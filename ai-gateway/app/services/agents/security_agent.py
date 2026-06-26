from __future__ import annotations

from typing import Any, Dict, List, Optional

from structlog import get_logger

from app.services.agents.base_agent import (
    AgentConfig,
    AgentError,
    BaseAgent,
)

logger = get_logger(__name__)

SECURITY_SYSTEM_PROMPT = """You are a security engineer specialized in application security auditing. Your role is to identify security vulnerabilities and recommend fixes.

Scan for these vulnerability classes:
1. **Injection** — SQL, NoSQL, OS command, LDAP, XPath, template injection
2. **Authentication** — Weak auth, session fixation, missing MFA, brute force susceptibility
3. **Authorization** — IDOR, privilege escalation, missing access controls
4. **XSS** — Reflected, stored, DOM-based
5. **CSRF** — Missing tokens, weak validation
6. **SSRF** — Server-side request forgery
7. **Data Exposure** — PII leaks, excessive logging, insecure storage
8. **Cryptography** — Weak algorithms, hardcoded keys, improper certificate validation
9. **Dependency** — Known vulnerabilities, outdated libraries
10. **Configuration** — Debug enabled, default credentials, permissive CORS

For each finding, include:
- CWE identifier (e.g., CWE-79)
- Severity (critical/high/medium/low)
- Affected file and line
- Impact description
- Remediation with code example

Output as a JSON object:
{{
  "overall_risk": "critical|high|medium|low",
  "summary": "brief security assessment",
  "findings": [
    {{
      "cwe": "CWE-79",
      "title": "Stored XSS in user profile",
      "severity": "high",
      "file": "path/to/file",
      "line": 42,
      "impact": "what an attacker can do",
      "remediation": "how to fix",
      "code_example": "fixed code snippet"
    }}
  ],
  "compliance_notes": ["PCI-DSS", "GDPR", "OWASP Top 10 considerations"],
  "security_score": 0-100
}}

Be thorough and specific. Include actual code fixes."""


class SecurityAgent(BaseAgent):
    agent_name: str = "security"
    agent_description: str = "Security scanning and vulnerability detection"
    system_prompt_template: str = SECURITY_SYSTEM_PROMPT

    def __init__(self, config: Optional[AgentConfig] = None):
        super().__init__(config or AgentConfig(temperature=0.2, max_tokens=6144, model="gpt-4o"))

    async def validate_input(self, input_data: Dict[str, Any]) -> Dict[str, Any]:
        if "code" not in input_data and "files" not in input_data:
            raise AgentError(self.agent_name, "Input must contain 'code' or 'files'")
        code = input_data.get("code", "")
        files = input_data.get("files", [])
        if not code and not files:
            raise AgentError(self.agent_name, "No code provided for security scan")
        return {
            "code": code.strip(),
            "files": files,
            "language": input_data.get("language", ""),
            "scan_level": input_data.get("scan_level", "thorough"),
            "context": input_data.get("context", ""),
        }

    async def parse_output(self, raw_output: str, input_data: Dict[str, Any]) -> Dict[str, Any]:
        parsed = self._extract_json(raw_output)
        if parsed and "findings" in parsed:
            return {
                "overall_risk": parsed.get("overall_risk", "medium"),
                "summary": parsed.get("summary", ""),
                "findings": parsed.get("findings", []),
                "compliance_notes": parsed.get("compliance_notes", []),
                "security_score": parsed.get("security_score", 50),
            }
        return {
            "overall_risk": "medium",
            "summary": "Security scan completed (fallback parsing)",
            "findings": [],
            "security_score": 50,
            "raw_output": raw_output,
        }

    async def process(self, input_data: Dict[str, Any]) -> Dict[str, Any]:
        validated = await self.validate_input(input_data)
        code = validated["code"]
        files = validated["files"]
        language = validated["language"]
        scan_level = validated["scan_level"]
        context = validated["context"]

        prompt_parts = [f"Perform a {scan_level} security audit on:\n"]
        if files:
            for f in files:
                path = f.get("path", "unknown")
                content = f.get("content", f.get("code", ""))
                prompt_parts.append(f"\n### {path}\n```\n{self._truncate(content)}\n```")
        if code:
            prompt_parts.append(f"\n```{language}\n{self._truncate(code)}\n```")
        if context:
            prompt_parts.append(f"\nContext:\n{context}")

        prompt = "\n".join(prompt_parts)
        messages = self._build_messages(prompt, {"language": language or "any"})
        raw = await self._call_with_retry(messages, self._make_step())
        return await self.parse_output(raw, input_data)

    def _make_step(self):
        from app.services.agents.base_agent import AgentStep
        return AgentStep(agent=self.agent_name, action="security_scan", input_data={})
