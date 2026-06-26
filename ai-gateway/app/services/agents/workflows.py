from __future__ import annotations

from typing import Any, Dict, List

from app.services.agents.orchestrator import WorkflowDefinition

# ---------------------------------------------------------------------------
# Workflow: Build a Blog Website
# ---------------------------------------------------------------------------
WORKFLOW_BUILD_BLOG: Dict[str, Any] = {
    "name": "build_blog_website",
    "description": "Build a complete blog website from requirements — plan, code, test, review, document",
    "steps": [
        {
            "agent": "planner",
            "action": "plan",
            "description": "Break blog website requirements into architecture and tasks",
            "depends_on": [],
            "parallel": False,
        },
        {
            "agent": "coder",
            "action": "code_backend",
            "description": "Implement backend (models, routes, controllers)",
            "depends_on": ["planner:plan"],
            "parallel": False,
            "config": {"temperature": 0.2},
        },
        {
            "agent": "coder",
            "action": "code_frontend",
            "description": "Implement frontend templates and components",
            "depends_on": ["planner:plan"],
            "parallel": False,
            "config": {"temperature": 0.2},
        },
        {
            "agent": "tester",
            "action": "test",
            "description": "Generate unit tests for backend code",
            "depends_on": ["coder:code_backend"],
            "parallel": False,
        },
        {
            "agent": "reviewer",
            "action": "review",
            "description": "Review all generated code",
            "depends_on": ["coder:code_backend", "coder:code_frontend", "tester:test"],
            "parallel": False,
        },
        {
            "agent": "security",
            "action": "security_scan",
            "description": "Security audit of the blog application",
            "depends_on": ["coder:code_backend", "coder:code_frontend"],
            "parallel": False,
        },
        {
            "agent": "documentation",
            "action": "document",
            "description": "Generate README and setup documentation",
            "depends_on": ["reviewer:review", "security:security_scan"],
            "parallel": False,
        },
    ],
}

# ---------------------------------------------------------------------------
# Workflow: Debug This Code
# ---------------------------------------------------------------------------
WORKFLOW_DEBUG_CODE: Dict[str, Any] = {
    "name": "debug_code",
    "description": "Analyze, debug, and fix buggy code with test verification",
    "steps": [
        {
            "agent": "debugger",
            "action": "diagnose",
            "description": "Analyze the bug and identify root cause",
            "depends_on": [],
            "parallel": False,
        },
        {
            "agent": "coder",
            "action": "apply_fix",
            "description": "Apply the fix suggested by the debugger",
            "depends_on": ["debugger:diagnose"],
            "parallel": False,
            "config": {"temperature": 0.1},
        },
        {
            "agent": "tester",
            "action": "test_fix",
            "description": "Generate tests to validate the fix",
            "depends_on": ["coder:apply_fix"],
            "parallel": False,
        },
        {
            "agent": "reviewer",
            "action": "review_fix",
            "description": "Review the fix and tests",
            "depends_on": ["coder:apply_fix", "tester:test_fix"],
            "parallel": False,
        },
    ],
}

# ---------------------------------------------------------------------------
# Workflow: Write Tests for This Function
# ---------------------------------------------------------------------------
WORKFLOW_WRITE_TESTS: Dict[str, Any] = {
    "name": "write_tests",
    "description": "Generate comprehensive unit tests for provided code",
    "steps": [
        {
            "agent": "tester",
            "action": "analyze_coverage",
            "description": "Analyze code and plan test coverage",
            "depends_on": [],
            "parallel": False,
        },
        {
            "agent": "coder",
            "action": "generate_tests",
            "description": "Generate the actual test files",
            "depends_on": ["tester:analyze_coverage"],
            "parallel": False,
            "config": {"temperature": 0.2},
        },
        {
            "agent": "reviewer",
            "action": "review_tests",
            "description": "Review tests for completeness and correctness",
            "depends_on": ["coder:generate_tests"],
            "parallel": False,
        },
        {
            "agent": "security",
            "action": "security_test_review",
            "description": "Check tests for security testing coverage",
            "depends_on": ["reviewer:review_tests"],
            "parallel": False,
        },
    ],
}

# ---------------------------------------------------------------------------
# Workflow: Generate Documentation
# ---------------------------------------------------------------------------
WORKFLOW_GENERATE_DOCS: Dict[str, Any] = {
    "name": "generate_documentation",
    "description": "Generate comprehensive documentation for code or API",
    "steps": [
        {
            "agent": "documentation",
            "action": "generate_docs",
            "description": "Generate the primary documentation",
            "depends_on": [],
            "parallel": False,
            "config": {"temperature": 0.3},
        },
        {
            "agent": "reviewer",
            "action": "review_docs",
            "description": "Review documentation for clarity and completeness",
            "depends_on": ["documentation:generate_docs"],
            "parallel": False,
        },
        {
            "agent": "coder",
            "action": "verify_code_examples",
            "description": "Verify code examples in documentation are correct",
            "depends_on": ["documentation:generate_docs"],
            "parallel": False,
        },
    ],
}

# ---------------------------------------------------------------------------
# Workflow: Full Application Development
# ---------------------------------------------------------------------------
WORKFLOW_FULL_APP: Dict[str, Any] = {
    "name": "full_app_development",
    "description": "End-to-end application development: plan, code, test, review, secure, document",
    "steps": [
        {
            "agent": "planner",
            "action": "plan",
            "description": "Create architecture and implementation plan",
            "depends_on": [],
            "parallel": False,
        },
        {
            "agent": "coder",
            "action": "code",
            "description": "Implement the application code",
            "depends_on": ["planner:plan"],
            "parallel": False,
        },
        {
            "agent": "tester",
            "action": "test",
            "description": "Generate unit and integration tests",
            "depends_on": ["coder:code"],
            "parallel": False,
        },
        {
            "agent": "security",
            "action": "security_scan",
            "description": "Full security audit",
            "depends_on": ["coder:code"],
            "parallel": True,
        },
        {
            "agent": "reviewer",
            "action": "review",
            "description": "Final code review",
            "depends_on": ["coder:code", "tester:test", "security:security_scan"],
            "parallel": False,
        },
        {
            "agent": "documentation",
            "action": "document",
            "description": "Generate all documentation",
            "depends_on": ["reviewer:review"],
            "parallel": False,
        },
    ],
}

# ---------------------------------------------------------------------------
# Registry
# ---------------------------------------------------------------------------
WORKFLOW_REGISTRY: Dict[str, Dict[str, Any]] = {
    "build_blog_website": WORKFLOW_BUILD_BLOG,
    "debug_code": WORKFLOW_DEBUG_CODE,
    "write_tests": WORKFLOW_WRITE_TESTS,
    "generate_documentation": WORKFLOW_GENERATE_DOCS,
    "full_app_development": WORKFLOW_FULL_APP,
}

WORKFLOW_EXAMPLES: List[Dict[str, Any]] = [
    {
        "title": "Build a Blog Website",
        "description": "Plan, implement, test, and document a complete blog website with posts, comments, and user authentication.",
        "workflow": "build_blog_website",
        "sample_input": {
            "prompt": "Build a blog website with user registration, post creation with markdown editor, comments system with nested replies, tags/categories, search functionality, and an admin dashboard. Use Python FastAPI backend with SQLAlchemy and Jinja2 templates.",
        },
    },
    {
        "title": "Debug This Code",
        "description": "Analyze buggy code, identify root cause, fix bugs, and verify with tests.",
        "workflow": "debug_code",
        "sample_input": {
            "code": "def calculate_total(items):\n    total = 0\n    for i in range(len(items)):\n        total += items[i]['price']\n    return total\n\n# Bug: doesn't handle empty list, items without 'price' key, or quantity multiplier",
            "error_message": "KeyError: 'price' when an item lacks price field",
        },
    },
    {
        "title": "Write Tests for This Function",
        "description": "Generate comprehensive unit tests with edge case coverage.",
        "workflow": "write_tests",
        "sample_input": {
            "code": "def is_valid_email(email: str) -> bool:\n    import re\n    pattern = r'^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$'\n    return bool(re.match(pattern, email))",
            "language": "python",
            "test_framework": "pytest",
        },
    },
    {
        "title": "Generate Documentation",
        "description": "Create README, API reference, or architecture documentation from source code.",
        "workflow": "generate_documentation",
        "sample_input": {
            "source": "Provide a codebase or description to document",
            "doc_type": "readme",
            "audience": "developers",
        },
    },
]


def get_workflow(name: str) -> WorkflowDefinition:
    config = WORKFLOW_REGISTRY.get(name)
    if not config:
        available = ", ".join(WORKFLOW_REGISTRY.keys())
        raise ValueError(f"Workflow '{name}' not found. Available: {available}")
    return WorkflowDefinition(
        name=config["name"],
        description=config["description"],
        steps=config["steps"],
    )
