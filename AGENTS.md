# POS System — agent & developer guide

Read this first. It is the map; details live in the code.

## What this is

A point-of-sale system skeleton. It keeps the architecture of the Shwemandalar Cargo system
(modules, roles/permissions, app settings, `admin` area) on a modern stack, with **no business
features yet**: sales, products, inventory etc. are to be built as new modules.

- **Laravel 12**, **PHP 8.2+** (developed on 8.4). Admin UI is **AdminLTE 3.2** (Bootstrap 4.6, jQuery 3,
  Font Awesome 5), bundled with Vite. No Tailwind: its reset breaks Bootstrap.
- `nwidart/laravel-modules` v12 for modules, `spatie/laravel-permission` v6 for roles/permissions.
- Local default DB is SQLite (`database/database.sqlite`); set `DB_*` for MySQL.

## Layout

```
app/
  Enums/SystemRole.php          Administrator / Manager / Cashier (protected roles)
  Http/Controllers/Auth/         sign in / sign out (no public registration)
  Http/Controllers/ProfileController.php   own profile + password
  Http/Middleware/EnsureUserIsActive.php   kicks out users deactivated mid-session
  Listeners/RecordLastLogin.php  users.last_login_at
  Support/Access/ModulePermissionSeeder.php  base seeder every module extends
  Support/Menu/                  MenuItem, MenuGroup (collapsible), MenuRegistry (sidebar, filled by modules)
Modules/<Name>/
  app/Http/Controllers, app/Http/Requests, app/Services, app/Support, app/Policies, app/Models
  app/Providers/<Name>ServiceProvider.php   bindings, menu items, policies
  routes/web.php                prefix `admin`, name `admin.`, middleware `admin`
  database/migrations, database/seeders/<Name>DatabaseSeeder.php (permissions)
  resources/views               referenced as `<alias>::view`
  tests/Feature
resources/views/
  components/layouts/admin.blade.php   AdminLTE shell: <x-layouts.admin :title="..." :breadcrumbs="[label => url]">
                                       (navbar, sidebar, content-header + breadcrumb, footer, shared delete modal)
  components/ avatar, confirm-delete
  partials/   head, navbar, sidebar, flash, dark-mode-init
  auth/login.blade.php (AdminLTE login-box), profile/edit.blade.php
resources/css/app.css            imports AdminLTE + Font Awesome, re-points AdminLTE "primary" to --brand
resources/js/app.js              jQuery global → Bootstrap → AdminLTE, then ui/ helpers:
  ui/dark-mode.js, ui/confirm-delete.js, ui/forms.js (file input label, password eye, toggle-all, colour sync, autosubmit)
```

## Modules

| Module | What it does |
|---|---|
| Dashboard | `/admin/dashboard`, landing page for everyone. Overview widgets need `view-dashboard`. |
| Access | Users: list with All / Active / Deactivated / Deleted tabs, view page (effective permissions, browser sessions, app devices), create/edit, change password, clear sessions, activate/deactivate, **Login as** (`impersonate-user`), soft delete → restore / delete permanently. Roles (permission matrix), permissions (read-only list). |
| AppSetting | Settings stored in the `settings` table, edited at `/admin/settings/{group}`. |
| Api | Token API for the POS/mobile apps at `/api/v1` (Sanctum), plus the admin **API Tokens** screen (`view/delete-apitoken`). See "API" below. |
| LogViewer | `/admin/logs`: **daily logs only** (`laravel-YYYY-MM-DD.log`, one row per day with per-level counts, totals, like the old arcanedev viewer). Click a day for its entries: level filter, search, stack traces; download / delete. Permissions `view/download/delete-logviewer` (Administrator only). Counts are cached per file (name+size+mtime). Reads at most the newest `LOG_VIEWER_MAX_BYTES` (5 MB) of a file. Only files matching the daily pattern can be opened. |

### Adding a module

```bash
php artisan module:make Product
```

Then, following Access/AppSetting:

1. Delete what the generator adds that we don't use: `resources/assets`, `vite.config.js`,
   `package.json`, `resources/views/components`, `routes/api.php` (and `mapApiRoutes` in the
   module's `RouteServiceProvider`). All CSS/JS is built once by the root Vite config, which
   already scans `Modules/*/resources/views`.
2. Routes in `routes/web.php`: `Route::middleware('admin')->prefix('admin')->name('admin.')`,
   one `->middleware('permission:<action>-<resource>')` per route.
3. Permissions: make `<Name>DatabaseSeeder` extend `App\Support\Access\ModulePermissionSeeder`
   and return e.g. `['product' => self::CRUD]`. Run `php artisan module:seed Product` (idempotent),
   then grant on the Roles screen. `php artisan db:seed` seeds every enabled module automatically.
4. Sidebar: in the provider's `boot()`,
   `$this->app->make(MenuRegistry::class)->add(new MenuItem('Products', 'admin.products.index', 'cube', 'Inventory', 'view-product'))`.
   Several related links? Register a collapsible group and point the items at it:
   `->addGroup(new MenuGroup('inventory', 'Inventory', 'fas fa-boxes', 'Inventory', 10))` then
   `new MenuItem(..., permission: 'view-product', order: 10, parent: 'inventory')`. A group shows only
   if the user can see one of its items and opens itself on their pages (Access Management is the example).
   Sections (sidebar `nav-header`s): Main, Sales, Inventory, Reports, Administration.
   Icons are Font Awesome 5 classes (`fas fa-box`).
5. Views: `<x-layouts.admin :title="..." :breadcrumbs="[__('Products') => route(...)]">` and plain
   AdminLTE markup: `card card-primary card-outline`, `small-box`, `info-box`, `table table-hover`,
   `custom-control` checkboxes/switches, `btn btn-sm`. Copy from the Access views. Delete buttons:
   `<x-confirm-delete :action="route(...)" :title="..." />` (opens the shared modal).
   Flash success with `->with('success', ...)`. Reference: https://adminlte.io/themes/v3/

## Access rules

- Permission names are `<action>-<resource>` (`view-user`, `edit-appsetting`). The role screen
  groups by the resource part.
- **Administrator holds every permission** (`Gate::before` in `AppServiceProvider`), so it never needs
  permissions granted and can't be locked out of a new module. The shortcut applies only to plain
  permission checks; policy checks on a record (`can('impersonate', $user)`) still run for admins.
- **Login as** (`ImpersonationService`): the real admin's id stays in the session, permission checks
  use the real admin, start/end are logged, the target's `last_login_at` isn't touched, and a banner
  with "Return to …" shows on every page. Not for yourself, inactive users, or (for non-admins) admins.
- Deleting a user is a **soft delete** (`users.deleted_at`): they can't sign in, keep their roles,
  and can be restored. Deactivate, delete and password change end their browser sessions
  (`UserSessionService`, needs `SESSION_DRIVER=database`) and API tokens.
- System roles (`SystemRole`) can't be renamed or deleted; a role still assigned to users can't be deleted.
- Only an administrator can give the Administrator role or edit/delete an administrator
  (`UserRequest` + `UserPolicy`); otherwise a Manager with `edit-user` could take over an admin account.
- At least one active administrator must remain; you can't deactivate or delete yourself (`UserService`).
- Starting grants (`RoleSeeder`): Manager → dashboard, view/create/edit users, view settings;
  Cashier → dashboard. They are applied only when the role is first created, so re-seeding
  never overwrites changes made in the UI.

## Settings

- Read with `setting('company_name')`, image URLs with `setting()->url('logo')`, amounts with
  `money($amount)` (currency symbol + position). Helpers: `Modules/AppSetting/app/helpers.php`.
- **To add a setting, add a `SettingField` in `SettingCatalog`.** The form, validation and default
  come from there; nothing else to edit. A new group = a new tab.
- Values are cached forever under one key and flushed on save. After editing the `settings` table
  by hand: `php artisan cache:forget appsetting.values`.
- `app_name` and `timezone` override `config('app.*')` at boot. `primary_color` (default AdminLTE
  blue `#007bff`) is printed into CSS as `--brand` (validated `#rrggbb`); `app.css` makes
  `btn-primary`, `bg-primary`, active sidebar/pills, `card-primary`, progress bars etc. use it.
  If you use a new AdminLTE "primary" component and it stays blue, add its selector there.
- Unlike the old cargo system, saving settings never rewrites `.env`.

## API (`Modules/Api`)

Laravel Sanctum personal access tokens: stored **hashed** in `personal_access_tokens`, so deleting
a row revokes the token on the next request. This replaces the old JWT + `user_tokens` +
`check-token-in-db` setup.

| Method | Path | Auth | |
|---|---|---|---|
| POST | `/api/v1/auth/login` | none, `throttle:api-login` (5/min per login+IP) | `login` (email or phone), `password`, `device_name` → `data.token` |
| POST | `/api/v1/auth/refresh` | token | new token for this device, old one revoked |
| POST | `/api/v1/auth/logout` / `logout-all` | token | revoke this token / all of the user's tokens |
| GET | `/api/v1/me` | token | user, roles, permission names (for showing/hiding app screens) |
| GET | `/api/v1/settings` | token | business, currency, tax and receipt settings |

Send `Authorization: Bearer <token>`. Errors are always JSON (401 no/expired/revoked token,
403 missing permission, 422 validation, 429 rate limit).

**Rules, and what is deliberately *not* exposed (unlike the old cargo API):**
- Sign-in is the **only** route without a token: no public signup, lookups or print endpoints.
  New endpoints go in the authenticated group in `Modules/Api/routes/api.php` and get a
  `permission:<action>-<resource>` middleware, like the admin routes.
- Responses are API Resources with explicit fields (`UserResource`, `SettingsResource`), never a raw model.
- Wrong password, unknown user and inactive user all get the same 422 message.
- `config/sanctum.php` `guard => []`: the admin's browser session can't call the API.
  CORS only allows `CORS_ALLOWED_ORIGINS` (default `APP_URL`); native apps don't need CORS.
- Tokens expire after `API_TOKEN_TTL_DAYS` (30). Each device name keeps one token, at most
  `API_MAX_DEVICES` (3) per user; a new device pushes out the least recently used.
  `sanctum:prune-expired` runs daily (module schedule).
- Deactivating a user, deleting them, or changing their password (admin or own profile) revokes all
  their tokens. A user deactivated directly in SQL is cut off on their next request
  (`EnsureTokenUserIsActive`).
- `users.phone` is unique (it is a login name).
- Testing tip: in feature tests call `$this->app['auth']->forgetGuards()` between requests with
  different tokens; Sanctum's guard caches the user inside one test (real requests don't).

## Running it (Docker)

`compose.yaml` runs the whole stack; ports avoid the cargo project's containers (3307 / 6379).

| Service | What | Host port |
|---|---|---|
| `web` | nginx → `public/` | **8080** (`APP_PORT`) |
| `app` | PHP 8.4-FPM (`docker/php/Dockerfile`), the project is bind-mounted | — |
| `queue` | `queue:work redis` | — |
| `scheduler` | `schedule:work` (e.g. `sanctum:prune-expired`) | — |
| `mysql` | MySQL 8.4, db `pos_system`, volume `mysql-data` | **3308** (`FORWARD_DB_PORT`) |
| `redis` | Redis 7 (cache + queue), volume `redis-data` | **6380** (`FORWARD_REDIS_PORT`) |

```bash
cp .env.example .env               # set DB_PASSWORD / DB_ROOT_PASSWORD, DOCKER_UID/GID = `id -u` / `id -g`
composer install && npm install && npm run build
docker compose up -d --build       # http://localhost:8080
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan storage:link
```

- `.env` holds the **host** view (`DB_HOST=127.0.0.1`, `DB_PORT=3308`, `REDIS_PORT=6380`); compose
  overrides them inside the containers (`mysql:3306`, `redis:6379`). So `php artisan …` works both on
  the host (PHP 8.4) and via `docker compose exec app php artisan …`. **Don't `config:cache` in
  development**: it would freeze one set of hosts for both.
- Redis client is **predis** (`REDIS_CLIENT=predis`): pure PHP, works without the `redis` extension.
  Only `phpredis` or `predis` are valid values.
- Sessions stay in the database (`SESSION_DRIVER=database`), because the user screen lists and clears them.
- After changing queued code: `docker compose restart queue`.
- Default sign-in: `admin@pos.test` / `password`. Change it after first sign-in.

**Data moved from SQLite (2026-10-07).** The app first ran on `database/database.sqlite`. Its data was
copied into MySQL with `php artisan db:import-sqlite` (migrate the target first; copies every table
except framework scratch tables, keeps ids, one transaction, replaces target rows). Exports live in
`database/exports/` (git-ignored: they contain password hashes):
`pos_system_sqlite_*.sqlite` / `.sql` (before) and `pos_system_mysql_*.sql` (after). Restore a MySQL
export with `docker compose exec -T mysql mysql -u pos_user -p pos_system < database/exports/<file>.sql`.

## Testing

```bash
php artisan test                 # tests/Feature + Modules/*/tests, in-memory SQLite
vendor/bin/pint                  # code style
```

`Tests\TestCase::seedAccess()` seeds permissions, roles and the admin; `userWithRole()` makes a user.

## Code conventions

- OOP first: logic in Services/Support classes, collaborators injected through the constructor,
  named constants instead of magic values. Controllers stay thin; Blade has no queries.
- Validation and authorization in FormRequests; extra ownership rules in Policies.
- Multi-row writes in `DB::transaction`. Search with prefix `LIKE 'abc%'`, not `%abc%`.
- Strings: `"User {$name}"`, not concatenation. Blade: `{{ }}`; `{!! !!}` only for trusted HTML.

## Skills

Detailed knowledge lives in `.claude/skills/` (Claude Code loads these automatically; other agents
can read the `SKILL.md` files directly):

| Skill | Use when |
|---|---|
| `pos-system-foundation` | adding a module or screen; touching the sidebar, permissions, settings, API, logs or AdminLTE theme; how the skeleton was built and the traps already hit |

When you learn something non-obvious that the next person needs, add it here (short) or in the skill
(detailed). Keep this file a map, not a manual.
