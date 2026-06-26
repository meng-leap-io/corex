# Corex.dev - Project Summary

## Overview

**Corex.dev** is a multi-domain, AI-powered development platform built with a **Laravel 11 (PHP 8.3)** backend, a **Python FastAPI AI Gateway**, and an **Nginx** reverse proxy. The project is currently in an early scaffolding stage with infrastructure, routing, and Dockerization largely in place, but core application logic (controllers, models, migrations, frontend apps) is not yet implemented.

> **Project State:** Early scaffolding / pre-development. Many referenced files (controllers, models, middleware, frontend apps, k8s/terraform, CI/CD) are empty or missing.

---

## 1. Folder Structure

```
/workspaces/corex/
├── README.md                          # Project description
├── .env.example                       # Environment variable template
├── .git/                              # Git repository
├── .github/workflows/                  # empty (CI/CD placeholder)
├── ai-gateway/
│   ├── Dockerfile                     # Multi-stage Python 3.11 image
│   ├── main.py                        # FastAPI entry point
│   ├── requirements.txt               # Python dependencies
│   ├── tests/                          # empty
│   └── app/                            # empty subdirs (api, core, models, services, utils)
├── backend/
│   ├── artisan                         # Laravel CLI entry point
│   ├── composer.json                   # PHP dependencies
│   ├── app/                            # empty (intended for controllers, models, services, traits, providers)
│   ├── bootstrap/
│   │   ├── app.php                     # Laravel application bootstrap
│   │   └── providers.php             # Service providers
│   ├── config/                         # empty
│   ├── database/
│   │   ├── factories/                  # empty
│   │   ├── migrations/                 # empty
│   │   └── seeders/                    # empty
│   SteamSetup.exe.bat
│   ├── public/
│   │   ├── index.php                  # Laravel web entry point
│   │   └── build/                     # (placeholder)
│   ├── resources/
│   │   ├── css/                        # empty
│   │   ├── js/                         # empty
│   │   └── views/                      # empty
│   ├── routes/
│   │   ├── api.php                    # API route definitions
│   │   └── web.php                    # Web route definitions
│   ├── storage/                        # Laravel storage (app, framework, logs)
│   ├── tests/
│   │   ├── Feature/                    # empty
│   │   └── Unit/                       # empty
│   └── vendor/                         # Composer packages (if installed)
├── docker/
│   ├── nginx/
│   │   ├── Dockerfile
│   │   ├── conf.d/
│   │   │   ├── corex.dev.conf         # Landing page vhost
│   │   │   └── console.corex.dev.conf # Console IDE vhost
│   │   └── nginx.conf                 # Main Nginx config
│   └── php/
│       ├── Dockerfile
│       └── php.ini
├── frontend/
│   ├── console/                        # empty (intended for IDE/Console app)
│   └── landing/                        # empty (intended for marketing site)
├── infra/
│   ├── k8s/                            # empty
│   └── terraform/                      # empty
└── scripts/                             # empty
```

---

## 2. How the Application Works

### Architecture

Corex is designed as a **multi-service, containerized platform** orchestrated via Docker Compose. It separates concerns across three main services:

1. **Frontend (Nginx)** – Serves static landing page (`corex.dev`) and console IDE (`console.corex.dev`).
2. **Backend (Laravel 11 / PHP-FPM)** – Handles REST API, authentication, and business logic.
3. **AI Gateway (Python FastAPI)** – Proxies and augments requests to AI providers (OpenAI, Anthropic).
4. **Cache/Queue/Session (Redis)** – Shared state between Laravel and the AI Gateway.

### Request Flow

1. User visits `https://corex.dev` or `https://console.corex.dev`
2. Nginx (SSL termination) serves static assets and proxies API requests to Laravel (`/api/*`)
3. Console requests to `/ai/*` are proxied to the FastAPI Gateway
4. WebSocket requests (`/ws/*`) are proxied to Laravel for real-time features
5. Laravel interacts with a PostgreSQL database (Supabase) and Redis
6. AI Gateway communicates with external AI provider APIs (OpenAI, Anthropic)

---

## 3. Main Technologies

| Layer            | Technology                   | Notes                                       |
|------------------|------------------------------|---------------------------------------------|
| **Backend**      | Laravel 11, PHP 8.3         | API + Web routing, Queue, Scheduler          |
| **AI Gateway**   | Python 3.11, FastAPI 0.111   | Async, Pydantic settings, structured logging |
| **Database**     | PostgreSQL (Supabase)        | `pdo_pgsql`Imagínate extensions             |
| **Cache/Queue**  | Redis 7 (Alpine)             | Sessions, caching, queues                    |
| **Reverse Proxy**| Nginx 1.25 (Alpine)          | SSL, HTTP/2, rate limiting, gzip             |
| **Auth**         | Laravel Sanctum + JWT (RS256) | Token-based API auth, JWT for inter-service   |
| **Frontend**     | (Planned) Console + Landing  | Directories exist but are empty              |
| **Infrastructure**| Docker, Docker Compose       | Production-oriented, multi-stage builds      |
| **Monitoring**   | Prometheus, Sentry           | Configured in AI Gateway deps                |

---

## 4. Entry Points

| Service        | Entry Point                                | Description                              |
|----------------|--------------------------------------------|------------------------------------------|
| **Laravel Web**| `backend/public/index.php`                | Standard Laravel public web entry        |
| **Laravel CLI**| `backend/artisan`                         | Artisan commands, migrations, jobs       |
| **AI Gateway** | `ai-gateway/main.py`                      | FastAPI + Uvicorn (port 8000)            |
| **Queue Worker**| Docker `queue` service: `php artisan queue:work` | Background job processing       |
| **Scheduler**  | Docker `scheduler` service: `php artisan schedule:work` | Cron-like scheduled tasks   |

---

## 5. Database Structure

> **Current state:** The `database/migrations/`, `factories/`, and `seeders/` directories are completely empty. No models, no migrations, no seed data exist yet.

### Inferred Intended Stack

- **Driver:** PostgreSQL (`pgsql`)
- **Host:** `db.supabase.co` (configured in `.env.example`)
- **Schema:** No schema defined yet. Expect standard Laravel auth tables (`users`, `password_resets`, etc.) plus platform-specific tables for AI sessions, project workspaces, code generation history, etc.

### Required Next Steps for DB
1. Generate Laravel migrations for `users`, `profiles`
2. Add AI-related tables (`conversations`, `code_generations`, `projects`, etc.)
3. Create Eloquent models in `app/Models/`
4. Write seeders and factories for development data

---

## 6. API Flow

### Laravel Backend Routes (`backend/routes/api.php`)

| Method | Endpoint             | Auth        | Description           |
|--------|--------------------|-------------|-----------------------|
| GET    | `/health`          | Public      | Service health check   |
| POST   | `/auth/register`   | Public      | User registration      |
| POST   | `/auth/login`      | Public      | User login             |
| POST   | `/auth/refresh`    | Public      | Refresh JWT token      |
| POST   | `/auth/logout`     | Public      | Logout                 |
| GET    | `/user`            | Sanctum     | Get current user       |
| GET    | `/user/profile`    | Sanctum     | Get user profile       |
| PUT    | `/user/profile`    | Sanctum     | Update user profile   |

> **Note:** `AuthController` and `UserController` are referenced in routes but do **not exist** yet.

### AI Gateway Routes (`ai-gateway/main.py`)

| Method | Endpoint                 | Auth     | Description          |
|--------|--------------------------|----------|----------------------|
| GET    | `/health`                | Public   | Health check         |
| GET    | `/`                      | Public   | Service info         |
| POST   | `/v1/chat/completions`   | (planned)| Chat completion proxy|
| POST   | `/v1/embeddings`         | (planned)| Embeddings proxy     |

### Nginx Proxy Flow

- `/api/*`  → `http://php:9000/api/`
- `/ai/*`   → `http://ai-gateway:8000/`
- `/ws/*`   → `httpIf you:/ws/` (WebSocket upgrade)
n
---

## 7. Authentication Flow

Authentication is **designed** as a dual-system approach. **Neither system is actually implemented yet** (no controllers, no models, no middleware exist).

### Dual Authentication Strategy

| Layer         | Tech               | Use Case                                    |
|---------------|--------------------|---------------------------------------------|
| **APIme API** | Laravel Sanctum    | Cookie-based spa auth for web/app clients   |
| **AI Gateway**| JWT (RS256)        | Inter-service auth, machine-to-machine      |

### Intended Flow

1. User submits credentials to `POST /auth/login` or `POST /auth/register`
2. `AuthController` validates input, creates/retrieves a user record, issues a Sanctum token or session cookie
3. Frontend stores the token in a secure, httpOnly cookie
4. Subsequent API requests include the Sanctum cookie, authenticated via `auth:sanctum` middleware
5. When the Laravel app needs to call the AI Gateway, it signs a JWT with the `RS256` private key
6. AI Gateway verifies the JWT using the public key, ensuring only the Laravel backend can request AI resources
7. `POST /auth/refresh` issues a new set of tokens; `POST /auth/logout` revokes the current session

### Current Gaps
- `AuthController`, `UserController`, `User` model, and `auth:sanctum` guards are not created
- No JWT generation or validation logic exists in PHP
- AI Gateway accepts `jwt_secret` but does not yet verify tokens on its endpoints

---

## 8. Suggested Improvements

### Immediate (Critical for Development)
1. **Fix `main.py` Syntax Error** – Line 107 has a stray `illy` and the `return` statement on line 109 contains invalid escaping (`{\"` instead of `{"`). This will prevent the AI Gateway from starting.
2. **Generate Laravel Core Files** – Run `php artisan make:controller Api/AuthController`, `php artisan make:controller Api/UserController`, `php artisan make:model User -m` to create the referenced classes.
3. **Fix Docker Compose Typo** – `docker-compose.yml` line 40 has `DB_CONNECTION: pgsql implícita` instead of `DB_CONNECTION: pgsql`.
4. **Generate Laravel App Keys** – Run `php artisan key:generate` and store the key in the `.env` file.
5. **Add `APP_KEY` to `.env.example`** and document key generation in the README.

### Short-Term (Needed for First Release)
6. **Implement AI Gateway Logic** – Add actual OpenAI/Anthropic API routing, request/response transformation, rate-limiting via Redis, and usage tracking.
7. **Add Database Migrations** – Start with `users`, `profiles`, `conversations`, `code_generations`.
8. **Build Frontend Scaffolding** – Initialize `frontend/landing` and `frontend/console` (e.g., with Vite + React/Vue/Svelte).
9. **Add CI/CD Pipeline** – Define GitHub Actions workflows for `backend` (PHPUnit, Pint) and `ai-gateway` (pytest, black, mypy).
10. **Add `AGENTS.md`** – Document the project's coding conventions, commit style, and AI agent workflows so that future agents know how to interact with the codebase.

### Medium-Term (Production Readiness)
11. **SSL Certificate Management** – Automate certificate provisioning (e.g., Let's Encrypt via Certbot) instead of manual `.crt` / `.key` files.
12. **Observability** – Integrate the already-installed Prometheus and Sentry on both the PHP and Python services.
13. **Multi-Environment Configuration** – Separate `docker-compose.yml`, `.env.example`, and Nginx configs for `development`, `staging`, and `production`.
14. **Kubernetes & Terraform** – The `infra/` directory is empty. Add Helm charts or raw K8s manifests, and Terraform modules for cloud infrastructure (Supabase, Redis, Container Registry).
15. **Documentation** – Generate OpenAPI/Swagger specs for the AI Gateway and Laravel API.
16. **Health Checks & Graceful Shutdowns** – Add proper `HEALTHCHECK` instructions in Dockerfiles and signal handling in the FastAPI/Laravel apps.

---

*This summary was generated based on the current state of the `/workspaces/corex` repository.*
