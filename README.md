# POS System

Point-of-sale system on Laravel 12: modules, staff sign-in, roles & permissions, app settings,
AdminLTE 3 admin, token API, log viewer, and the loyalty side (customers, tiers, points with expiry,
merchants, redemptions, gift cards). See [AGENTS.md](AGENTS.md) for the architecture and how to add modules.

PHP 8.4 and Node run on your machine; Docker only runs MySQL and Redis.

```bash
cp .env.example .env     # set DB_PASSWORD, DB_ROOT_PASSWORD
docker compose up -d     # MySQL :3308, Redis :6380 (give MySQL ~20 s on first start)
composer setup           # first run: install, key, migrate --seed, storage:link, npm build
composer dev             # every day after that: server, queue, scheduler, logs, vite
```

Open http://localhost:8000 and sign in with `admin@pos.test` / `password`.

## Sample and performance data

```bash
php artisan merchant:demo-data          # a few demo merchants, customers and redemptions (--remove deletes them)
php artisan perf:seed                   # 100k customers with a year of history, for performance testing
php artisan perf:seed --remove          # delete exactly the performance data
```

`perf:seed` creates, by default (about 3 million rows in ~2 minutes on the Docker MySQL):

| What | Default | Option |
|---|---|---|
| Customers (`perf-1` … `perf-100000`) with purchases, tiers, expiring points, redemptions, gift card exchanges, reversals | 100,000 | `--customers` |
| Staff users (`staffN@perf.pos.test` / `password`, Managers and Cashiers) | 1,000 | `--users` |
| Merchants (5 branches and 4 rewards each) | 200 | `--merchants` |
| Gift card types | 30 | `--gift-cards` |
| Days of history | 365 | `--days` |

Each customer's history is simulated with the app's own rules (tier state machine, point lots, expiry,
soonest-expiring-first spending), so balances, ledger and lots agree, and is written with bulk inserts.
Run it on a quiet database: ids are assigned in PHP. Same `--seed`, same data. It refuses in production
without `--force`.
