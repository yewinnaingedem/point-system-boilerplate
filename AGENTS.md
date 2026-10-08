# POS System — agent & developer guide

Read this first. It is the map; details live in the code and in the skill
(`.claude/skills/pos-system-foundation/SKILL.md`, which also has the build history and the traps already hit).

## What this is

A point-of-sale system built on the architecture of the Shwemandalar Cargo system (modules,
roles/permissions, app settings, `admin` area) on a modern stack. Built so far: the foundation
(staff accounts, roles, settings, API, logs) and the **loyalty side**: customers, tiers, points,
merchants where points are spent, and redemptions. **Not built yet:** Sales (which will award
points and tier spending) and Settlement (paying merchants what redemptions cost).

- **Laravel 12**, **PHP 8.2+** (developed on 8.4). Admin UI is **AdminLTE 3.2** (Bootstrap 4.6, jQuery 3,
  Font Awesome 5), bundled with Vite. No Tailwind: its reset breaks Bootstrap.
- `nwidart/laravel-modules` v12 for modules, `spatie/laravel-permission` v6 for roles/permissions,
  `yajra/laravel-datatables-oracle` v12 for server-side lists, `firebase/php-jwt` for customer SSO.
- DB is MySQL (Docker, see "Running it"); tests use in-memory SQLite.
- **Two kinds of account:** `users` = staff (admin panel, POS app); `customers` = people who earn and
  spend points, signed in from the partner Laravel project. Never mix them.

## Layout

```
app/
  Enums/SystemRole.php          Administrator / Manager / Cashier (protected roles)
  Http/Controllers/Auth/         sign in / sign out (no public registration)
  Http/Controllers/ProfileController.php   own profile + password
  Http/Middleware/EnsureUserIsActive.php   kicks out users deactivated mid-session
  Http/Requests/DataTableRequest.php       base request for every server-side list
  Listeners/RecordLastLogin.php  users.last_login_at
  Support/Access/ModulePermissionSeeder.php  base seeder every module extends
  Support/Menu/                  MenuItem, MenuGroup (collapsible), MenuRegistry (sidebar, filled by modules)
Modules/<Name>/
  app/Http/Controllers, app/Http/Requests, app/Services, app/Support, app/Policies, app/Models
  app/Tables/*Table.php          DataTables JSON for a list (see "Lists")
  app/Console/                   artisan commands (registered in the provider's $commands)
  app/Providers/<Name>ServiceProvider.php   bindings, menu items, schedules, rate limiters
  routes/web.php                prefix `admin`, name `admin.`, middleware `admin`
  database/migrations, database/seeders/<Name>DatabaseSeeder.php (permissions)
  resources/views               referenced as `<alias>::view`; list cells in `cells/*.blade.php`
  tests/Feature, tests/Unit
resources/views/
  components/layouts/admin.blade.php   AdminLTE shell: <x-layouts.admin :title="..." :breadcrumbs="[label => url]">
                                       (navbar, sidebar, content-header + breadcrumb, footer, shared delete modal, toasts)
  components/ avatar, confirm-delete, datatable (server-side list, see "Lists")
  partials/   head, navbar, sidebar, sidebar-link, toasts + toast, dark-mode-init, sidebar-state-init, ui-state-restore
  auth/login.blade.php (AdminLTE login-box), profile/edit.blade.php
resources/css/app.css            AdminLTE + Font Awesome + DataTables CSS; --brand colour; toasts, table
                                 loading row, sidebar section dividers, tier/customer badges
resources/js/app.js              jQuery global → Bootstrap → AdminLTE, then ui/ helpers:
  ui/dark-mode.js, ui/confirm-delete.js,
  ui/forms.js (file input label, password eye, toggle-all, colour sync, autosubmit, form[data-confirm]),
  ui/datatables.js (lazy-loads DataTables only on pages with <x-datatable>), ui/toasts.js,
  ui/ui-state.js (remembers sidebar collapsed / open groups / collapsed cards in localStorage)
docs/customer-sso.md             how the partner project signs customers in (with its code)
docs/partner-api.md              how the partner project awards points (with its code)
docs/diagrams/pos-system-map.tldraw   module map and flows (tldraw offline)
```

## Modules

| Module | What it does |
|---|---|
| Dashboard | `/admin/dashboard`, landing page for everyone. Overview widgets need `view-dashboard`. |
| Access | Staff users: DataTable list with All / Active / Deactivated / Deleted tabs, view page (effective permissions, browser sessions, app devices), create/edit, change password, clear sessions, activate/deactivate, **Login as** (`impersonate-user`), soft delete → restore / delete permanently. Roles (permission matrix), permissions (read-only list). |
| AppSetting | Settings stored in the `settings` table, edited at `/admin/settings/{group}` (tabs incl. **Loyalty**: tier cycle length, point expiry months and cutoff day). |
| Api | Token API at `/api/v1` (Sanctum): staff routes for the POS app, plus the route groups other modules add (customer API). Admin **API Tokens** screen (`view/delete-apitoken`). See "API". |
| LogViewer | `/admin/logs`: **daily logs only** (`laravel-YYYY-MM-DD.log`, one row per day with per-level counts). Click a day for its entries: level filter, search, stack traces; download / delete. `view/download/delete-logviewer` (Administrator only). Reads at most the newest `LOG_VIEWER_MAX_BYTES` (5 MB). |
| Customer | **Customers**: created/updated by sign-in from the partner Laravel project (JWT SSO). Start at Silver, have a tier history, points and redemptions. Admin **Customers** screen (`view/edit-customer`), customer API `/api/v1/customer/*`. See "Customers". |
| Loyalty | Tiers Silver → Gold → Platinum → Diamond: qualification engine, guarantee, cycles, tier config screen (**Loyalty → Tiers**, `view/edit-loyaltytier`), and the **points wallet** with expiring point lots and a monthly summary (**Loyalty → Customer Points / Points Summary**, `view/adjust-point`). See "Loyalty". |
| Partner | Server-to-server API for the partner project: **award points** (`POST /api/v1/partner/points`, idempotent by `reference`, optional tier spending) and read a customer's points. `PARTNER_API_KEY` bearer key. See "Partner API". |
| GiftCard | **Gift cards** customers exchange points for: tier restriction (min tier), stock (out of stock at 0), max per customer, validity days, optional **two-step** (emailed 6-digit code). Admin **Gift Cards** + **Exchanges** (cancel = refund + restock). Customer API `/api/v1/customer/gift-cards*`. See "Gift cards". |
| Merchant | Partners where customers **spend** points (e.g. KFC; merchants never award points): merchant → branches (each with a 6-digit code) → rewards. **Redemptions** screen with reversal. `merchant:demo-data`. See "Merchants & redemptions". |

Sidebar: **Main** (Dashboard) · **Sales** (Customers, Loyalty ▸ Tiers / Customer Points / Points Activity / Points Summary, Merchants ▸ Merchants / Redemptions, Gift Cards ▸ Gift Cards / Exchanges)
· **Administration** (Access Management ▸ Users / Roles / Permissions, API Tokens, Settings, Logs).

### Adding a module

```bash
php artisan module:make Product
```

Then, following Access/AppSetting (the skill has the full recipe):

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
   `$this->app->make(MenuRegistry::class)->add(new MenuItem('Products', 'admin.products.index', 'fas fa-box', 'Inventory', 'view-product'))`.
   Several related links? Register a collapsible group and point the items at it:
   `->addGroup(new MenuGroup('inventory', 'Inventory', 'fas fa-boxes', 'Inventory', 10))` then
   `new MenuItem(..., permission: 'view-product', order: 10, parent: 'inventory')`. A group shows only
   if the user can see one of its items and opens itself on their pages.
   Sections: Main, Sales, Inventory, Reports, Administration (each is its own `<ul>` with a divider).
5. Lists that grow: server-side DataTable (see "Lists"). Views: `<x-layouts.admin :title="..." :breadcrumbs="[...]">`
   and plain AdminLTE markup: `card card-primary card-outline`, `info-box`, `custom-control` switches,
   `btn btn-sm`. Copy from the Access / Merchant views. Delete buttons:
   `<x-confirm-delete :action="route(...)" :title="..." />` (shared modal); other risky buttons:
   `<form data-confirm="Sure?">`. A collapsible card that should stay collapsed: `data-remember-card="module.key"`.
6. Feedback: `->with('success' | 'info' | 'warning' | 'error', ...)` shows a **toast** top-right with a
   countdown bar (hover pauses, × closes). From JS: `toast('error', 'text')`. No queries in Blade.
   Reference: https://adminlte.io/themes/v3/

## Lists (yajra DataTables)

Every list that can grow is a server-side DataTable: users, API tokens, merchants, branches, rewards,
redemptions, customers, tier history, customer points and history, and the tiers list (`:paging="false"`).
Packages: `yajra/laravel-datatables-oracle` + `datatables.net-bs4` / `-responsive-bs4` (v3; `datatables.net`
^3 is a direct devDependency so only one core is installed). Roles and permissions stay plain tables.

- Page route renders filters + `<x-datatable id source columns filters params order empty loading ...>`; a
  `…/data` route (same permission) returns JSON from `<Module>/app/Tables/*Table`.
- The data request extends `App\Http\Requests\DataTableRequest` (requires `start`/`length`, max 100:
  yajra returns **every row** for `length=-1`). Add filters in `filterRules()`, access in `authorize()`.
- In the table class: `->filter(fn ($q) => $q->search($term))` (prefix search instead of yajra's `%term%`),
  `->whitelist([...sortable columns])`, HTML cells from Blade partials (`cells/*.blade.php`, so `@can`
  works), `->rawColumns([...])` for those only, `->only([...])` so no other model field is sent.
- Format a real column with `->editColumn('balance', ...)`, never `->addColumn('balance', ...)`: yajra blacklists
  every added name, so sorting on it is silently ignored. `addColumn` only for names that aren't the sort column.
- Column `priority` decides what the Responsive extension folds into the ▶ row first; give the column people
  need most (e.g. Status) a low number.
- `:paging="false"` for short fixed lists (the JS then asks for 100 rows, never `length=-1`).
- Loading state is AWS-console style (spinner + muted text in one centred row): `:loading="__('Loading users')"`.
- `config/datatables.php` `error => 'throw'`: a failing query goes to the exception handler, not the browser.
- Filter form fields (and a `search` field) are sent with each request and mirrored in the URL; tab links
  with `data-keep-filters` keep them. Tests: `$this->dataTable($url, $columns, $params, $search)` and
  `dataTableText()` (JSON escapes `/`, so `assertSee(route(...))` on the JSON won't match).

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
  never overwrites changes made in the UI. New module permissions (customer, loyaltytier, point,
  merchant, merchantcode, redemption, giftcard, giftcardexchange) start with Administrator only: grant them on the Roles screen.

## Settings

- Read with `setting('company_name')`, image URLs with `setting()->url('logo')`, amounts with
  `money($amount)` (currency symbol + position). Helpers: `Modules/AppSetting/app/helpers.php`.
- **To add a setting, add a `SettingField` in `SettingCatalog`.** The form, validation and default
  come from there; nothing else to edit. A new group = a new tab (e.g. **Loyalty**: `loyalty_cycle_months`,
  `point_expiry_months` (0 = never), `point_expiry_cutoff_day` (15)).
- Values are cached forever under one key and flushed on save. After editing the `settings` table
  by hand: `php artisan cache:forget appsetting.values`.
- `app_name` and `timezone` override `config('app.*')` at boot. `primary_color` (default AdminLTE
  blue `#007bff`) is printed into CSS as `--brand` (validated `#rrggbb`); `app.css` makes
  `btn-primary`, `bg-primary`, active sidebar/pills, `card-primary`, progress bars etc. use it.
  If you use a new AdminLTE "primary" component and it stays blue, add its selector there.
- Unlike the old cargo system, saving settings never rewrites `.env`.

## Customers (`Modules/Customer`)

- `customers`: `external_id` (the partner project's id, JWT `sub`; the identity), name, email, phone,
  is_active, last_login_at. Created/updated only by `CustomerSsoService::signIn()`; a new customer is
  enrolled at **Silver** (`TierQualificationEngine::enrol`), so "enrolled" is the first tier-history entry.
- **SSO** (auto-login): the partner signs an HS256 JWT (`CUSTOMER_SSO_SECRET` ≥ 32 chars, same on both sides;
  `iss`/`aud` checked; lifetime ≤ 5 min; `jti` single use). `POST /api/v1/customer/auth/sso` returns our
  Sanctum token on the `Customer` model (`CUSTOMER_TOKEN_TTL_DAYS` 90, one per device, max 5). Any bad token
  gets the same 422; the reason is logged. Sign-in is off while the secret is empty. Partner code: `docs/customer-sso.md`.
- `Customer` implements `Authenticatable` (Sanctum's guard requires it) but has no password.
- Sanctum resolves any token owner, so routes check the kind: `EnsureStaffToken` on staff API routes,
  `EnsureCustomerToken` on `/api/v1/customer/*` (403 for the wrong kind). Rate-limit keys are `User:5` / `Customer:5`.
- Admin **Customers**: list (tier badge, points, last sign-in; filter by tier/status), customer page (tier +
  progress to the next tier, tier history, signed-in devices). **Deactivate** revokes all their tokens;
  their next request is 401 and SSO is refused.

## Loyalty (`Modules/Loyalty`)

- A sales module calls `TierQualificationEngine::processTransaction($customerId, $amount, $date)` per
  completed sale; it returns a `TierResult`. Amounts are decimal strings (bcmath), never floats.
- Only **current-cycle** spending counts (`loyalty_cycle_spendings`, one row per customer per cycle,
  atomic increments, no `SUM` over sales). **Spending resets to 0 each cycle; nothing carries over.** A
  customer's cycles start on the 1st of the enrolment month; length is the `loyalty_cycle_months` setting.
- Rules are in one pure class, `TierStateMachine` (Upgraded / Requalified / Protected / Demoted).
  Invariant: tier = max(tier this cycle's spending qualifies for, current tier while its guarantee runs).
  Upgrade is immediate; demotion goes to the qualified tier, not one step down.
- Every change is logged in `loyalty_tier_events` (the tier history); `TierChanged` fires after commit.
- Concurrency: every write takes `SELECT … FOR UPDATE` on the customer's `loyalty_member_statuses` row.
  Enrolment (`INSERT IGNORE`) runs **before** that transaction (inside it MySQL deadlocks under simultaneous
  first sales). Called inside a caller's own transaction, Laravel can't retry a deadlock; the caller must.
- A sale dated before the active cycle throws `TransactionOutsideActiveCycle` (that cycle is closed).
- `loyalty:evaluate-tiers` (hourly) handles customers with no sales via the indexed `next_evaluation_at`.
- Tier config: threshold per cycle (must rise with the tier), guarantee months, badge `color` (`#rrggbb`).
  Show a tier with `@include('loyalty::partials.tier-badge', ['tier' => $tier])`.
- **Points wallet** (`PointWallet`): `loyalty_point_accounts` (balance) + append-only `loyalty_point_transactions`
  (earn / redeem / adjust / reversal / expire, signed points, balance_after, morph source, unique `reference`,
  created_by = staff user). Every change locks the account row. **Points do not reset** with the cycle.
- **Expiry** (`PointExpiryPolicy`, settings `point_expiry_months`, `point_expiry_cutoff_day`): every credit is a
  **lot** (`loyalty_point_lots`: points, remaining, expires_at exclusive). Earned on/before the cutoff day → the
  earning month is the first counted month; later → counting starts next month; gone at the end of the last
  counted month (2 months: 10 Jan → end Feb, 20 Jan → end Mar). Debits expire what is due, then take from the
  **soonest-expiring lots** (`loyalty_point_lot_usages` records which); `restore()` puts a reversed debit back
  into the same lots (re-expiring at once if they are past due). `balance()` expires lazily; `loyalty:expire-points`
  sweeps daily at 00:10. Books always agree: balance = ledger sum = remaining in lots.
- **Monthly summary** `loyalty_point_summaries` (customer × month: earned, redeemed, reversed, adjusted_in/out,
  expired), written in the same transaction as each ledger row. `PointStatement::for($customerId)` (balance,
  next expiry, by expiry, last 12 months) feeds the customer API, partner API and admin pages.
- Admin: **Customer Points** (balances, history, points by expiry, month by month, adjust by email/phone),
  **Points Activity** (all customers' movements by period / type / customer or reference; "customers who earned"
  grouped per customer; today's earned / earners / redeemed / expired) and **Points Summary** (outstanding,
  expiring this/next month, all-customer month table, expiring soon).
- Tiers, points and redemptions belong to **customers** (`customer_id`), never to staff `users`.

## Merchants & redemptions (`Modules/Merchant`)

- Flow: customer picks a branch + reward in the app → shop staff type the **branch's 6-digit code** on the
  customer's phone → `RedemptionService::redeem()` checks availability, lock-out, code, then in one transaction
  writes the redemption and debits the points (row lock: 10 simultaneous redemptions on 500 points → exactly 1
  succeeds, checked on MySQL).
- Codes: `BranchCodeGenerator` (random, skips 000000 / 121212 / 123456 / 890123-style runs), stored **encrypted**,
  compared with `hash_equals`, never in API responses. Wrong codes: `code_max_attempts` (5) per customer per
  branch, then `code_lockout_minutes` (15) lock-out (429). Seeing/replacing codes needs `view/edit-merchantcode`.
- Payout owed to the merchant is a **snapshot** on each redemption: the reward's `payout_amount`, else
  points × merchant `settlement_rate`. `request_id` makes app retries idempotent.
- **For the settlement (payout) module:** redemptions with `status = completed` and `settlement_id IS NULL`
  (`Redemption::unsettled()`, indexed `merchant_id, status, settlement_id`) are what is owed. A settlement row
  groups them per merchant/period and sets their `settlement_id`; settled redemptions can't be reversed.
- Merchants/branches with redemptions can't be deleted (money is owed): deactivate them. Rewards can (name and
  amounts are snapshots). Reversal (`reverse-redemption`) returns the points and removes it from what is owed.
- **Demo data:** `php artisan merchant:demo-data` creates 4 merchants (1 inactive), 10 branches, 10 rewards,
  8 customers (partner ids `demo-1`..`demo-8`, enrolled at Silver), 90 days of purchases (1 point per 1,000 Ks +
  tier spending) and redemptions (2 reversed), all through the real services. `--remove` deletes exactly that
  data. Refuses in production without `--force`.

## Gift cards (`Modules/GiftCard`)

- `gift_cards`: points_cost, face_value, `min_tier` (TierLevel; "Diamond" = Diamond only, null = everyone), `stock`
  (null = unlimited), `per_customer_limit`, `valid_days`, `requires_verification`, is_active.
  `gift_card_exchanges`: pending / issued / cancelled / failed, code `GC-XXXX-XXXX-XXXX`, snapshots of points and value.
- `GiftCardExchangeService`: checks active → tier → stock → per-customer limit (issued only) → points. Issuing locks
  the gift card row (`FOR UPDATE`) and re-checks, then debits points (`Redeem`, soonest-expiring first), decrements
  stock, issues the code — one transaction (MySQL: 10 customers racing for stock 1 → exactly 1 issued).
- Two-step: `request()` creates a pending exchange and emails a 6-digit code (`GiftCardVerificationCode`; stored as
  an HMAC; 10 min; 5 wrong tries) — nothing is taken until `verify()`. Customers need an email for it.
  Mail goes to the log in development (`MAIL_MAILER=log`): set real mail before using 2-step.
- Customer API rejections are 422 with a stable `reason` (`tier`, `out_of_stock`, `limit_reached`,
  `not_enough_points`, `wrong_code`, `code_expired`, `too_many_attempts`, `no_email`, ...).
- Admin cancel (`cancel-giftcardexchange`) restores the points to their original lots and returns the stock.
  Cards with exchanges can only be deactivated. Permissions `giftcard` CRUD, `giftcardexchange` view/cancel.

## Partner API (`Modules/Partner`)

- Server to server from the partner project: `Authorization: Bearer <PARTNER_API_KEY>` (≥ 32 chars, `hash_equals`;
  off while empty), optional `PARTNER_API_ALLOWED_IPS`, `throttle:partner`. No user is signed in. Routes live in
  `Modules/Api/routes/api.php` (partner group). Contract + partner code: `docs/partner-api.md`.
- `PointAwardService::award()`: idempotent by `reference` (replay → same award, 200; different customer/points → 409),
  creates unknown customers (`CustomerDirectory::upsert`, enrolled at Silver), refuses deactivated customers, and
  credits points + optional `spent_amount` tier spending in **one** transaction.
- `CustomerDirectory::upsert()` is shared with SSO: the partner owns name/email/phone.

## API (`Modules/Api`)

Laravel Sanctum personal access tokens: stored **hashed** in `personal_access_tokens`, so deleting
a row revokes the token on the next request. This replaces the old JWT + `user_tokens` +
`check-token-in-db` setup. Two route groups in `Modules/Api/routes/api.php`:

| Method | Path | Auth | |
|---|---|---|---|
| POST | `/api/v1/auth/login` | none, `throttle:api-login` (5/min per login+IP) | staff: `login` (email or phone), `password`, `device_name` → `data.token` |
| POST | `/api/v1/auth/refresh` | staff token | new token for this device, old one revoked |
| POST | `/api/v1/auth/logout` / `logout-all` | staff token | revoke this token / all of the user's tokens |
| GET | `/api/v1/me` | staff token | user, roles, permission names (for showing/hiding app screens) |
| GET | `/api/v1/settings` | staff token | business, currency, tax and receipt settings |
| POST | `/api/v1/customer/auth/sso` | partner JWT, `throttle:customer-sso` (30/min per IP) | customer sign-in → our customer token |
| POST | `/api/v1/customer/auth/logout` / `logout-all` | customer token | sign out this device / every device |
| GET | `/api/v1/customer/me`, `/tier-history` | customer token | profile, points, tier + progress to the next tier; tier history |
| GET | `/api/v1/customer/merchants`, `/points`, `/redemptions` | customer token | where to spend, balance + history, own redemptions |
| POST | `/api/v1/customer/redemptions` | customer token | `branch_id`, `reward_id`, `code`, optional `request_id` → 201 (200 on retry); 422 wrong code / not enough points; 429 locked out |
| GET | `/api/v1/customer/gift-cards` | customer token | active cards with `available` + `reason` for this customer |
| POST | `/api/v1/customer/gift-cards/{id}/exchange` | customer token | 201 issued (code) or 202 pending (code emailed); 422 + `reason` |
| POST | `/api/v1/customer/gift-card-exchanges/{id}/verify` | customer token | `code` (6 digits) → issued; 422 + `reason` |
| GET | `/api/v1/customer/gift-card-exchanges` | customer token | own gift cards (issued / cancelled) |
| POST | `/api/v1/partner/points` | partner key | award points (`customer{}`, `points`, `reference`, `note`, `spent_amount`) → 201 / 200 replay / 409 |
| GET | `/api/v1/partner/customers/{external_id}/points` | partner key | balance, expiry, months, tier |

Send `Authorization: Bearer <token>`. Errors are always JSON (401 no/expired/revoked token,
403 missing permission or wrong kind of token, 422 validation, 429 rate limit).

**Rules, and what is deliberately *not* exposed (unlike the old cargo API):**
- Staff login and customer SSO are the **only** routes without a token (partner routes need the partner key):
  no public signup, lookups or print endpoints. New staff endpoints go in the staff group with a `permission:<action>-<resource>`
  middleware; new customer endpoints in the customer group (`$request->user()` is then a `Customer`).
- Responses are API Resources with explicit fields, never a raw model. Branch codes and merchant payouts
  are never sent to customers.
- Wrong password, unknown user and inactive user all get the same 422 message.
- `config/sanctum.php` `guard => []`: the admin's browser session can't call the API.
  CORS only allows `CORS_ALLOWED_ORIGINS` (default `APP_URL`); native apps don't need CORS.
- Staff tokens expire after `API_TOKEN_TTL_DAYS` (30), at most `API_MAX_DEVICES` (3) per user; customer
  tokens see "Customers". `sanctum:prune-expired` runs daily (module schedule).
- Deactivating a user, deleting them, or changing their password revokes all their tokens. A user
  deactivated directly in SQL is cut off on their next request (`EnsureTokenUserIsActive`).
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
| `scheduler` | `schedule:work` (`sanctum:prune-expired` daily, `loyalty:evaluate-tiers` hourly, `loyalty:expire-points` 00:10) | — |
| `mysql` | MySQL 8.4, db `pos_system`, volume `mysql-data` | **3308** (`FORWARD_DB_PORT`) |
| `redis` | Redis 7 (cache + queue), volume `redis-data` | **6380** (`FORWARD_REDIS_PORT`) |

```bash
cp .env.example .env               # set DB_PASSWORD / DB_ROOT_PASSWORD, DOCKER_UID/GID = `id -u` / `id -g`,
                                   # CUSTOMER_SSO_SECRET / _ISSUER for customer sign-in (docs/customer-sso.md),
                                   # PARTNER_API_KEY for awarding points (docs/partner-api.md)
composer install && npm install && npm run build
docker compose up -d --build       # http://localhost:8080
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan storage:link
docker compose exec app php artisan merchant:demo-data   # optional sample data; --remove to delete it
```

- `.env` holds the **host** view (`DB_HOST=127.0.0.1`, `DB_PORT=3308`, `REDIS_PORT=6380`); compose
  overrides them inside the containers (`mysql:3306`, `redis:6379`). So `php artisan …` works both on
  the host (PHP 8.4) and via `docker compose exec app php artisan …`. **Don't `config:cache` in
  development**: it would freeze one set of hosts for both.
- Redis client is **predis** (`REDIS_CLIENT=predis`): pure PHP, works without the `redis` extension.
- Sessions stay in the database (`SESSION_DRIVER=database`), because the user screen lists and clears them.
- After changing queued code: `docker compose restart queue`. After new permissions: `module:seed <Module>`.
- Default sign-in: `admin@pos.test` / `password`. Change it after first sign-in.

**Data moved from SQLite (2026-10-07).** The app first ran on `database/database.sqlite`. Its data was
copied into MySQL with `php artisan db:import-sqlite`. Only MySQL backups `pos_system_mysql_*.sql` are kept in
`database/exports/` (git-ignored: they contain password hashes). Restore one with
`docker compose exec -T mysql mysql -u pos_user -p pos_system < database/exports/<file>.sql`.

**Loyalty moved from users to customers (2026-10-08).** Migration `move_loyalty_ownership_to_customers`
renames `user_id` → `customer_id` on the loyalty/redemption tables and refuses to run while they hold rows.

## Testing

```bash
php artisan test                 # tests/Feature + Modules/*/tests, in-memory SQLite (160 tests)
vendor/bin/pint                  # code style
```

- `Tests\TestCase::seedAccess()` seeds permissions, roles and the admin; `userWithRole()` makes a staff user;
  `Customer::factory()` makes a customer; `dataTable()` / `dataTableText()` call a list's JSON endpoint.
- Every module has a "role without permission gets 403" test; `AdminPagesRenderTest` opens every admin page.
- Row locks and races can't be seen in SQLite: for money/points/tier concurrency, race parallel `php`
  processes against the Docker MySQL (done for tier enrolment and redemptions, incl. with point lots).
- Time-dependent rules (expiry, cycles): `$this->travelTo(...)` in tests; save settings with
  `SettingService::saveGroup($catalog->group('loyalty'), [...])` (all fields of the group).

## Code conventions

- OOP first: logic in Services/Support classes, collaborators injected through the constructor,
  named constants instead of magic values. Controllers stay thin; **Blade has no queries**.
- Validation and authorization in FormRequests; extra ownership rules in Policies.
- Multi-row writes in `DB::transaction`; anything with money or points locks its row (`FOR UPDATE`).
  Search with prefix `LIKE 'abc%'`, not `%abc%`.
- Strings: `"User {$name}"`, not concatenation. Blade: `{{ }}`; `{!! !!}` only for trusted HTML.

## Diagrams

`docs/diagrams/pos-system-map.tldraw` (open with tldraw offline), colour-coded by module, dashed grey = planned:
- page "Page 1": module map, the redeem-at-a-branch flow, the tier-qualification flow;
- page "Module trees": one tree per module - Merchant, Clear merchant amount (settlement; mostly planned), Loyalty tier.
They were drawn before the Customer module and still say "member": update them when you next touch them.

## Skills

Detailed knowledge lives in `.claude/skills/` (Claude Code loads these automatically; other agents
can read the `SKILL.md` files directly):

| Skill | Use when |
|---|---|
| `pos-system-foundation` | adding a module or screen; touching the sidebar, lists, permissions, settings, API, customers, loyalty, merchants, logs or the AdminLTE theme; the build history and the traps already hit |

When you learn something non-obvious that the next person needs, add it here (short) or in the skill
(detailed). Keep this file a map, not a manual.
