---
name: pos-system-foundation
description: How the POS System was built and how its parts fit — Laravel 12 + nwidart modules, staff login, spatie roles/permissions (Access Management), DB-backed app settings, AdminLTE 3 admin UI (module-driven sidebar, server-side DataTables lists, toasts, remembered UI state), the Sanctum token API for staff and customers, customer SSO from the partner Laravel project, the loyalty tier engine and points wallet, merchants with branch codes and redemptions, and the daily-log viewer — plus the recipe for adding a module and every trap already hit. Use when adding a module or screen, touching the sidebar, lists, permissions, settings, API, customers, loyalty, merchants, logs or the AdminLTE theme, or when something in that foundation looks broken.
---

# POS System foundation

A point-of-sale system on the architecture of the Shwemandalar Cargo system (modules, roles,
settings, `admin` area, mobile API, log viewer), rebuilt on Laravel 12. Built so far: the foundation
and the loyalty side (customers, tiers, points, merchants, redemptions). Not built yet: Sales and
Settlement. `AGENTS.md` is the short map; this file is the detail and the history.

## How it was built

### 2026-10-07: foundation

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

### 2026-10-08: loyalty side and UI

| Step | Result | Why this way |
|---|---|---|
| 13. Loyalty tiers | `Modules/Loyalty`: `TierQualificationEngine` + pure `TierStateMachine`, per-cycle spending aggregates, guarantee, hourly `loyalty:evaluate-tiers`, tier config in `loyalty_tiers`, cycle length as a setting | spec: current-cycle spending only, immediate upgrade, guarantee protection, O(1) aggregates, row locks |
| 14. DataTables | `yajra/laravel-datatables-oracle` + `datatables.net-bs4` v3; `<x-datatable>`, `DataTableRequest`, `app/Tables/*Table` | user asked for yajra; kept prefix search, sort whitelist, Blade cells for `@can` |
| 15. Loading row | AWS-console style spinner row instead of DataTables' box | user request |
| 16. Tier screen v2 | tier `color` column, DataTable list, per-tier edit page, collapsible "How tiers work" card | user request; inline-edit inside an AJAX table loses old input/errors |
| 17. Remembered UI | localStorage for sidebar collapse, open groups, `data-remember-card`; inline scripts apply before paint | user request; AdminLTE's own `enableRemember` flickers |
| 18. Toasts | flash messages as top-right toasts with a countdown bar (`partials/toasts`, `ui/toasts.js`) | user request; replaces the in-page alert |
| 19. Points + merchants | `PointWallet` (balance + append-only ledger); `Modules/Merchant`: merchants → branches (6-digit code) → rewards, redemptions with payout snapshot, reversal, `merchant:demo-data` | user: merchants where points are spent, branch staff confirm with a code, later pay merchants |
| 20. Diagrams | `docs/diagrams/pos-system-map.tldraw` (tldraw offline): module map, two flows, module trees | user request |
| 21. Sidebar sections | one `<ul>` per section with a divider; each needs its own `id` | user split the list; the shared-handler bug followed |
| 22. Customers | `Modules/Customer`: customers separate from staff users, JWT SSO from the partner project, own Sanctum tokens, tier history API; loyalty tables moved `user_id` → `customer_id` | user: customers are API users from another Laravel project with auto-login |
| 23. Point expiry + summary | point lots with expiry (setting months + cutoff day 15), FIFO-by-expiry spending with lot usages, `restore()` for reversals, daily `loyalty:expire-points`, per-customer monthly `loyalty_point_summaries`, Points Summary screen, `PointStatement` | user: points expire N months by the 15th rule; spending uses the right points; a summary table for checking points |
| 25. Points Activity | all customers' point movements and "who earned" per period (today default), type / customer / reference filters; date index on the ledger | user: no place to see incoming points per day or customer |
| 26. Gift cards | `Modules/GiftCard`: card types with min tier, stock, per-customer limit, validity, optional emailed 2-step code; locked issuing; admin cancel = refund + restock; customer API with `reason` codes | user: exchange points for gift cards with tier limits, out of stock, max attempts, optional 2FA |
| 24. Partner API | `Modules/Partner`: `POST /api/v1/partner/points` (bearer key, idempotent `reference`, creates customers, optional tier spending), customer points lookup; `CustomerDirectory` shared with SSO | user: points are assigned over the API by the other project |

## Running and checking

Docker (preferred): `docker compose up -d --build`, app at http://localhost:8080 (`admin@pos.test` /
`password`), `docker compose exec app php artisan …`. Details and ports: AGENTS.md "Running it (Docker)".

On the host, `php` is 7.4 — use 8.4 explicitly (it reaches the Docker MySQL/Redis through `.env`):

```bash
P=/opt/homebrew/opt/php@8.4/bin/php
$P artisan test                           # tests/ + Modules/*/tests, in-memory SQLite (160 tests)
$P vendor/bin/pint                        # style
$P /usr/local/bin/composer require ...    # composer must run under 8.4 too
npm run build                             # after any CSS/JS or new Blade classes
$P artisan module:seed <Module>           # after adding permissions (idempotent)
docker compose exec app php artisan merchant:demo-data [--remove]   # sample customers, merchants, redemptions
```

- zsh doesn't word-split `$files`: pipe file lists through `xargs` (`grep -rl … | xargs sed -i ''`).
- Test logging is **not** isolated: errors in tests and in `artisan tinker` are written to the real
  `storage/logs`. Check the `local.` / `testing.` env in an entry before chasing it.
- Concurrency (row locks, deadlocks, double spending) can't be tested in SQLite. Race it on MySQL: a small
  PHP script that boots the app (`require vendor/autoload.php; $app = require bootstrap/app.php;` + console
  kernel `bootstrap()`), run N copies in parallel with `&` and `wait`, then compare totals; create and delete
  its own test rows. Done for tier enrolment (20 first sales) and redemptions (10 on a 500-point balance).

## The pieces

### Modules and routes
- Each module: `Modules/<Name>/app/...`, `routes/web.php`, `database/{migrations,seeders,factories}`,
  `resources/views` (`<alias>::view`), `config/config.php` (read as `config('<alias>.key')`), `tests/`.
- Admin routes: `Route::middleware('admin')->prefix('admin/...')->name('admin....')` with one
  `->middleware('permission:<action>-<resource>')` per route. `admin` = `auth` + `active`
  (`bootstrap/app.php`). Implicit binding matches by parameter name (`{customer}` ↔ `Customer $customer`).
- Commands: provider `protected array $commands = [...]`; schedules in `configureSchedules(Schedule $s)`.
- Migrations run in filename order across all modules: a migration that needs another module's table
  must sort after it (Customer's `2026_10_08_2000xx` after Loyalty's and Merchant's).
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
- Users list is a DataTable (`UsersTable`, `UserTableRequest`, `users/cells/*`, route `users/data`): the
  reference implementation for every list.

### Settings (`Modules/AppSetting`)
- Add a setting = add a `SettingField` to `SettingCatalog`. Form, validation, default all follow.
  The **Loyalty** tab (`loyalty_cycle_months`: 1, 2, 3, 6, 12) lives there too.
- Read: `setting('key')`, `setting()->url('logo')`, `money($amount)` (`app/helpers.php` of the module).
- One cache key `appsetting.values`, flushed on save. `SettingService` is bound **scoped** (fresh per
  request/job). `app_name` and `timezone` override config at boot.
- `primary_color` → CSS `--brand` (validated `#rrggbb`, safe to print in `<style>`).

### AdminLTE UI
- Shell: `resources/views/components/layouts/admin.blade.php`
  → `<x-layouts.admin :title="..." :breadcrumbs="[label => url]">` (the title is appended to the
  breadcrumb, so don't list the current page in `breadcrumbs`). Partials: `head`, `navbar`, `sidebar`,
  `sidebar-link`, `toasts` + `toast`, `dark-mode-init`, `sidebar-state-init`, `ui-state-restore`.
  Components: `avatar` (staff), `confirm-delete`, `datatable`. Customers use `customer::partials.avatar` (initials).
- JS (`resources/js/app.js`): `jquery-global.js` **must be imported first** (Bootstrap 4 and
  AdminLTE are jQuery plugins), then `bootstrap`, `admin-lte`, then `ui/*`:
  `dark-mode.js`, `confirm-delete.js` (one shared modal, `data-confirm-delete="url"`), `forms.js`
  (`custom-file` label, `data-toggle-password`, `data-toggle-all`, colour sync, `data-autosubmit`,
  `form[data-confirm]` → `confirm()`), `datatables.js`, `toasts.js`, `ui-state.js`.
- **Toasts:** session keys `success` / `info` / `warning` / `error`, plus non-field error bag keys
  (`user`, `role`). Top-right under the navbar, CSS countdown bar (5 s, 8 s for warning/error), hover pauses,
  × closes; `toast(type, text)` from JS (text only, never HTML).
- **Remembered UI state** (localStorage, this browser only): `sidebar` (collapsed, desktop ≥ 992 px only),
  `sidebar.groups` (open groups; the group of the current page always opens), `cards`
  (`data-remember-card="key"`). `sidebar-state-init` (top of body) and `ui-state-restore` (after the content)
  apply them before paint; `ui-state.js` saves on AdminLTE's `collapsed/shown.lte.pushmenu`,
  `expanded/collapsed.lte.treeview`, `expanded/collapsed.lte.cardwidget` events.
- `resources/css/app.css` imports AdminLTE + Font Awesome + DataTables CSS and re-points AdminLTE "primary"
  to `--brand`. A new AdminLTE primary component that stays blue needs its selector added there.
- Pagination outside DataTables is Bootstrap 4 (`Paginator::useBootstrapFour()`).

### Lists (DataTables)
- `<x-datatable id source columns order filters params empty loading page-length paging>` renders the table;
  `ui/datatables.js` lazy-loads `datatables.net-bs4` + `-responsive-bs4` (own chunk, only on pages with a table).
- Columns: `['data', 'title', 'name' => sort column, 'orderable', 'class', 'priority']`. Everything is
  `searchable: false`; the form's `search` field drives a prefix search in the table class.
- Server: `DataTableRequest` (length 1..100, `searchTerm()`, `filterRules()`), table class with
  `filter()` + `whitelist()` + Blade cells + `rawColumns()` + `only()`. `editColumn` for real columns
  (`addColumn` blacklists the name). Collection engine for tiny fixed lists (tiers).
- Errors: `config/datatables.php error => 'throw'`; JS shows a message row and reloads the page on 401/403/419.
- Tests: `$this->dataTable($url, $columns, $params, $search)`; JSON escapes `/`.

### Sidebar (`app/Support/Menu`)
- Modules register in their provider `boot()`; the layout never lists modules.
- `MenuItem(label, route, 'fas fa-…', section, permission, order, activePattern, parent)`.
- `MenuGroup(key, label, icon, section, order)` + items with `parent: key` → AdminLTE treeview.
  Shown only if one child is visible; opens itself (`menu-open`) on a child's page; an item whose
  parent was never registered is shown alone rather than lost. `<li data-menu-group="key">` for ui-state.
- Markup: one `<ul id="sidebar-section-N" class="nav-sidebar nav-sidebar-section" data-widget="treeview">`
  **per section**, so `app.css` can draw a divider under every section but the last. The `id` is required
  (see traps); `AdminPagesRenderTest` checks it. Code that walks the menu must not assume a single list.
- Look: parent active = brand blue; active child = AdminLTE's light pill; no `nav-child-indent`
  (user wanted children on the same left edge).

### Api (`Modules/Api`)
- Sanctum personal access tokens (hashed in `personal_access_tokens`). Two public routes only:
  staff `POST /api/v1/auth/login` (`login` = email or phone, `password`, `device_name`) and customer
  `POST /api/v1/customer/auth/sso`.
- Staff group: `auth:sanctum`, `EnsureStaffToken`, `EnsureTokenUserIsActive`, `throttle:api`.
  Customer group (`/api/v1/customer`): `auth:sanctum`, `EnsureCustomerToken`, `throttle:api`.
  `throttle:api` keys by `class_basename(user):id`, because staff and customers have separate id sequences.
- `ApiTokenService` (staff): one token per device name, max `API_MAX_DEVICES` (oldest by
  `coalesce(last_used_at, created_at)` then `id` dropped), TTL `API_TOKEN_TTL_DAYS`, refresh = new
  token + delete current. Daily `sanctum:prune-expired` in the module schedule.
- `ApiAuthenticator`: same 422 message for unknown / inactive / wrong password, hashes even for
  unknown logins (timing). `users.phone` is unique because it is a login name.
- Hardening: `config/sanctum.php guard => []` (admin browser session can't call the API),
  CORS only `CORS_ALLOWED_ORIGINS` (default `APP_URL`), JSON errors for `api/*`
  (`shouldRenderJsonWhen` in `bootstrap/app.php`), responses only via Resources with listed fields.
- Admin screen **API Tokens** (`view/delete-apitoken`, DataTable): revoke a device or all of a user's devices.
- New endpoint: staff → staff group + `permission:…`; customer → customer group. Return a Resource.

### Customers (`Modules/Customer`)
- `Customer` (`customers`: external_id unique, name, email, phone, is_active, last_login_at) uses
  `Authenticatable`, `HasApiTokens`, `HasFactory` (`CustomerFactory`). No password.
- `CustomerSsoService::signIn($jwt, $device)`: `verify()` (firebase/php-jwt HS256 with
  `CUSTOMER_SSO_SECRET` ≥ 32 chars; required claims iss, aud, sub, name, iat, exp, jti; issuer and audience
  checked; `exp - iat` ≤ `max_lifetime` 300 s; leeway 30 s; `jti` stored with `Cache::add` until expiry so it
  works once), then upsert by `external_id` under `lockForUpdate`, refuse inactive customers, `enrol()` new
  ones at Silver, issue a Sanctum token (ability `customer`, 90 days, one per device, max 5).
- Every rejection is the same 422 (`errors.token`); the reason goes to `Log::notice`. Sign-in rate limit
  `customer-sso` (30/min per IP: many phones share a mobile-network IP).
- `CustomerTierSummary::for($customer)`: evaluates first, then tier, colour, guarantee, cycle window,
  cycle spending, next tier and what is still needed. Used by `CustomerResource` and the admin page.
- Admin: `CustomersTable` (tier badge, points via `withSum`, filters tier/status), `TierHistoryTable`,
  deactivate (revokes tokens), sign out all devices. Permissions `view/edit-customer`.
- Partner-side code and the API contract: `docs/customer-sso.md`.

### Loyalty (`Modules/Loyalty`)
- `TierLevel` enum (silver, gold, platinum, diamond; rank in code), `TierTransition` enum (enrolled,
  upgraded, requalified, protected, demoted, unchanged).
- Tables: `loyalty_tiers` (threshold, guarantee_months, color), `loyalty_member_statuses` (one row per customer:
  current tier, guarantee, cycle window, `next_evaluation_at`; the lock row), `loyalty_cycle_spendings`
  (aggregate per customer per cycle), `loyalty_tier_events` (history), `loyalty_point_accounts` +
  `loyalty_point_transactions` (wallet). All keyed by `customer_id`.
- `TierQualificationEngine`: `processTransaction($customerId, $amount, $date)`, `evaluateUserTierStatus($customerId, $at)`,
  `enrol($customerId)`. Cycles start on the 1st of the enrolment month (`CycleWindow`, no month-end drift);
  a new cycle starts spending at 0 (no carry-over). Decisions in `TierStateMachine` (pure, unit-tested).
  `TierLadder` is a config snapshot per decision. Amounts via `Amount` (bcmath, 2 places).
- `PointWallet`: `credit`, `debit` (throws `InsufficientPoints`), `adjust`; locks the account row; account
  rows are created outside the locking transaction (same deadlock reason as enrolment).
- Admin: **Tiers** (DataTable, per-tier edit: threshold between neighbours, guarantee, colour; Silver: colour
  only), **Customer Points** (balances, per-customer history, adjust by email/phone with a reason).

### Points: lots, expiry, summary (`Modules/Loyalty`)
- `PointWallet` methods: `balance` (expires due lots first), `credit` (Earn/Adjust only: makes a lot),
  `debit` (expire due → check balance → consume `PointLot::spendable()` order: expires_at asc, never-expiring
  last, then id; writes `PointLotUsage`), `restore(PointTransaction $debit)` (Reversal: usages back into their
  lots, then expire again what is past due), `adjust`, `expireDue`, `customersWithDuePoints`.
- Every write goes through `record()`: account balance, ledger row, then `summarise()` (insertOrIgnore the
  customer-month row + increment one column; safe because the account lock serialises the customer).
- `consume()` throws `LogicException` if lots can't cover the amount: lots and balance must never disagree.
- `PointExpiryPolicy::expiresAt($earnedAt)`: months 0 → null; day ≤ cutoff → start of earning month, else next
  month; + N months = exclusive expiry. Shown to people as `expires_at - 1 second` ("end of February").
- Migration backfill: balances from before lots became one never-expiring lot; summaries rebuilt from the ledger.
  For demo data, `--remove` and recreate after migrating so lots get real expiry dates.
- `PointStatement::for()` is the one place that shapes "balance / next expiry / by expiry / months".
- Admin tables: `PointSummaryTable` (collection over the grouped summary, max 36 months), `ExpiringPointsTable`
  (grouped `customer_id, expires_at` query, paged by yajra through a count subquery; per customer or within 3 months).
- Route names `points-summary.*` (not `points.summary`) so the "Customer Points" menu item's `points.*`
  active pattern doesn't light up on the summary page (MenuItem patterns are `routeIs` wildcards, not regex).

### Partner (`Modules/Partner`)
- `AuthenticatePartner` middleware (key length, optional IP allow-list via `IpUtils::checkIp`, `hash_equals`),
  `PointAwardService` (replay/conflict by `reference`, `CustomerDirectory::upsert`, credit + tier spending in
  one transaction, `UniqueConstraintViolationException` → replay), `PartnerPointController` (award, show).
- The module has no routes file; its routes are the partner group in `Modules/Api/routes/api.php`.

### Gift cards (`Modules/GiftCard`)
- `GiftCardExchangeService::availability|request|verify|cancel`; `check()` order active → tier (`GiftCard::allowsTier`,
  rank ≥ min) → stock → per-customer limit (issued only) → balance. `issue()` locks the card row and re-checks
  (balance via the wallet under its own lock; `InsufficientPoints` → `not_enough_points`).
- Pending exchanges hold nothing (no points, no stock); a new request fails any older pending one for that card.
- Codes: OTP `random_int` 6 digits, `hash_hmac(sha256, app.key)`, `hash_equals`; card code from an alphabet
  without 0/O/1/I + unique index. `Customer` uses `Notifiable` (mail to `email`).
- Exchange routes are named `admin.gift-card-exchanges.*` (not `admin.gift-cards.exchanges.*`) so the two menu items
  light up separately — the same `routeIs` wildcard trap as `points-summary`.
- Customer API errors: `HttpResponseException` with `{message, reason, errors}` (422), not a renamed error bag.

### Merchants (`Modules/Merchant`)
- `merchants` (settlement_rate per point), `merchant_branches` (code `encrypted` cast, hidden, `code_changed_at`),
  `merchant_rewards` (points_cost, optional payout_amount), `merchant_redemptions` (reference, customer_id,
  snapshots of reward name / points / payout, status, settlement_id for the future payout module, request_id).
- `MerchantService` (CRUD, delete guards), `BranchCodeGenerator`, `RedemptionService` (`redeem`, `reverse`;
  wrong codes counted with `RateLimiter` per customer per branch).
- Customer API controller `CustomerRewardController` (merchants, points, redemptions, redeem); admin screens
  Merchants (list, merchant page with Branches and Rewards tables, forms) and Redemptions (filters, reverse page).
- `merchant:demo-data`: sets `Carbon::setTestNow()` to each event's date so history looks real; tagged by
  merchant notes `Demo data (merchant:demo-data)` and customer ids `demo-N`.

### Diagrams (`docs/diagrams/pos-system-map.tldraw`)
- Drawn through the tldraw offline local API (skill `tldraw-offline`), with `helpers.mermaid()` for flows and
  trees. Two pages: "Page 1" (module map, two flows) and "Module trees". Predates the Customer module.

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
   `mapApiRoutes` call + method in `app/Providers/RouteServiceProvider.php` (or copy Loyalty's provider).
3. Migration in `Modules/Product/database/migrations` (indexes for every filter/sort column; check the
   filename sorts after any table it references).
4. Model, then logic in `app/Services` with constructor injection; FormRequests for validation;
   Policy only for rules a permission can't express. Money or stock: lock the row, write in one transaction.
5. `routes/web.php` as in "Modules and routes"; `ProductDatabaseSeeder extends ModulePermissionSeeder`
   returning `['product' => self::CRUD]`; run `module:seed Product`.
6. Provider `boot()`: `MenuGroup` if there will be several screens, then `MenuItem`s with `parent`.
7. Lists: DataTable like the Access users list or Merchant screens (`app/Tables`, a `DataTableRequest`,
   `cells/*`, a `…/data` route, `<x-datatable>`). Forms: copy the Merchant forms (card, `custom-switch`,
   Cancel left / Save right). Feedback with toasts. `npm run build`.
8. Feature tests in `Modules/Product/tests/Feature` using `seedAccess()` / `userWithRole()` / factories;
   include a "role without permission gets 403" test and a sort test for any column you format.
   Add the pages to `tests/Feature/AdminPagesRenderTest.php`.
9. API needed? Staff endpoints in the staff group of `Modules/Api/routes/api.php` (+ `permission:`),
   customer endpoints in the customer group; return a Resource.
10. Check it in the browser (see "Verifying UI changes"), then update `AGENTS.md` (module table) and
    this skill (build history, pieces, traps).

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
| MySQL `1213 Deadlock` on `SELECT … FOR UPDATE` under simultaneous first sales | `INSERT IGNORE` of an existing key takes a shared lock; several sessions then upgrade it to exclusive | lock-first; do the insert-if-missing as its own statement before the locking transaction (`TierQualificationEngine::enrolIfNew`, `PointWallet::ensureAccount`). SQLite tests can't show this: race against the Docker MySQL |
| DataTables extension does nothing / "DataTable is not a function" | `admin-lte` pulls `datatables.net` 1.13; `-bs4` v3 and `-responsive` v4 got separate nested cores | `datatables.net` ^3 is a direct devDependency, so one core is hoisted (`npm ls datatables.net`) |
| Responsive "▶" control on its own line above the cell | its `::before` is inline, the avatar cell is a block `d-flex` | `app.css` makes `.d-flex` inside `td.dtr-control` inline-flex |
| yajra list could dump the whole table / leak SQL | `length=-1` disables paging; default `error => null` returns the exception message | `DataTableRequest` caps length; `config/datatables.php` `error => 'throw'` |
| Unpaged table (`paging: false`) gets 422 | DataTables sends `length=-1` | the JS sends `start=0, length=100` when paging is off |
| DataTables column won't sort (no error) | `addColumn('balance')` puts `balance` on yajra's blacklist | `editColumn` for real columns; `addColumn` only with a different `name` |
| Important column (Status) hidden at laptop width | Responsive folds columns without priority from the right | set `'priority'` on the columns that must stay |
| Loading row never shows on first load | DataTables fires `processing.dt` inside its constructor | bind `$(element).on('processing.dt', …)` before `new DataTable(...)` |
| Sidebar group opens but never closes | several `data-widget="treeview"` lists without `id`: AdminLTE binds each list's click handler to every list, so one click toggles 3x | unique `id` per list (`sidebar-section-{{ $loop->index }}`); `AdminPagesRenderTest` checks it |
| Model route key error: `Object of class TierLevel could not be converted to string` | `getRouteKeyName()` on an enum-cast column | also override `getRouteKey()` to return `->value` (`LoyaltyTier`) |
| Weak branch codes slipped through (890123, 135791) | step check ignored wrap-around 9→0 | steps compared modulo 10 (`BranchCodeGenerator::isWeak`) |
| Money/points race (double spend) can't be seen in SQLite tests | no real row locks | race N parallel `php` processes against the Docker MySQL (see "Running and checking") |
| `RequestGuard::setUser(): Argument #1 must be Authenticatable, Customer given` | Sanctum token owner model without the contract | `Customer implements Authenticatable` (trait `Illuminate\Auth\Authenticatable`) |
| A customer token could call staff API routes (or the reverse) | Sanctum's guard resolves *any* tokenable model | `EnsureStaffToken` / `EnsureCustomerToken` on the route groups |
| Customer #5 and staff user #5 shared one API rate limit | `throttle:api` keyed by the bare id | key `class_basename(user):id` |
| Renaming `user_id` → `customer_id` on tables with data | old rows point at users and can't be mapped | migration refuses while rows exist; remove demo data first (`merchant:demo-data --remove`) |
| Test expectation wrong, code right (reversal across an expired lot) | easy to mis-add lots by hand | write the lot timeline in comments (earned → expires, what each debit took) before the assert |
| Intermittent failure of `MemberPointsTest::admin adjusts points…` (once in ~10 full runs, never alone) | not found yet | if it shows again, capture the assertion message before changing anything |
| MenuItem active pattern with `(a\|b)` never matches | `routeIs` uses `Str::is` wildcards, not regex | give the routes their own name prefix (`points-summary.*`) |
| tldraw: diagram drawn on top of an earlier one | `helpers.mermaid()` `blueprintRender.position` is the **centre**, and each diagram is one group | draw far away, measure, then `editor.nudgeShapes` the group into place |
| tldraw: closing one window closed the others / a new doc window vanished | app window handling | never draw on documents you didn't create; re-list docs by name, reopen your own file with `open -a "tldraw offline" <file>` |

## Verifying UI changes

Tests prove pages render; they don't prove they look right. For visual changes: `npm run build`, then
screenshot the Docker app (http://localhost:8080, `admin@pos.test` / `password`) with headless Chrome
(puppeteer-core against `/Applications/Google Chrome.app`, installed in a scratch folder outside the project):

- light **and** dark (set `localStorage.theme` with `evaluateOnNewDocument`; the user's own browser is dark),
  plus a 390 px wide view loaded at that width (resizing mid-page gives false horizontal overflow);
- wait for `#<table> tbody tr td:not(.dt-loading-cell)` before a list screenshot; slow the `/data` request
  with request interception to capture the loading row;
- move the mouse off the sidebar and `document.activeElement.blur()` before shooting a collapsed sidebar
  (hover/focus expands it);
- check `pageerror` and failed responses are empty — a jQuery load-order mistake shows up there, not in PHP tests;
- if you create records through the UI to test, delete them again (or use `merchant:demo-data` / `--remove`).
