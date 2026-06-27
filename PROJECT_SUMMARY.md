# Corex.dev Project Summary

## Overview

Corex.dev is a containerized AI development platform combining a Laravel backend, a Python FastAPI AI gateway, and Nginx reverse proxy infrastructure. The repository includes scaffolding for backend APIs, AI service routing, and deployment workflows, with the intent to support AI-assisted coding, project management, and conversational developer tooling.

## Current Status

- Backend and AI Gateway projects are present and partially configured.
- CI/CD workflows are defined under `.github/workflows/` for backend, AI gateway, frontend, and security scanning.
- The repository includes Docker Compose orchestration and Dockerfiles for PHP, Nginx, and the AI gateway.
- Some application logic is implemented, especially in the AI gateway and route definitions, but many backend controllers, models, migrations, and frontend assets remain incomplete.

## Key Technologies

- Backend: Laravel 11, PHP 8.3+, Laravel Sanctum, Predis, Guzzle
- AI Gateway: Python 3.12, FastAPI, Pydantic, structlog, python-jose, Prometheus
- Database: PostgreSQL 16
- Cache/Queue: Redis 7
- Reverse Proxy: Nginx
- Infrastructure: Docker, Docker Compose, Kubernetes manifests, Terraform placeholders
- CI/CD: GitHub Actions, CodeQL, Trivy, Gitleaks

## Repository Layout

- `backend/` – Laravel application source, routes, configuration, storage, tests, and vendor dependencies
- `ai-gateway/` – FastAPI application source, requirements, Dockerfile, and tests
- `docker/` – Docker build definitions for Nginx and PHP
- `docker-compose.yml` – Local service definitions for `postgres`, `redis`, `php`, `queue`, `scheduler`, `nginx`, and `ai-gateway`
- `frontend/` – intended client app directories for console and landing pages
- `infra/` – Kubernetes and Terraform deployment manifests
- `docs/` – documentation files
- `.github/workflows/` – CI/CD pipeline definitions
- `.env.example` – environment variable template

## Backend Service

- Entry point: `backend/public/index.php`
- CLI: `backend/artisan`
- Primary routes: `backend/routes/api.php`, `backend/routes/web.php`
- API routes include health checks, auth, user profile, projects, conversations, AI usage, and proxy endpoints for `/v1/chat/completions` and `/v1/embeddings`.
- Authentication is expected to use Sanctum for user sessions and JWT middleware for inter-service auth.
- Current backend gaps:
  - referenced controllers such as `AuthController`, `UserController`, `ProjectController`, `ConversationController`, and `AIController` are not present in `backend/app/Http/Controllers/Api/`
  - database migrations and seeders are empty
  - frontend assets are not fully implemented

## AI Gateway Service

- Entry point: `ai-gateway/main.py`
- AI gateway includes:
  - structured logging with `structlog`
  - Sentry initialization
  - health checks for Redis, memory, disk, and uptime
  - agent orchestration with agents such as planner, coder, tester, reviewer, debugger, documentation, and security
  - middleware for CORS, trusted hosts, input validation, sanitization, rate limiting, and security headers
- Dependencies include FastAPI, Uvicorn, HTTPX, Redis, JWT, Prometheus client, and OpenTelemetry.

## Docker Compose Services

- `postgres` – PostgreSQL 16 container
- `redis` – Redis 7 container with password auth
- `php` – Laravel/PHP-FPM application container
- `queue` – Laravel queue worker container
- `scheduler` – Laravel schedule worker container
- `nginx` – Nginx reverse proxy container
- `ai-gateway` – Python FastAPI application container

## CI/CD and Security

- Backend workflow: PHP lint, Composer install, Laravel Pint, PHPUnit tests with coverage
- AI gateway workflow: Python lint, Black, isort, flake8, mypy, pytest with coverage, Docker build/push
- Frontend workflow: ESLint, Prettier, asset build and optional CDN sync
- Security workflow: Dependabot monitor, CodeQL, Trivy container scanning, Gitleaks secret scanning

## Environment and Configuration

- Environment variables are documented in `.env.example`
- Backend expects PostgreSQL, Redis, JWT secrets, Sanctum config, AI gateway URL/key, Sentry, OpenTelemetry, and other deployment settings
- `docker-compose.yml` is configured for local service networking and health checks
- `.env.example` is currently configured for Supabase-style PostgreSQL host values and includes placeholders for keys/secrets

## Known Gaps and Next Steps

- Implement missing API controllers, models, migrations, and seeders in `backend/app/` and `backend/database/`
- Build the frontend console and landing apps
- Complete the AI gateway routing controllers and provider integrations
- Add end-to-end tests for backend, AI gateway, and frontend flows
- Validate Docker Compose startup and service communication in local development
- Ensure Kubernetes and Terraform manifests are complete for production deployment

## Summary

Corex.dev is a promising AI platform foundation that already contains multi-service architecture, containerization, and comprehensive CI/CD scaffolding. The current work is focused on filling in backend and frontend application logic, connecting the AI gateway, and verifying the system end-to-end.
