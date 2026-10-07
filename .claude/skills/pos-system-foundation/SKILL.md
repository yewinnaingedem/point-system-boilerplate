---
name: pos-system-foundation
description: How the POS System skeleton was built and how its parts fit — Laravel 12 + nwidart modules, staff login, spatie roles/permissions (Access Management), DB-backed app settings, AdminLTE 3 admin UI with the module-driven sidebar (MenuRegistry/MenuGroup), the Sanctum token API, and the daily-log viewer — plus the step-by-step recipe for adding a new POS module and the traps already hit. Use when adding a module or screen, touching the sidebar, permissions, settings, API, logs or the AdminLTE theme, or when something in that foundation looks broken.
---

# POS System foundation

A point-of-sale **skeleton**: the architecture of the Shwemandalar Cargo system (modules, roles,
settings, `admin` area, mobile API, log viewer) rebuilt on Laravel 12 with **no business features**.
Sales, products, stock etc. are to be added as new modules. `AGENTS.md` is the short map; this
file is the detail and the history.

## How it was built (2026-10-07)

| Step | Result | Why this way |
|---|---|---|
| 1. Skeleton | Laravel 12.69, PHP 8.4, SQLite local | fresh project, not an in-place upgrade of the 5.4 cargo code |
| 2. Modules | `nwidart/laravel-modules` v12: Access, AppSetting, Dashboard | same module boundaries as cargo |
| 3. Access | `spatie/laravel-permission` v6 instead of the old boilerplate access layer | maintained, idempotent, Gate-integrated |
| 4. Settings | `settings` table + `SettingCatalog` | cargo rewrote `.env` and re-cached config on every save |
| 5. UI v1 | Tailwind 4 + Alpine (replaced) | — |
| 6. UI v2 | **AdminLTE 3.2** (Bootstrap 4.6, jQuery, Font Awesome 5) | the user asked for the AdminLTE 3 look |
| 7. LogViewer | own module in AdminLTE style | cargo used arcanedev/log-viewer, which has its own UI |
| 8. Api | Sanctum tokens at `/api/v1` | cargo's JWT API exposed unauthenticated endpoints |
| 9. Menu groups | `MenuGroup` treeview; Users/Roles/Permissions under **Access Management** | user request, AdminLTE style |
| 10. Daily logs | `LOG_STACK=daily`, viewer lists only `laravel-YYYY-MM-DD.log` | match cargo (30 days) |
| 11. User features | Login as, view page, change password, clear sessions, Deactivated/Deleted tabs, soft delete + restore | parity with the cargo boilerplate (skipped: email confirmation, social unlink, agent upgrade — not used in POS) |
| 12. Docker + MySQL | `compose.yaml` (app, web:8080, queue, scheduler, mysql:3308, redis:6380); SQLite data moved with `db:import-sqlite` | run like production, next to the cargo containers |

## Running and checking

Docker (preferred): `docker compose up -d --build`, app at http://localhost:8080,
`docker compose exec app php artisan …`. Details and ports: AGENTS.md "Running it (Docker)".

On the host, `php` is 7.4 — use 8.4 explicitly (it reaches the Docker MySQL/Redis through `.env`):

```bash
P=/opt/homebrew/opt/php@8.4/bin/php
$P artisan test                           # tests/ + Modules/*/tests, in-memory SQLite (70 tests)
$P vendor/bin/pint                        # style
$P /usr/local/bin/composer require ...    # composer must run under 8.4 too
npm run build                             # after any CSS/JS or new Blade classes
$P artisan module:seed <Module>           # after adding permissions (idempotent)
```

Test logging is **not** isolated: errors in tests and in `artisan tinker` are written to the real
`storage/logs`. Check the `local.` / `testing.` env in an entry before chasing it.

## The pieces

### Modules and routes
- Each module: `Modules/<Name>/app/...`, `routes/web.php`, `database/{migrations,seeders}`,
  `resources/views` (`<alias>::view`), `config/config.php` (read as `config('<alias>.key')`), `tests/`.
- Admin routes: `Route::middleware('admin')->prefix('admin/...')->name('admin....')` with one
  `->middleware('permission:<action>-<resource>')` per route. `admin` = `auth` + `active`
  (`bootstrap/app.php`).
- `module:make` also generates per-module Vite/assets/api stubs we don't use; delete them (recipe below).

### Access (`Modules/Access`)
- Permissions are `<action>-<resource>`. Every module registers its own via a seeder extending
  `App\Support\Access\ModulePermissionSeeder` (`return ['product' => self::CRUD]`). `db:seed`
  discovers every enabled module's `<Name>DatabaseSeeder` (`database/seeders/DatabaseSeeder.php`).
- `Administrator` holds every permission (`Gate::before` in `AppServiceProvider`) — but only for
  checks **without arguments**. Policy checks on a record still run for admins; otherwise "not
  yourself" / "must be active" rules are silently skipped and buttons show where they shouldn't. System roles
  (`app/Enums/SystemRole.php`) can't be renamed/deleted. `RoleSeeder` grants starting permissions
  **only when a role is created**, so re-seeding never undoes UI changes.
- Guards in `UserService`: can't delete/deactivate yourself; at least one active administrator
  must remain (checked inside the transaction, so the change rolls back). Only an administrator can
  give the Administrator role (`UserRequest` rule) or edit/delete an administrator (`UserPolicy`).
- Deactivate, delete or password change ⇒ API tokens deleted, browser sessions ended
  (`UserSessionService`: DB sessions, keeps the current browser, cycles `remember_token`).
- Delete = soft delete (`{deletedUser}` route binding = `onlyTrashed`); restore keeps roles;
  permanent delete removes roles (HasRoles) and avatar.
- Login as: `ImpersonationService` + `ImpersonationController`. Session keys `impersonator_id`,
  `impersonator_name`; `RecordLastLogin` skips while the key is set; banner via a view composer on
  `components.layouts.admin` (Access provider); `POST admin/access/impersonate/leave` has no
  permission check on purpose (the borrowed account lacks it). Logged with `Log::notice`.

### Settings (`Modules/AppSetting`)
- Add a setting = add a `SettingField` to `SettingCatalog`. Form, validation, default all follow.
- Read: `setting('key')`, `setting()->url('logo')`, `money($amount)` (`app/helpers.php` of the module).
- One cache key `appsetting.values`, flushed on save. `SettingService` is bound **scoped** (fresh per
  request/job). `app_name` and `timezone` override config at boot.
- `primary_color` → CSS `--brand` (validated `#rrggbb`, safe to print in `<style>`).

### AdminLTE UI
- Shell: `resources/views/components/layouts/admin.blade.php`
  → `<x-layouts.admin :title="..." :breadcrumbs="[label => url]">`. Partials: `head`, `navbar`,
  `sidebar`, `sidebar-link`, `flash`, `dark-mode-init`. Components: `avatar`, `confirm-delete`.
- JS (`resources/js/app.js`): `jquery-global.js` **must be imported first** (Bootstrap 4 and
  AdminLTE are jQuery plugins), then `bootstrap`, `admin-lte`, then `ui/*`:
  `dark-mode.js` (body `.dark-mode` + localStorage), `confirm-delete.js` (one shared modal,
  `data-confirm-delete="url"`), `forms.js` (`custom-file` label, `data-toggle-password`,
  `data-toggle-all`, colour sync, `data-autosubmit`, alert auto-close).
- `resources/css/app.css` imports AdminLTE + Font Awesome and re-points AdminLTE "primary" to
  `--brand`. A new AdminLTE primary component that stays blue needs its selector added there.
- Pagination is Bootstrap 4 (`Paginator::useBootstrapFour()`).

### Sidebar (`app/Support/Menu`)
- Modules register in their provider `boot()`; the layout never lists modules.
- `MenuItem(label, route, 'fas fa-…', section, permission, order, activePattern, parent)`.
- `MenuGroup(key, label, icon, section, order)` + items with `parent: key` → AdminLTE treeview.
  Shown only if one child is visible; opens itself (`menu-open`) on a child's page; an item whose
  parent was never registered is shown alone rather than lost.
- Look: parent active = brand blue; active child = AdminLTE's light pill; no `nav-child-indent`
  (user wanted children on the same left edge).

### Api (`Modules/Api`)
- Sanctum personal access tokens (hashed in `personal_access_tokens`). `POST /api/v1/auth/login`
  (`login` = email or phone, `password`, `device_name`) is the **only** unauthenticated route.
  Authenticated group: `auth:sanctum`, `EnsureTokenUserIsActive`, `throttle:api`.
- `ApiTokenService`: one token per device name, max `API_MAX_DEVICES` (oldest by
  `coalesce(last_used_at, created_at)` then `id` dropped), TTL `API_TOKEN_TTL_DAYS`, refresh = new
  token + delete current. Daily `sanctum:prune-expired` in the module schedule.
- `ApiAuthenticator`: same 422 message for unknown / inactive / wrong password, hashes even for
  unknown logins (timing). `users.phone` is unique because it is a login name.
- Hardening: `config/sanctum.php guard => []` (admin browser session can't call the API),
  CORS only `CORS_ALLOWED_ORIGINS` (default `APP_URL`), JSON errors for `api/*`
  (`shouldRenderJsonWhen` in `bootstrap/app.php`), responses only via Resources with listed fields.
- Admin screen **API Tokens** (`view/delete-apitoken`): revoke a device or all of a user's devices.
- New endpoint: add it inside the authenticated group in `Modules/Api/routes/api.php`, gate with
  `permission:…`, return a Resource. Never add a public route besides login.

### LogViewer (`Modules/LogViewer`)
- `LogFileRepository`: only `<prefix>-YYYY-MM-DD.log` (prefix `laravel`); URL names are matched
  against the real listing, so traversal and other files are 404.
- `LogLevelCounter`: streams the whole file, counts headers, cached by `name:size:mtime` fingerprint.
- `LogReader` + `LogParser`: entries of a day, newest first, last `LOG_VIEWER_MAX_BYTES` (5 MB) only.
- Permissions `view/download/delete-logviewer` — Administrator only by default (logs hold PII).

## Recipe: add a POS module (e.g. Product)

1. `$P artisan module:make Product`
2. Delete: `resources/assets`, `vite.config.js`, `package.json`, `resources/views/components`,
   `resources/views/index.blade.php`, `routes/api.php`, the generated `ProductController`, and the
   `mapApiRoutes` call + method in `app/Providers/RouteServiceProvider.php`.
3. Migration in `Modules/Product/database/migrations` (indexes for every filter/sort column).
4. Model, then logic in `app/Services` with constructor injection; FormRequests for validation;
   Policy only for rules a permission can't express.
5. `routes/web.php` as in "Modules and routes"; `ProductDatabaseSeeder extends ModulePermissionSeeder`
   returning `['product' => self::CRUD]`; run `module:seed Product`.
6. Provider `boot()`: `MenuGroup` if there will be several screens, then `MenuItem`s with `parent`.
7. Views: copy the Access users views (card-primary card-outline, table-hover, btn-group btn-sm,
   `x-confirm-delete`, `custom-control`), `npm run build`.
8. Feature tests in `Modules/Product/tests/Feature` using `seedAccess()` / `userWithRole()`; include a
   "role without permission gets 403" test. Add the pages to `tests/Feature/AdminPagesRenderTest.php`.
9. API needed? Endpoints in `Modules/Api/routes/api.php` (authenticated group) + a Resource.
10. Update `AGENTS.md` (module table) and this skill if you learned something non-obvious.

## Traps already hit

| Symptom | Cause | Fix |
|---|---|---|
| `npm install admin-lte` fails: `husky: command not found` | admin-lte's own lifecycle script | a clean `npm install` works; if not, `--ignore-scripts` |
| Bootstrap layout broken under Tailwind | Tailwind preflight resets Bootstrap | don't mix; Tailwind was removed |
| Settings tabs fine but sidebar child same blue as parent | `.nav-pills .nav-link.active` brand rule hit the sidebar | rule scoped to `.content-wrapper` |
| Clickable badges unreadable (blue on red) | content link-colour rule | `:not(.badge)` added; exclude other link-styled components the same way |
| File "last modified" 6.5 h off from entries | Carbon 3 `createFromTimestamp` defaults to UTC | pass `config('app.timezone')` |
| Device limit dropped the wrong token | equal timestamps within one second | tie-break `orderByDesc('id')` |
| API test: refreshed/logged-out token still "works" | Sanctum guard caches the user across requests in one test | `$this->app['auth']->forgetGuards()` between token calls |
| `MenuGroup::url()` error in the log | a page loaded between editing the registry and the sidebar | sidebar handles `MenuGroup`; deploy menu + view together |
| Screenshots: dim sidebar text / sidebar over content | puppeteer `clip` / `fullPage` capture artifacts | screenshot the plain viewport |
| `env()` returns null in seeders | config cache | read through module config (`config('access.admin.email')`) |
| Containers crash-loop: `Call to a member function connect() on null` (RedisManager) | `REDIS_CLIENT` not `predis`/`phpredis` (it was `database`) | `REDIS_CLIENT=predis` |
| `compose up --build`: image "pos-system-php:8.4" already exists | app, queue, scheduler all had `build:` with one tag | only `app` builds; the others use `image:` |
| Queued closure from tinker fails to serialize | closures defined in eval'd code | test the queue with `Artisan::queue('about')` |
| "Login as" button on your own row / inactive users | `Gate::before` returned true for policy checks too | short-cut only argument-less checks |
| Admin changing own password would log themselves out | clearing all of the user's DB sessions | `UserSessionService` keeps the current session id |

## Verifying UI changes

Tests prove pages render; they don't prove they look right. For visual changes: build, start
`artisan serve`, and screenshot with headless Chrome (puppeteer-core against
`/Applications/Google Chrome.app`, kept outside the project), in light **and** dark
(`emulateMediaFeatures prefers-color-scheme`), plus a 390 px wide mobile view. Also check
`pageerror` is empty — a jQuery load-order mistake shows up there, not in PHP tests.
