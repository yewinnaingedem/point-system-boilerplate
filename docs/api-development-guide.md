# API development guide

For developers who **add to or change** this project's API. To **call** the API from another
system, read [`gateway-api.md`](gateway-api.md) (the contract and the partner code) instead.

Contents

1. [Which API to use](#1-which-api-to-use)
2. [The gateway at a glance](#2-the-gateway-at-a-glance)
3. [Where the code is](#3-where-the-code-is)
4. [What happens to one request](#4-what-happens-to-one-request)
5. [Signing](#5-signing)
6. [Responses and error codes](#6-responses-and-error-codes)
7. [Adding a method, step by step](#7-adding-a-method-step-by-step)
8. [Rules every method follows](#8-rules-every-method-follows)
9. [Testing](#9-testing)
10. [Trying it by hand](#10-trying-it-by-hand)
11. [API clients (keys)](#11-api-clients-keys)
12. [Configuration](#12-configuration)
13. [Changing the contract safely](#13-changing-the-contract-safely)
14. [Not built yet](#14-not-built-yet)
15. [Checklist before you open a PR](#15-checklist-before-you-open-a-pr)

---

## 1. Which API to use

All APIs live under `/api/v1`, and their routes are all in `Modules/Api/routes/api.php`.

| API | Who calls it | Auth | Add new endpoints here when… |
|---|---|---|---|
| **Gateway** `POST /api/v1/gateway` | other **systems** (partner backend, website server) | `appid` + SHA256 signature on every request | another system needs to act for customers: points, customers, tiers, gift cards (and using them at shops) |
| Staff `/api/v1/*` | our POS app (cashiers) | Sanctum staff token + `permission:` middleware | a staff screen in the POS app needs it |
| Customer `/api/v1/customer/*` | the customer app after SSO | Sanctum customer token | the customer is signed in and calls us directly |
| Partner `/api/v1/partner/*` | partner project (older) | `PARTNER_API_KEY` bearer | **don't add here**: use the gateway. Kept for existing callers |

**New integrations from other systems go through the gateway.** Don't add new bearer-key
endpoints.

## 2. The gateway at a glance

One URL, one envelope, many methods (the KBZPay `kbz.payment.precreate` style):

```json
{
  "Request": {
    "timestamp": "1791530225",
    "method": "pos.point.create",
    "nonce_str": "5K8264ILTKCH16CQ2502SI8ZNMTM67VS",
    "sign_type": "SHA256",
    "sign": "768E0C18…",
    "version": "1.0",
    "biz_content": { "appid": "pos3k9x…", "external_id": "shop-7", "points": "250", "reference": "ORDER-1001" }
  }
}
```

- `method` picks the handler. `biz_content` carries `appid` (who is calling) and the handler's fields.
- The answer is always `{"Response": {...}}`, signed with the same key, and its HTTP status matches the outcome.
- Customers are named by `external_id`, the partner project's id for them. The calling system is trusted to act
  for its own customers.

Methods today: `pos.customer.register|query`, `pos.tier.query|history`, `pos.point.create|query`,
`pos.giftcard.list|exchange|verify|exchanges|use`, `pos.merchant.list`.

## 3. Where the code is

```
Modules/Api/
  app/Gateway/
    GatewayKernel.php        the whole request lifecycle (§4); never throws, always answers an envelope
    GatewayEnvelope.php      builds and signs {"Response": …}
    Signer.php               string-to-sign + SHA256 (§5), used for requests AND responses
    GatewayError.php         expected failure: code + message + HTTP status + field errors
    GatewayMethod.php        interface every method implements: rules() + handle()
    GatewayMethods.php       MAP of method name → class (the only registry)
    GatewayCustomers.php     find / active customer by external_id, paging helper, shared rules
    Methods/                 one class per method (RegisterCustomer, CreatePoints, UseGiftCard, …)
  app/Models/ApiClient.php            appid, encrypted secret, is_active, last_used_at
  app/Services/ApiClientService.php   create (generates appid + secret), rotate
  app/Http/Controllers/V1/GatewayController.php     one line: $kernel->handle($request)
  app/Http/Controllers/Admin/ApiClientController.php  Administration → API Clients screen
  config/config.php           'gateway' => versions, timestamp_tolerance, rate_per_minute
  database/migrations/2026_10_09_100000_create_api_clients_table.php
  tests/Feature/Concerns/CallsGateway.php   test helper that signs like a caller
  tests/Feature/GatewayTest.php             envelope, auth, nonce, timestamp, rate limit
  tests/Feature/GatewayMethodsTest.php      every method end to end
  tests/Unit/SignerTest.php                 the exact string-to-sign
docs/gateway-api.md           the contract you hand to callers
```

**Business logic is not in the gateway.** Methods are thin adapters over the module services that
the admin screens and the customer API also use:

| Method | Service it calls |
|---|---|
| `pos.customer.register` | `Customer\Services\CustomerDirectory::upsert` |
| `pos.point.create` | `Partner\Services\PointAwardService::award` |
| `pos.point.query` | `Loyalty\Support\PointStatement::for` |
| `pos.tier.query` / `pos.customer.query` | `Customer\Http\Resources\CustomerResource` (→ `CustomerTierSummary`) |
| `pos.giftcard.*` | `GiftCard\Services\GiftCardExchangeService` |
| `pos.giftcard.use` | `GiftCardExchangeService::useAtBranch` (branch code check: `Merchant\Services\BranchCodeVerifier`) |

If a rule changes, change it in the service. Every API then gets the change.

## 4. What happens to one request

`GatewayKernel::handle()` runs these steps in order. The first failure stops the request and becomes
the response:

| # | Step | Fails with |
|---|---|---|
| 1 | Body has a `Request` object | 400 `INVALID_REQUEST` |
| 2 | Envelope rules: timestamp 10 digits, nonce 16–32 alnum, `sign_type` SHA256, `sign` 64 hex, `version` 1.0, `biz_content.appid`; every biz value a string/int | 400 `INVALID_REQUEST` + `errors` |
| 3 | Client: `api_clients.app_id = appid` and active | 401 `INVALID_APPID` (unsigned) |
| 4 | Rate limit: client + IP, `rate_per_minute` | 429 `RATE_LIMITED` |
| 5 | *(IP allow-list goes here, not built)* | |
| 6 | Signature | 401 `INVALID_SIGNATURE` (logged as notice with appid, method and IP) |
| 7 | Timestamp within ± `timestamp_tolerance` (300 s) | 401 `TIMESTAMP_EXPIRED` |
| 8 | Nonce single use: `Cache::add("api-gateway:nonce:{client}:{nonce}")` | 409 `DUPLICATE_NONCE` |
| 9 | Method in `GatewayMethods::MAP` | 400 `UNKNOWN_METHOD` |
| 10 | The method's `rules()` on `biz_content` | 422 `VALIDATION_FAILED` + `errors` |
| 11 | `handle($validated, $client)` | whatever the method throws (§6) |
| 12 | `last_used_at` updated (at most once a minute) and a signed SUCCESS envelope returned | |

Why this order:

- **Signature before nonce.** Otherwise someone without the key could use up a client's nonces.
- **Rate limit inside the kernel, not as route middleware.** That way a 429 is an envelope too.
- **No FormRequest for the envelope.** A FormRequest's 422 would be Laravel's plain JSON error, not
  an envelope.

## 5. Signing

The same function signs requests and responses (`Signer`):

1. Take the `Request` (or `Response`) object, **without** `sign` and `sign_type`.
2. Flatten nested keys with dots (`Arr::dot`): `biz_content.appid`, `biz_content.items.0.name`.
3. Drop `null`, `''` and empty arrays.
4. Booleans → `true` / `false`, everything else `(string)`.
5. Sort by key (`ksort`, `SORT_STRING`) and join as `k=v&k=v` (no URL encoding).
6. Append `&key=<secret>`, `hash('sha256', …)`, uppercase.

```
biz_content.appid=pos3k…&biz_content.points=250&method=pos.point.create&nonce_str=5K82…&timestamp=1791530225&version=1.0&key=<secret>
```

How this differs from KBZPay: KBZ puts the `biz_content` fields at the top level. We keep the
`biz_content.` prefix, so one rule also covers nested response data and two keys can never collide
(for example our `code` field and a gift card's `code`).

Don't break these:

- `GatewayEnvelope` **JSON-encodes and decodes the body before signing.** `JsonResource::resolve()` can
  leave `Collection` objects inside the array. `Arr::dot` skips objects, so without the round trip the
  signature would not match what the caller decodes.
- Compare signatures with `hash_equals`, never `===`.
- Never log the secret or the full string-to-sign (it ends with the key).

## 6. Responses and error codes

```json
{"Response": {"result": "SUCCESS", "code": "0", "msg": "success", "method": "…", "biz_content": {…},
              "nonce_str": "…", "timestamp": "…", "sign_type": "SHA256", "sign": "…"}}

{"Response": {"result": "FAIL", "code": "NOT_ENOUGH_POINTS", "msg": "Not enough points…", "method": "…",
              "errors": {"field": ["…"]}, "nonce_str": "…", "timestamp": "…", "sign_type": "SHA256", "sign": "…"}}
```

- `code` is a **stable UPPER_SNAKE string**. Callers switch on it, so never rename one; add a new one instead.
- `msg` is for people and goes through `__()`. Callers must not parse it.
- The HTTP status must match: 400 malformed, 401 who-are-you, 403 not allowed, 404 not found, 409 conflict / replay,
  422 rule or validation, 429 limit, 500 our bug.

How the kernel turns exceptions into responses:

| Thrown | Becomes |
|---|---|
| `GatewayError` | its own `errorCode`, `status`, `errors` |
| `ValidationException` (e.g. from a service) | 422 `VALIDATION_FAILED` with its errors |
| `ModelNotFoundException` | 404 `NOT_FOUND` |
| anything else | `report($e)` (goes to the daily log), then 500 `SYSTEM_ERROR`. The message is never sent to the caller |

**Map domain exceptions in the method**, so callers get a precise code:

```php
try {
    $exchange = $this->exchanges->request($customer, $card);
} catch (ExchangeRejected $e) {
    throw new GatewayError(strtoupper($e->reason), $e->getMessage());   // OUT_OF_STOCK, TIER, …
}
```

Existing mappings: `ExchangeRejected::reason` → uppercased (`useAtBranch` turns the branch code's `RedemptionRejected` into
`wrong_code` / `too_many_attempts` / `branch_unavailable` first); `PointAwardConflict` → `REFERENCE_CONFLICT` (409). Customer helpers throw
`CUSTOMER_NOT_FOUND` (404) and `CUSTOMER_INACTIVE` (403).

Shortcuts on `GatewayError`: `::validation($errors)`, `::invalidRequest($errors)`, `::unauthenticated($code, $msg)`,
`::notFound($code, $msg)`, or `new GatewayError($code, $msg, $status, $errors)`.

## 7. Adding a method, step by step

Example: `pos.branch.list`, the branches of one merchant.

**1. Write the logic in the module service first**, if it doesn't exist yet. The method only adapts it.

**2. Create the method class** in `Modules/Api/app/Gateway/Methods/ListBranches.php`:

```php
<?php

namespace Modules\Api\Gateway\Methods;

use Modules\Api\Gateway\GatewayError;
use Modules\Api\Gateway\GatewayMethod;
use Modules\Api\Models\ApiClient;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\MerchantBranch;

/** pos.branch.list: active branches of one active merchant, by name. */
class ListBranches implements GatewayMethod
{
    public function rules(): array
    {
        return ['merchant_id' => ['required', 'integer', 'min:1']];
    }

    public function handle(array $input, ApiClient $client): array
    {
        $merchant = Merchant::query()->active()->find((int) $input['merchant_id'])
            ?? throw GatewayError::notFound('MERCHANT_NOT_FOUND', __('No active merchant with this id.'));

        return ['items' => $merchant->branches()->active()->orderBy('name')->get()
            ->map(fn (MerchantBranch $branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'address' => $branch->address,   // never the code: shop staff type it, the app must not know it
            ])->all()];
    }
}
```

**3. Register it** in `GatewayMethods::MAP`:

```php
'pos.branch.list' => Methods\ListBranches::class,
```

Name it `pos.<resource>.<action>`, with a singular resource. Use these actions: `create`, `query` (one
record), `list` (many), plus a verb when needed (`register`, `exchange`, `verify`).

**4. Test it** in `GatewayMethodsTest` (§9): one success, each error code it can return, and
assertions on what must *not* be in the response.

**5. Document it** in `docs/gateway-api.md`: one row in the Methods table, and any new error code
in the error table.

**6. Run** `vendor/bin/pint` and `php artisan test`.

The constructor can take any service; the container resolves it (`GatewayMethods::resolve` uses `make`).
No routes, middleware or permissions to add.

## 8. Rules every method follows

**Validation**
- Every field in `biz_content` that the method reads has a rule. Only fields with rules reach `handle()`
  (`$validator->validated()`).
- Callers send **strings** (`"250"`). `integer` and `numeric` rules accept numeric strings, so cast in the method:
  `(int) $input['points']`, and `(string)` for amounts.
- Money: `['numeric', 'gt:0', 'decimal:0,2', 'max:…']`. Pass it on as a string (bcmath). Never use a float.
- Reuse the shared rules: `GatewayCustomers::EXTERNAL_ID`, `GatewayCustomers::PAGE`.
- `biz_content` is flat. If you think you need a nested object, use prefixed flat fields
  (`customer_name`) or a separate method.

**Customers**
- Look the customer up with `GatewayCustomers::find()` for reads, or `::active()` for anything that
  changes points or gift cards.
- Create customers only through `CustomerDirectory::upsert()` (it enrols them at Silver). It overwrites
  name, email and phone, so pass the existing profile when the caller didn't send one (see `CreatePoints::profile()`).

**Writes**
- **Every write must be safe to retry.** Callers retry after timeouts, and a used nonce forces a new
  signature but not a new operation. So take a caller key and replay on it: `reference` (points), or
  the record itself (the same gift card at the same branch). Return `replayed: true` on a replay instead of failing.
- Money, points and stock: the service locks the row (`FOR UPDATE`) inside `DB::transaction`. Don't
  write those tables from a method.

**Output**
- Return a plain array. Reuse the module's API Resource with `(new XResource($model))->resolve()`, or
  list the fields yourself. **Never return a raw model or `toArray()`.**
- Never send: branch codes, `payout_amount` or `settlement_rate`, internal notes, staff users, secrets.
  Add an assertion that the field is absent.
- Lists that can grow: `$this->customers->page($query, $input['page'] ?? null, $map)` gives
  `{items, page, per_page: 20, has_more}`. The query must have a stable order (`latest('id')` as tie-break).
- Dates as ISO 8601 (`->toIso8601String()`), or `toDateString()` for a day. Points as ints, amounts as strings.
- Avoid empty objects (`{}`). After the JSON round trip they become `[]`.

**Code style**: same as the rest of the project (AGENTS.md "Code conventions"): constructor
injection, `"text {$var}"`, `__()` for messages, one-line docblock stating the method name and what it does.

## 9. Testing

Use the `CallsGateway` trait. It creates a client and signs requests exactly like a caller would:

```php
use Modules\Api\Tests\Feature\Concerns\CallsGateway;

class GatewayMethodsTest extends TestCase
{
    use CallsGateway, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        $this->makeGatewayClient();               // $this->client, $this->secret
    }

    public function test_branch_list(): void
    {
        $response = $this->gateway('pos.branch.list', ['merchant_id' => (string) $kfc->id])
            ->assertOk()
            ->assertJsonPath('Response.code', '0')
            ->assertJsonPath('Response.biz_content.items.0.name', 'Junction Square');

        $this->assertSignedResponse($response);
        $this->assertStringNotContainsString($branch->code, $response->getContent());

        $this->gateway('pos.branch.list', ['merchant_id' => '999'])
            ->assertNotFound()->assertJsonPath('Response.code', 'MERCHANT_NOT_FOUND');
    }
}
```

| Helper | Does |
|---|---|
| `gateway($method, $biz, $overrides = [], $secret = null)` | sign and POST. `$overrides` change fields **after** signing (`['biz_content.external_id' => 'y']`) to test tampering |
| `envelope($method, $biz)` | the signed `Request` array without sending it (to replay it, or send it after `travel()`) |
| `assertSignedResponse($response)` | the response `sign` matches |

- Time rules: `$this->travelTo(...)` (expiry, timestamp window). Settings: `SettingService::saveGroup(...)`, see the existing setUp.
- SQLite can't show row locks. For a new write that touches money or points, also race it on the Docker
  MySQL (skill "Running and checking").
- Run only the API tests: `php artisan test --filter='Gateway|Signer|ApiClient'`.

## 10. Trying it by hand

### Postman (easiest)

`docs/postman/` holds a ready collection with every method plus the error cases:

| File | |
|---|---|
| `pos-gateway.postman_collection.json` | 22 requests in 6 folders: Customers, Points, Tiers, Gift cards, Use gift card at a shop, Error cases |
| `pos-local.postman_environment.json` | `base_url` (http://localhost:8000), `appid`, `secret` |

1. Postman → **Import** both files, then pick the **POS local** environment (top right).
2. Admin → Administration → **API Clients** → *New API client*. Paste the appid and secret into the environment.
3. Run **1. Customers → Register customer** first (it creates `{{external_id}}`, default `postman-1`), then go
   through the folders in order, or use *Run collection*.

How it works:

- **You never sign by hand.** A request body only needs `method` and `biz_content`. The collection's
  pre-request script adds `timestamp`, `nonce_str`, `version`, `sign_type`, `appid` and `sign` (the same
  rule as `Signer`), and resolves `{{variables}}` first.
- The test script checks the **response signature** and saves ids for the next requests. *List gift cards*
  sets `gift_card_id`, *Exchange* sets `exchange_id` (and says whether it needs the emailed code; *Verify* is
  skipped otherwise), and *List merchants* sets `branch_id`. A new point `reference` is saved, so the *replay*
  request sends it again; *Use again (retry)* repeats the last gift card use.
- Set these yourself: `branch_code`, the 6-digit code of the branch picked for *Use gift card* (Admin → Merchants → the
  merchant, needs `view-merchantcode`), and `giftcard_code`, the emailed code for two-step cards (with
  `MAIL_MAILER=log` it is in `storage/logs`). Give the customer enough points: raise `points` (collection
  variable, default 250).
- *Error cases* break one thing each through `"_case"` in the body (`bad_sign`, `old_timestamp`, `reuse_nonce`,
  `bad_appid`). The script reads it and removes it, so it's never sent. Each checks its HTTP status and `code`.
- **`INVALID_SIGNATURE` in Postman?** Almost always the `secret` in the *selected* environment isn't
  this appid's key: a different client, a key from before a rotation, or an incomplete copy. The script trims
  spaces and line breaks and refuses anything that isn't 64 hex characters. While `APP_DEBUG=true` the
  server returns `debug.string_to_sign`, and the Postman console says which case it is: "content matches"
  means a wrong key (rotate it and paste the new one); otherwise both strings are printed.
- Signature mismatch while writing your own client? The collection variable `last_string_to_sign` shows
  exactly what was signed (without the key). Compare it with `Response.debug.string_to_sign`.
- From the command line: `npx newman run docs/postman/pos-gateway.postman_collection.json -e docs/postman/pos-local.postman_environment.json --env-var appid=… --env-var secret=… --env-var branch_code=…`.
  **Don't commit a real secret** into the environment file.

A new gateway method gets a request in the right folder of the collection. Copy a neighbour and change
`method` and `biz_content`.

### A PHP script

1. Admin → **Administration → API Clients** → *New API client*. Copy the appid and the secret.
2. Save this as a script **outside the project** (e.g. `~/gateway-call.php`). Run it from the project root with
   `php ~/gateway-call.php` (PHP 8.4: `/opt/homebrew/opt/php@8.4/bin/php`, see AGENTS.md "Running it"):

```php
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$appId = 'pos…';      // from the API Clients screen
$secret = '…';

$request = [
    'timestamp' => (string) time(),
    'method' => 'pos.customer.query',
    'nonce_str' => Illuminate\Support\Str::random(32),
    'version' => '1.0',
    'sign_type' => 'SHA256',
    'biz_content' => ['appid' => $appId, 'external_id' => 'demo-1'],
];
$request['sign'] = app(Modules\Api\Gateway\Signer::class)->sign($request, $secret);

echo Illuminate\Support\Facades\Http::post('http://localhost:8000/api/v1/gateway', ['Request' => $request])->body(), "\n";
```

3. `php artisan merchant:demo-data` gives you customers `demo-1`..`demo-8` and merchants with branches to call against (create a gift card in Admin → Gift Cards).
4. Signature problems? Print `app(Modules\Api\Gateway\Signer::class)->stringToSign($request)` on both sides and
   compare. It's almost always a field that was sent but not signed, a number vs a string, or the
   wrong secret.
5. Delete the test client when you're done.

## 11. API clients (keys)

- Table `api_clients`: `name`, `app_id` (unique, `pos` + 29 lowercase chars), `secret` (64 hex chars,
  **`encrypted` cast**, `$hidden`), `is_active`, `notes`, `secret_rotated_at`, `last_used_at`.
- The secret is encrypted with `APP_KEY`. **Changing `APP_KEY` makes every client secret unreadable**, so
  rotate all clients afterwards.
- The secret is shown once, flashed to the next page after create or rotate. It's never shown again,
  and there is no "reveal" on purpose.
- Rotate = new secret, and the old one stops working at once. Agree a time with the caller. There's no
  grace period with two keys (yet).
- Permissions `view/create/edit/delete-apiclient`, Administrator only by default.
- Clients sign on a **server**. A browser or mobile app must call its own backend, which signs and
  forwards the request.

## 12. Configuration

`Modules/Api/config/config.php` → `config('api.gateway.*')`:

| Key | Env | Default | |
|---|---|---|---|
| `versions` | — | `['1.0']` | accepted `Request.version` values |
| `timestamp_tolerance` | `API_GATEWAY_TIMESTAMP_TOLERANCE` | 300 | seconds either way; also sets the nonce TTL (2× + 60 s) |
| `rate_per_minute` | `API_GATEWAY_RATE_PER_MINUTE` | 300 | per client + IP |

Nonces and rate limits use the default cache store (Redis locally). With several app servers, they
must share that cache, or a nonce could be replayed against another server.

## 13. Changing the contract safely

Callers are other teams' code. Treat the contract in `gateway-api.md` as published:

| Safe (just do it, document it) | Breaking (needs a new method or version) |
|---|---|
| new method | renaming or removing a method |
| new **optional** biz field | new **required** field, tighter validation |
| new response field | renaming or removing a response field, changing its type |
| new error code | changing an existing code or its HTTP status |

For a breaking change, add a new method (`pos.point.create2`, or better a clearer name) or a new
version: add `'2.0'` to `api.gateway.versions` and branch in the method on `$version`. The version
isn't passed to methods yet, so you would add it to the `GatewayMethod` interface. Keep the old
behaviour until the callers have moved.

## 14. Not built yet

- **IP allow-list per client.** Planned: a nullable `allowed_ips` JSON column on `api_clients`, checked
  with `Symfony\Component\HttpFoundation\IpUtils::checkIp($ip, $list)` at step 5 in `GatewayKernel::handle()`
  (the comment marks the spot), failing with 401 `IP_NOT_ALLOWED` and a log line. Add the field to the
  API Clients form. Behind a proxy, set trusted proxies first, or `$request->ip()` is the proxy's address.
  `Modules/Partner/app/Http/Middleware/AuthenticatePartner.php` already does the same check.
- **Per-client method permissions** (today every active client can call every method).
- **`notify_url` callbacks**: accepted and ignored.
- **Paging for `pos.merchant.list`**: it returns every active merchant in one response.
- **Request log / audit table** of gateway calls. Today only signature failures and 500s are logged.

## 15. Checklist before you open a PR

- [ ] Logic is in a module service; the method only validates, calls and maps
- [ ] `rules()` covers every field read; values cast in `handle()`
- [ ] Writes are idempotent (a caller key such as `reference`, or the same record again) and return `replayed`
- [ ] Domain exceptions mapped to specific `GatewayError` codes with the right HTTP status
- [ ] Output is explicit fields or a Resource; no branch codes, payouts or secrets (asserted in a test)
- [ ] Lists use `GatewayCustomers::page()` with a stable order
- [ ] Registered in `GatewayMethods::MAP`
- [ ] Tests: success, each error code, signed response, absent fields
- [ ] `docs/gateway-api.md` updated (method row, new error codes); this guide if the rules changed
- [ ] Request added to `docs/postman/pos-gateway.postman_collection.json`
- [ ] `vendor/bin/pint` and `php artisan test` pass
