# POS System

Point-of-sale system skeleton on Laravel 12: modules, staff sign-in, roles & permissions,
app settings, AdminLTE 3 admin, token API and log viewer. See [AGENTS.md](AGENTS.md) for the
architecture and how to add modules.

PHP 8.4 and Node run locally; Docker only runs MySQL and Redis.

```bash
cp .env.example .env     # set DB_PASSWORD, DB_ROOT_PASSWORD
docker compose up -d     # MySQL :3308, Redis :6380 (give MySQL ~20 s on first start)
composer setup           # first run: install, key, migrate --seed, storage:link, npm build
composer dev             # every day after that
```

Open http://localhost:8000 and sign in with `admin@pos.test` / `password`.
