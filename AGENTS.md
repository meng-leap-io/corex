# Corex.dev — AI Agent Guidelines

## 1. Project Overview

Corex.dev is a multi-domain AI-powered development platform. It consists of two main services:

- **Backend** (`backend/`): Laravel 11 PHP application — API routes, models, views (Blade/Alpine.js), Sanctum auth, PostgreSQL
- **AI Gateway** (`ai-gateway/`): Python 3.12 FastAPI service — multi-provider AI routing, agent system, rate limiting, usage tracking

**Architecture highlights:**
- UUID primary keys across all database tables (`HasUuids` trait, `uuid('id')->primary()`)
- `jsonb` columns for flexible data (profiles, AI usage metadata, project settings)
- All timestamps use `timestampsTz` (timezone-aware)
- Soft deletes enabled on all entity models
- Services communicate via HTTP (backend → ai-gateway at `AI_GATEWAY_URL`)
- Docker Compose for local dev (8 services); Kubernetes for production

---

## 2. Development Environment

### Prerequisites
```bash
# Local development
php 8.4+ (with pdo_pgsql, mbstring, bcmath, redis)
python 3.12+
composer 2.x
docker & docker compose
postgresql 16
redis 7+
```

### Setup
```bash
# Backend
cd backend
cp .env.example .env      # Configure DB, Redis, API keys
composer install
php artisan key:generate
php artisan migrate --seed

# AI Gateway
cd ai-gateway
python -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt

# Docker (full stack)
docker compose up -d
```

### Available Commands
```bash
cd backend
composer run format    # Laravel Pint (PHP CS Fixer)
composer run test      # PHPUnit

cd ai-gateway
black --check --line-length=100 app/
ruff check app/
mypy app/
pytest --asyncio-mode=auto --cov=app -v
```

---

## 3. Coding Conventions

### PHP / Laravel (PSR-12)

```php
<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class UserService
{
    public function __construct(
        private readonly User $user,
    ) {}

    public function findOrFail(string $id): User
    {
        return Cache::remember("user:{$id}", 3600, function () use ($id) {
            return $this->user->findOrFail($id);
        });
    }
}
```

**Rules:**
- `<?php` on line 1, no closing `?>`
- PSR-4 autoloading under `App\` → `app/`
- Type hints + `readonly` properties on constructors
- `declare(strict_types=1)` on all new files
- Named arguments for function calls with >3 params
- Avoid Facades in service layer — inject via constructor
- Use `Cache::remember()`, never `Cache::put()` + `Cache::get()` separately
- All models: traits always ordered as `HasApiTokens, HasFactory, HasUuids, Notifiable, SoftDeletes`
- Use `foreignUuid()` for foreign keys referencing UUID columns
- Always define `protected $casts`, `$fillable`, `$hidden` on Eloquent models
- Use `Builder` scopes for reusable query filters (`scopeActive()`, `scopeByPlan()`)
- Accessors/mutators via `get{Field}Attribute` and `set{Field}Attribute`

### Python / FastAPI (PEP 8 + Black)

```python
from __future__ import annotations

import time
from abc import ABC, abstractmethod
from typing import Any, AsyncGenerator

import httpx
from structlog import get_logger
from pydantic import BaseModel, Field

logger = get_logger(__name__)


class CompletionRequest(BaseModel):
    model: str = Field(..., min_length=1)
    messages: list[dict[str, str]]
    temperature: float = Field(default=0.7, ge=0, le=2)


class MyProvider(BaseProvider):
    name: str = "my-provider"
    base_url: str = "https://api.example.com/v1"
    api_key_env: str = "MY_API_KEY"
    default_models: list[str] = ["my-model-1", "my-model-2"]
    supports_streaming: bool = True
```

**Rules:**
- `from __future__ import annotations` at the top of every file
- `ruff` for linting (replace flake8/isort), `black --line-length=100` for formatting
- Pydantic v2 models for all request/response schemas
- `structlog` for all logging — never use `print()` or `logging` directly
- Async functions for all I/O-bound operations (httpx, redis)
- `BaseProvider` ABC pattern for all AI providers
- Use `@property` for computed fields on dataclasses/models
- Type hints on all function signatures (return types required)
- All env vars loaded through `pydantic-settings` `BaseSettings`

### JavaScript (ESLint + Prettier)

```javascript
// backend/resources/js/sentry.js
export function initSentry() {
  if (!SENTRY_DSN) {
    console.debug('[Sentry] disabled');
    return;
  }
  // ... SDK init
}
```

**Rules:**
- ES module syntax (`import`/`export`)
- No var — use `const` / `let`
- `camelCase` for variables and functions
- Async/await over promise chains
- Prefer optional chaining (`?.`) and nullish coalescing (`??`)
- Single quotes for strings, no semicolons
- 2-space indentation

---

## 4. Git Workflow

### Branching
```
main          ── Production (protected, requires PR + approval)
├── develop   ── Staging (integration branch)
├── feat/*    ── New features (branch off develop)
├── fix/*     ── Bug fixes (branch off develop or main for hotfixes)
├── chore/*   ── Tooling, deps, config
└── docs/*    ── Documentation only
```

### Commits
```
type(scope): brief description

Optional body with details. Use imperative mood.
```

Types: `feat`, `fix`, `chore`, `docs`, `style`, `refactor`, `perf`, `test`, `ci`

Examples:
```
feat(backend): add subscription cancellation webhook
fix(ai-gateway): handle OpenAI rate limit 429 with backoff
chore(deps): upgrade sentry-sdk to 2.7.2
docs(api): document /v1/agent/execute endpoint
```

### Pull Requests
- Always target `develop` (not `main` directly)
- Title must match commit convention
- Description: what, why, how, testing steps
- Add labels: `backend`, `ai-gateway`, `infra`, `ci`, `breaking`
- Link related issues
- Request review from relevant team

---

## 5. Testing Requirements

### PHP (PHPUnit 11)
```bash
cd backend
php artisan make:test UserServiceTest --unit
```
- Tests in `backend/tests/` mirroring `app/` structure (`tests/Unit/`, `tests/Feature/`)
- Feature tests use `RefreshDatabase` or `DatabaseTransactions` trait
- Factories for all models in `database/factories/`
- Aim for ≥80% coverage on business logic, ≥70% overall
- Mock external HTTP calls (Guzzle) with `Http::fake()`

### Python (pytest + pytest-asyncio)
```bash
cd ai-gateway
pytest --asyncio-mode=auto --cov=app -v
```
- Tests in `ai-gateway/tests/` mirroring `app/` structure
- Use `AsyncClient` for FastAPI endpoint tests
- Mock provider API calls with `httpx.MockTransport` or `respx`
- `conftest.py` for shared fixtures (test client, mock DB, mock Redis)
- Aim for ≥80% coverage
- Name test files `test_{module}.py` and test functions `test_{function}_{scenario}`

---

## 6. Documentation Standards

### Docstrings
```python
# Python — Google-style
def calculate_cost(model: str, tokens: int) -> float:
    """Calculate the cost of an AI API call.

    Args:
        model: The model identifier (e.g. "gpt-4o")
        tokens: Total token count

    Returns:
        Cost in USD

    Raises:
        ValueError: If model is not in the pricing table
    """
```

```php
/**
 * Calculate subscription cost with applicable discounts.
 *
 * @param User $user  The subscriber
 * @param string $plan  Plan identifier (free|pro|team)
 * @return float  Monthly cost in USD
 * @throws InvalidArgumentException If plan is invalid
 */
public function calculateCost(User $user, string $plan): float
```

### API Endpoint Documentation
- All FastAPI endpoints auto-documented via swagger at `/docs`
- Laravel routes documented in `routes/api.php` with inline comments
- Major workflows documented as Markdown in `docs/`

---

## 7. Security Practices

- **Do NOT commit secrets, API keys, tokens, or passwords** — use `.env.example`
- Never log sensitive data (passwords, tokens, PII) — redact in `before_send`
- SQL injection prevention: use Eloquent ORM, parameterized queries, avoid `DB::raw()`
- XSS prevention: Blade auto-escapes `{{ }}`, use `{!! !!}` only for trusted content
- CSRF: Sanctum token-based auth for API routes, SPA uses `XSRF-TOKEN`
- Rate limiting: `throttle:auth` (10/min), `throttle:api` (120/min), AI provider limits
- CORS restricted to known origins in production
- Input validation via Form Requests (Laravel) or Pydantic models (FastAPI)
- All AI provider keys stored in environment variables, never in database
- Production containers run as non-root user (`appuser`)

---

## 8. Performance Considerations

### Database
- Always add indexes to foreign keys, JSONB query paths, and frequently filtered columns
- Use `cursor()` or `chunk()` for large datasets, never `->all()`
- Eager load relationships with `->with()` to avoid N+1 queries
- JSONB indexes: `$table->index('metadata->>key')` for queryable paths

### Caching
- Redis as primary cache (laravel config: `CACHE_DRIVER=redis`)
- Cache AI provider responses with TTL (configurable via `REDIS_CACHE_TTL`)
- Cache user profiles, feature flags, configuration — invalidate on writes
- Use `Cache::tags()` for group invalidation where supported

### AI Gateway
- Provider response caching (Redis + local fallback)
- Connection pooling via httpx `AsyncClient` with limited pool size
- Rate limiting: TokenBucket local + Redis sliding window distributed
- Cost limits per user/day with pre-check before API call
- `--limit-max-requests` on uvicorn to prevent memory leaks (set to 10000)

---

## 9. Deployment Process

### Environments
| Environment | URL | AWS EKS Cluster | CI/CD |
|-------------|-----|----------------|-------|
| `develop` → staging | `staging.corex.dev` | `corex-staging` | Auto on push |
| `main` → production | `corex.dev` | `corex-production` | Manual approval |

### CI/CD Pipelines (`.github/workflows/`)
- **backend.yml**: lint → test (PostgreSQL+Redis services) → security scan → Docker build → deploy (staging auto, production manual)
- **ai-gateway.yml**: lint (black/flake8/mypy) → pytest → Docker build → deploy (blue-green for production)
- **frontend.yml**: ESLint → build assets → S3 sync → CloudFront invalidation
- **security.yml**: CodeQL + Trivy + Gitleaks + Composer/pip audit (weekly + on push)

### Rollback
```bash
# Manual rollback via workflow_dispatch with rollback=true
gh workflow run backend.yml -f environment=production -f rollback=true

# Or via kubectl
kubectl rollout undo deployment/corex-backend -n corex
kubectl rollout status deployment/corex-backend -n corex
```

---

## 10. Common Tasks

### Adding a New API Endpoint (Laravel)

```php
// 1. Create Form Request
// backend/app/Http/Requests/StoreProjectRequest.php
class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Project::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

// 2. Add controller method
// backend/app/Http/Controllers/Api/ProjectController.php
public function store(StoreProjectRequest $request): JsonResponse
{
    $project = $this->projectService->create(
        $request->user(),
        $request->validated(),
    );
    return new JsonResponse(new ProjectResource($project), 201);
}

// 3. Register route
// backend/routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/projects', [ProjectController::class, 'store']);
});
```

### Adding a New AI Provider (Python)

```python
# 1. Create provider class
# ai-gateway/app/services/providers/my_provider.py
from app.services.providers.base import BaseProvider


class MyProvider(BaseProvider):
    name: str = "my-provider"
    base_url: str = "https://api.example.com/v1"
    api_key_env: str = "MY_API_KEY"
    default_models: list[str] = ["example-model"]
    supports_streaming: bool = True
    cost_per_token: dict[str, tuple[float, float]] = {
        "example-model": (0.000_010, 0.000_030),  # (input, output) per token
    }

# 2. Register in AI router
# ai-gateway/app/services/ai_router.py
from app.services.providers.my_provider import MyProvider

class AIRouter:
    def __init__(self):
        self._providers: dict[str, BaseProvider] = {
            "openai": OpenaiProvider(),
            "anthropic": AnthropicProvider(),
            "my-provider": MyProvider(),
        }
        self._model_map: dict[str, str] = {
            "example-model": "my-provider",
            **self._build_model_map(),
        }

# 3. Add env var to config
# ai-gateway/app/core/config.py
class Settings(BaseSettings):
    my_api_key: Optional[str] = Field(default=None, alias="MY_API_KEY")
```

### Creating a New Migration (Laravel)

```php
// backend/database/migrations/2024_06_26_000001_create_teams_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('slug', 100)->unique();
            $table->jsonb('settings')->default('{}');
            $table->timestampsTz();
            $table->softDeletes();

            $table->index('owner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
```

### Debugging Common Issues

| Symptom | Likely Cause | Fix |
|---------|-------------|-----|
| 500 on model save | Missing `$fillable` or `$casts` | Add `protected $fillable = ['field'];` |
| PHP `Target class [X] does not exist` | Autoload cache stale | `composer dump-autoload` |
| FastAPI `422 Validation Error` | Pydantic schema mismatch | Check request body matches model fields |
| PostgreSQL `connection refused` | pg_hba.conf or service not running | `docker compose up -d postgres` |
| AI provider 401 | Missing or invalid API key | Check `.env` variable matches `api_key_env` |
| Queue jobs not processing | Queue worker not running | `php artisan queue:work redis --sleep=3 --tries=3` |
| Docker `no matching manifest` | Wrong platform/arch | Add `--platform linux/amd64` to build |
| Composer `Allowed memory size exhausted` | PHP memory limit | `php -d memory_limit=-1 composer install` |
| Python `ModuleNotFoundError: X` | Missing dependency | `pip install -r requirements.txt` |
| `kubectl rollout` stuck | Pods not becoming ready | `kubectl describe pod <name> -n corex` |

---

## AI Agent Constraints

1. **Read before edit** — Always read the file you're modifying before making changes.
2. **Follow existing patterns** — Match code style of the file you're working in (imports, naming, structure).
3. **One task at a time** — Complete one change before starting another. Verify each step.
4. **No magic strings** — Use constants/`Enum` for repeated values (plan types, status codes, model names).
5. **No secrets** — Never hardcode API keys, passwords, or tokens. Use env vars with `pydantic-settings` or Laravel `.env`.
6. **Test your changes** — Run relevant tests after any code change. If tests don't exist, write them.
7. **Cross-service awareness** — Backend and AI Gateway share Redis and PostgreSQL; changes to one may affect the other.
8. **Docker context** — Always consider that code runs inside containers; paths, env vars, and network names must match `docker-compose.yml`.
9. **Idempotent migrations** — All database migrations must be safe to run multiple times (`CREATE IF NOT EXISTS`, `addColumn` guards).
10. **Ask when uncertain** — If a decision could have downstream effects (schema changes, public API contract, infra), ask the user.
