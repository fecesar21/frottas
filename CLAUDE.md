# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Health Drive / FleetCore** — a Laravel 11 REST API for fleet management (vehicles, drivers, schedules, trips, refueling, shift handover, and odometer tracking). Pure API backend; no Blade views.

## Commands

```bash
# Start all services (server + queue + logs + Vite hot-reload)
composer dev

# Serve only the API
php artisan serve

# Run migrations
php artisan migrate

# Run all tests
php artisan test
# or
./vendor/bin/phpunit

# Run a single test file
php artisan test tests/Feature/ExampleTest.php

# Code formatting (Pint)
./vendor/bin/pint

# Interactive REPL
php artisan tinker

# First-time server provisioning (root, once): Nginx, HTTPS, Supervisor queue worker, cron scheduler
sudo DOMAIN=api.healthdrive.com.br CERTBOT_EMAIL=admin@healthdrive.com.br bash scripts/provision-server.sh

# Initial production install (one-time app setup: .env, key:generate, first build, permissions)
bash scripts/install.sh

# Deploy to production (pulls, installs deps, builds frontend, migrates, caches, restarts queue workers)
bash scripts/deploy.sh
```

> **Important:** the frontend is built by Vite into `public/build/` and served via the `@vite` Blade directive — it is NOT served from source. A `git pull` alone does **not** update what the browser loads; `npm run build` must be run afterwards (handled automatically by `scripts/deploy.sh`), or frontend changes will silently not take effect in production.

> **Queue workers:** production uses `QUEUE_CONNECTION=redis` with a Supervisor-managed `queue:work` process. `scripts/deploy.sh` runs `php artisan queue:restart` at the end so workers pick up new code after each deploy — Supervisor's `autorestart` then respawns them.

## Architecture

### Authentication
Sanctum token-based auth. Tokens expire in 8 hours. Login accepts `usuario` (username) **or** `email` as the login field. The auth model is `App\Models\Usuario` (not the default `User`), stored in table `usuarios` with `senha_hash` as the password column.

### Authorization
Two middleware levels:
- `auth:sanctum` — required on all protected routes
- `admin` (`App\Http\Middleware\SomenteAdmin`) — required for `usuarios` CRUD

### Models & Primary Keys
All models use **UUID primary keys** via `HasUuids` trait (`$incrementing = false`, `$keyType = 'string'`). Key models:

| Model | Table | Notes |
|---|---|---|
| `Usuario` | `usuarios` | Extends `Authenticatable`, has `HasApiTokens` |
| `Motorista` | `motoristas` | Has `diasParaVencerCnh` computed attribute |
| `Veiculo` | `veiculos` | |
| `Escala` | `escalas` | Schedules / shift assignments |
| `Checkin` | `checkins` | Driver check-in/check-out |
| `PassagemPlantao` | `plantao` | Shift handover with checklist |
| `Viagem` | `viagens` | Trips with departure/arrival |
| `Abastecimento` | `abastecimentos` | Fuel records |
| `KmRegistro` | `km_registros` | Odometer readings |

### API Routes
All routes are under `/api` prefix (standard Laravel). Grouped structure in `routes/api.php`:
- `POST /api/auth/login` — public
- `GET /api/health` — public health check
- All other routes require `auth:sanctum`

Controllers live in `app/Http/Controllers/Api/`. There is a dead `UsuarioController.old` / `RelatorioController.old` — these are superseded files, not active.

### Database
Uses SQLite (`database/database.sqlite`) by default. Tests also use SQLite (in-memory mode is commented out in `phpunit.xml`, so tests run against the file DB unless reconfigured).

### Frontend Assets
Vite + Tailwind are configured (`vite.config.js`, `tailwind.config.js`) but unused — this project is API-only. `package.json` / `postcss.config.js` are boilerplate skeleton files.
