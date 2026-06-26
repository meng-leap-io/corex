from __future__ import annotations

import hashlib
import json
from typing import Any, Dict, List, Optional

from structlog import get_logger

logger = get_logger(__name__)


class PromptTemplate:
    def __init__(
        self,
        name: str,
        version: str,
        system_prompt: str,
        user_template: str,
        model: Optional[str] = None,
        temperature: float = 0.7,
        max_tokens: int = 4096,
    ):
        self.name = name
        self.version = version
        self.system_prompt = system_prompt
        self.user_template = user_template
        self.model = model
        self.temperature = temperature
        self.max_tokens = max_tokens

    def render(self, **kwargs) -> Dict[str, Any]:
        return {
            "system": self._render_system(**kwargs),
            "user": self.user_template.format(**kwargs),
        }

    def _render_system(self, **kwargs) -> str:
        try:
            return self.system_prompt.format(**kwargs)
        except KeyError as e:
            logger.warning("prompt_template_missing_var", template=self.name, var=str(e))
            return self.system_prompt


class PromptManager:
    def __init__(self):
        self._templates: Dict[str, List[PromptTemplate]] = {}
        self._load_defaults()

    def _load_defaults(self) -> None:
        self.register(
            PromptTemplate(
                name="code_generation",
                version="1.0",
                system_prompt=(
                    "You are an expert software engineer. Generate clean, production-ready code following best practices. "
                    "Use modern language features and include proper error handling. "
                    "Language: {language}. "
                    "Focus on: {focus}."
                ),
                user_template="{prompt}",
                model="gpt-4o-mini",
                temperature=0.2,
                max_tokens=4096,
            )
        )
        self.register(
            PromptTemplate(
                name="code_debug",
                version="1.0",
                system_prompt=(
                    "You are an expert debugging assistant. Analyze the following code and error message. "
                    "Identify the root cause, explain it clearly, and provide a fix."
                ),
                user_template=(
                    "Language: {language}\n\n"
                    "Code:\n```\n{code}\n```\n\n"
                    "Error: {error_message}\n\n"
                    "Please debug this issue."
                ),
                model="gpt-4o",
                temperature=0.1,
            )
        )
        self.register(
            PromptTemplate(
                name="code_refactor",
                version="1.0",
                system_prompt=(
                    "You are an expert code refactoring assistant. Analyze the provided code "
                    "and suggest improvements for readability, performance, and maintainability. "
                    "Explain each change and provide the refactored code."
                ),
                user_template=(
                    "Language: {language}\n\n"
                    "Code to refactor:\n```\n{code}\n```\n\n"
                    "Additional instructions: {instructions}\n\n"
                    "Please refactor this code."
                ),
                model="gpt-4o",
                temperature=0.3,
            )
        )
        self.register(
            PromptTemplate(
                name="code_explain",
                version="1.0",
                system_prompt=(
                    "You are an expert code explainer. Explain the following code in {detail_level} detail. "
                    "Cover the purpose, logic flow, key patterns, and potential improvements."
                ),
                user_template=(
                    "Language: {language}\n\n"
                    "Code:\n```\n{code}\n```\n\n"
                    "Please explain this code."
                ),
                model="gpt-4o-mini",
                temperature=0.5,
            )
        )

    def register(self, template: PromptTemplate) -> None:
        if template.name not in self._templates:
            self._templates[template.name] = []
        self._templates[template.name].append(template)
        logger.info(
            "prompt_template_registered",
            name=template.name,
            version=template.version,
        )

    def get_template(
        self,
        name: str,
        version: Optional[str] = None,
    ) -> Optional[PromptTemplate]:
        templates = self._templates.get(name, [])
        if not templates:
            return None
        if version:
            for t in templates:
                if t.version == version:
                    return t
        return templates[-1]

    def render(
        self,
        name: str,
        version: Optional[str] = None,
        **kwargs,
    ) -> Optional[Dict[str, Any]]:
        template = self.get_template(name, version)
        if not template:
            return None
        return template.render(**kwargs)

    def list_templates(self) -> List[Dict[str, Any]]:
        result = []
        for name, templates in self._templates.items():
            for t in templates:
                result.append({
                    "name": t.name,
                    "version": t.version,
                    "model": t.model,
                    "temperature": t.temperature,
                })
        return result

    def compute_hash(self, name: str, **kwargs) -> str:
        raw = json.dumps({"name": name, "params": kwargs}, sort_keys=True)
        return hashlib.sha256(raw.encode()).hexdigest()[:16]


prompt_manager = PromptManager()
