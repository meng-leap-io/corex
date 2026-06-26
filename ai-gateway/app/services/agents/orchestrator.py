from __future__ import annotations

import json
import time
import uuid
from typing import Any, Dict, List, Optional

from structlog import get_logger

from app.services.agents.base_agent import (
    AgentError,
    AgentState,
    AgentStep,
    BaseAgent,
    RetryExhaustedError,
)

logger = get_logger(__name__)


class WorkflowStep(BaseAgent):
    """Placeholder agent for workflow steps that have no dedicated agent."""

    agent_name: str = "passthrough"
    agent_description: str = "Pass-through step"
    system_prompt_template: str = ""

    async def validate_input(self, input_data: Dict[str, Any]) -> Dict[str, Any]:
        return input_data

    async def parse_output(self, raw_output: str, input_data: Dict[str, Any]) -> Dict[str, Any]:
        return {"output": raw_output}

    async def process(self, input_data: Dict[str, Any]) -> Dict[str, Any]:
        return input_data


class WorkflowDefinition:
    def __init__(
        self,
        name: str,
        description: str,
        steps: List[Dict[str, Any]],
    ):
        self.name = name
        self.description = description
        self.steps = steps

    def to_dict(self) -> Dict[str, Any]:
        return {
            "name": self.name,
            "description": self.description,
            "steps": [
                {
                    "agent": s.get("agent", ""),
                    "action": s.get("action", ""),
                    "description": s.get("description", ""),
                    "depends_on": s.get("depends_on", []),
                    "parallel": s.get("parallel", False),
                    "config": s.get("config", {}),
                    "rollback": s.get("rollback", None),
                }
                for s in self.steps
            ],
        }


class AgentOrchestrator:
    def __init__(self):
        self._agents: Dict[str, BaseAgent] = {}
        self._states: Dict[str, AgentState] = {}

    def register_agent(self, name: str, agent: BaseAgent) -> None:
        self._agents[name] = agent
        logger.info("agent_registered", name=name)

    def get_agent(self, name: str) -> BaseAgent:
        agent = self._agents.get(name)
        if not agent:
            available = ", ".join(self._agents.keys())
            raise AgentError("orchestrator", f"Agent '{name}' not found. Available: {available}")
        return agent

    async def execute_workflow(
        self,
        workflow: WorkflowDefinition,
        input_data: Dict[str, Any],
        run_id: Optional[str] = None,
    ) -> AgentState:
        run_id = run_id or f"run_{uuid.uuid4().hex[:12]}"
        started_at = time.time()

        state = AgentState(
            run_id=run_id,
            workflow_name=workflow.name,
            started_at=started_at,
            status="running",
        )
        self._states[run_id] = state

        artifacts: Dict[str, Any] = {"input": input_data, "outputs": {}}
        completed_steps: Dict[str, Dict[str, Any]] = {}
        rollback_stack: List[str] = []

        try:
            step_defs = workflow.steps

            for step_def in step_defs:
                agent_name = step_def["agent"]
                action = step_def.get("action", "process")
                parallel_group = step_def.get("parallel", False)
                depends_on = step_def.get("depends_on", [])
                step_config = step_def.get("config", {})

                all_deps_met = all(d in completed_steps for d in depends_on)
                if not all_deps_met:
                    missing = [d for d in depends_on if d not in completed_steps]
                    raise AgentError(
                        "orchestrator",
                        f"Step '{agent_name}:{action}' dependencies not met: {missing}",
                    )

                prepared_input = self._prepare_step_input(
                    step_def, input_data, completed_steps, artifacts
                )

                state.current_agent = f"{agent_name}:{action}"

                if parallel_group:
                    # Parallel execution handled within the group
                    step_result = await self._execute_parallel(
                        step_def, prepared_input, state
                    )
                else:
                    step_result = await self._execute_single(
                        agent_name, action, prepared_input, state, step_config
                    )

                step_key = f"{agent_name}:{action}"
                completed_steps[step_key] = step_result
                artifacts["outputs"][step_key] = step_result
                rollback_stack.append(step_key)

                state.artifacts = artifacts

            state.status = "completed"
            state.completed_at = time.time()
            state.artifacts = artifacts
            state.artifacts["final_output"] = self._build_final_output(
                completed_steps, workflow.name
            )

            logger.info(
                "workflow_completed",
                run_id=run_id,
                workflow=workflow.name,
                duration_ms=round((time.time() - started_at) * 1000),
                steps=len(completed_steps),
            )

        except Exception as e:
            state.status = "failed"
            state.completed_at = time.time()
            state.errors.append(str(e))
            logger.error(
                "workflow_failed",
                run_id=run_id,
                workflow=workflow.name,
                error=str(e),
                step=state.current_agent,
            )

            if rollback_stack:
                await self._execute_rollback(
                    rollback_stack, completed_steps, artifacts
                )

        return state

    async def _execute_single(
        self,
        agent_name: str,
        action: str,
        input_data: Dict[str, Any],
        state: AgentState,
        config: Optional[Dict[str, Any]] = None,
    ) -> Dict[str, Any]:
        agent = self.get_agent(agent_name)
        step_key = f"{agent_name}:{action}"

        step = AgentStep(
            agent=agent_name,
            action=action,
            input_data=input_data,
            started_at=time.time(),
            status="running",
        )
        state.steps.append(step)

        try:
            result = await agent.process(input_data)
            step.output_data = result
            step.completed_at = time.time()
            step.duration_ms = round((step.completed_at - step.started_at) * 1000)
            step.status = "completed"
            logger.info(
                "step_completed",
                step=step_key,
                duration_ms=step.duration_ms,
                tokens=step.tokens_used,
            )
            return result

        except Exception as e:
            step.error = str(e)
            step.completed_at = time.time()
            step.duration_ms = round((step.completed_at - step.started_at) * 1000)
            step.status = "failed"
            state.errors.append(f"[{step_key}] {str(e)}")
            raise

    async def _execute_parallel(
        self,
        step_def: Dict[str, Any],
        base_input: Dict[str, Any],
        state: AgentState,
    ) -> Dict[str, Any]:
        import asyncio

        parallel_agents = step_def.get("agents", [])
        tasks = []
        for agent_name in parallel_agents:
            action = "process"
            task = self._execute_single(agent_name, action, base_input, state)
            tasks.append(task)

        results = await asyncio.gather(*tasks, return_exceptions=True)
        merged: Dict[str, Any] = {"parallel_results": []}

        for agent_name, result in zip(parallel_agents, results):
            if isinstance(result, Exception):
                merged["parallel_results"].append({
                    "agent": agent_name,
                    "error": str(result),
                })
                state.errors.append(f"[parallel:{agent_name}] {str(result)}")
            else:
                merged["parallel_results"].append({
                    "agent": agent_name,
                    "result": result,
                })

        return merged

    def _prepare_step_input(
        self,
        step_def: Dict[str, Any],
        original_input: Dict[str, Any],
        completed_steps: Dict[str, Any],
        artifacts: Dict[str, Any],
    ) -> Dict[str, Any]:
        step_input = dict(original_input)
        depends_on = step_def.get("depends_on", [])

        for dep_key in depends_on:
            if dep_key in completed_steps:
                dep_result = completed_steps[dep_key]
                if isinstance(dep_result, dict):
                    for k, v in dep_result.items():
                        if k not in step_input:
                            step_input[k] = v

        context = step_def.get("context", {})
        for ctx_key, ctx_value in context.items():
            if isinstance(ctx_value, str) and ctx_value.startswith("$"):
                ref_path = ctx_value[1:]
                parts = ref_path.split(".")
                ref_data = artifacts
                for part in parts:
                    if isinstance(ref_data, dict):
                        ref_data = ref_data.get(part, {})
                    else:
                        ref_data = {}
                step_input[ctx_key] = ref_data

        return step_input

    def _build_final_output(
        self, completed_steps: Dict[str, Any], workflow_name: str
    ) -> Dict[str, Any]:
        final = {
            "workflow": workflow_name,
            "steps_completed": len(completed_steps),
        }
        for step_key, result in completed_steps.items():
            agent_name = step_key.split(":")[0]
            if agent_name in ("planner",):
                final["plan"] = result
            elif agent_name in ("coder",):
                final.setdefault("code", []).append(result)
            elif agent_name in ("tester",):
                final["tests"] = result
            elif agent_name in ("reviewer",):
                final["review"] = result
            elif agent_name in ("debugger",):
                final["debug"] = result
            elif agent_name in ("documentation",):
                final["documentation"] = result
            elif agent_name in ("security",):
                final["security"] = result
        return final

    async def _execute_rollback(
        self,
        rollback_stack: List[str],
        completed_steps: Dict[str, Any],
        artifacts: Dict[str, Any],
    ) -> None:
        logger.info("rollback_started", steps=len(rollback_stack))
        for step_key in reversed(rollback_stack):
            agent_name = step_key.split(":")[0]
            try:
                agent = self.get_agent(agent_name)
                step_result = completed_steps.get(step_key, {})
                await agent.process({"rollback": True, "previous_result": step_result})
                logger.info("rollback_step", step=step_key)
            except Exception as e:
                logger.warning("rollback_failed", step=step_key, error=str(e))

    def get_state(self, run_id: str) -> Optional[AgentState]:
        return self._states.get(run_id)

    def list_states(self) -> List[Dict[str, Any]]:
        return [
            {
                "run_id": s.run_id,
                "workflow": s.workflow_name,
                "status": s.status,
                "steps": len(s.steps),
                "errors": len(s.errors),
                "started_at": s.started_at,
            }
            for s in self._states.values()
        ]

    def clear_state(self, run_id: str) -> bool:
        if run_id in self._states:
            del self._states[run_id]
            return True
        return False


orchestrator = AgentOrchestrator()
