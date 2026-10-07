# POS System

Point-of-sale system skeleton on Laravel 12: modules, staff sign-in, roles & permissions,
app settings, AdminLTE 3 admin, token API and log viewer. See [AGENTS.md](AGENTS.md) for the
architecture and how to add modules.

```bash
cp .env.example .env            # set DB_PASSWORD, DB_ROOT_PASSWORD, DOCKER_UID/GID
composer install && npm install && npm run build
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan storage:link
```

Open http://localhost:8080 and sign in with `admin@pos.test` / `password`.
