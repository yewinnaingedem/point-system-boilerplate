# Signed gateway API

> Building or changing the API in this project? See [`api-development-guide.md`](api-development-guide.md).
> Trying it: import [`postman/`](postman/) into Postman (it signs every request for you).

One endpoint, one signed envelope, many methods (the KBZPay `precreate` style). For other
**systems** (the partner project's backend, a website's server) to register customers, award
points, read tiers, exchange gift cards and use them at partner shops.

```
POST /api/v1/gateway
Content-Type: application/json
```

> The secret key signs requests, so it must stay on a **server**. A browser or mobile app must
> call its own backend, which signs and forwards the call. (The customer app can also use the
> token API, `/api/v1/customer/*`, after SSO: see `customer-sso.md`.)

## Getting a key

Admin → **Administration → API Clients** (`create-apiclient`) → *New API client*. You get an
`appid` (public, `pos…`, 32 chars) and a **secret key** (64 hex chars), shown **once**. Lost it?
*Rotate* makes a new one; the old one stops at once. A disabled or deleted client gets `INVALID_APPID`.

## Request

```json
{
  "Request": {
    "timestamp": "1791530225",
    "method": "pos.point.create",
    "nonce_str": "5K8264ILTKCH16CQ2502SI8ZNMTM67VS",
    "sign_type": "SHA256",
    "sign": "768E0C18F7FF0450B6A652000068980335E5DD1067FD276994116E6799EE9FCC",
    "version": "1.0",
    "biz_content": {
      "appid": "pos3k9x0d2m1q8w7e6r5t4y3u2i1o0pa",
      "external_id": "shop-7",
      "name": "Aung Aung",
      "points": "250",
      "reference": "ORDER-1001",
      "spent_amount": "250000"
    }
  }
}
```

| Field | Rule |
|---|---|
| `timestamp` | Unix time in **seconds**, 10 digits; at most 300 s from our clock (`API_GATEWAY_TIMESTAMP_TOLERANCE`) |
| `method` | one of the methods below |
| `nonce_str` | 16–32 letters/digits, **new for every request** (a reused one is `DUPLICATE_NONCE`) |
| `sign_type` | `SHA256` |
| `sign` | see "Signing" (64 hex chars, case doesn't matter) |
| `version` | `1.0` |
| `notify_url` | optional, accepted for KBZ compatibility, not used |
| `biz_content` | object: `appid` + the method's fields. **Flat**, every value a string (or integer) |

Send amounts and ids as strings (`"250"`, `"600000.50"`), like KBZPay.

## Signing

1. Take the `Request` object, without `sign` and `sign_type`.
2. Flatten nested keys with dots: `biz_content.appid`, `biz_content.points`.
3. Drop null and empty-string values.
4. Sort by key (byte order) and join as `key=value&key=value` (booleans as `true` / `false`).
5. Append `&key=<secret>`, SHA-256, **uppercase** hex.

```
biz_content.appid=pos3k…&biz_content.external_id=shop-7&biz_content.name=Aung Aung&biz_content.points=250
&biz_content.reference=ORDER-1001&biz_content.spent_amount=250000&method=pos.point.create
&nonce_str=5K8264ILTKCH16CQ2502SI8ZNMTM67VS&timestamp=1791530225&version=1.0&key=<secret>
```

(one line; values are not URL-encoded.)

## Response

Always `{"Response": {...}}`, signed the same way with your key once your `appid` is known
(`INVALID_APPID` and malformed bodies are unsigned). Check `sign` before trusting the answer.

```json
{
  "Response": {
    "result": "SUCCESS",
    "code": "0",
    "msg": "success",
    "method": "pos.point.create",
    "biz_content": { "reference": "ORDER-1001", "points": 250, "expires_on": "2027-03-31", "replayed": false,
                     "customer": { "external_id": "shop-7", "balance": 250 } },
    "nonce_str": "…",
    "timestamp": "1791530226",
    "sign_type": "SHA256",
    "sign": "…"
  }
}
```

Failures: `"result": "FAIL"`, a stable `code`, `msg`, and `errors` (field → messages) for validation.

| HTTP | `code` | When |
|---|---|---|
| 400 | `INVALID_REQUEST` | envelope missing/wrong (`errors` names the fields) |
| 400 | `UNKNOWN_METHOD` | |
| 401 | `INVALID_APPID` | unknown or disabled client |
| 401 | `INVALID_SIGNATURE` | wrong key, or anything changed after signing |
| 401 | `TIMESTAMP_EXPIRED` | clock off by more than 300 s |
| 409 | `DUPLICATE_NONCE` | `nonce_str` already used |
| 429 | `RATE_LIMITED` | more than `API_GATEWAY_RATE_PER_MINUTE` (300) per client + IP |
| 422 | `VALIDATION_FAILED` | biz_content fields (`errors`) |
| 404 | `CUSTOMER_NOT_FOUND`, `GIFT_CARD_NOT_FOUND`, `EXCHANGE_NOT_FOUND` | |
| 403 | `CUSTOMER_INACTIVE` | the customer was deactivated here |
| 409 | `REFERENCE_CONFLICT` | `pos.point.create`: reference used for a different award |
| 422 | `NOT_ENOUGH_POINTS`, `OUT_OF_STOCK`, `TIER`, `LIMIT_REACHED`, `UNAVAILABLE`, `NO_EMAIL`, `WRONG_CODE`, `CODE_EXPIRED`, `TOO_MANY_ATTEMPTS`, `NOT_PENDING` | gift card exchange / verify (the service's `reason`, uppercased) |
| 422 | `WRONG_SHOP` (card belongs to another merchant), `WRONG_CODE`, `TOO_MANY_ATTEMPTS` (5 wrong branch codes → 15 min lock-out), `ALREADY_USED`, `EXPIRED`, `NOT_ISSUED`, `BRANCH_UNAVAILABLE` | `pos.giftcard.use` |
| 500 | `SYSTEM_ERROR` | logged on our side; retry later |

A failed call may still have used up its `nonce_str`: retry with a new nonce and signature. Retrying
the *operation* is safe where it matters: `reference` (points) is idempotent, and using a gift card again at the same branch answers `replayed: true`.

## Methods (`biz_content` fields besides `appid`)

| Method | Fields | Returns |
|---|---|---|
| `pos.customer.register` | `external_id`, `name`, `email`?, `phone`? | `created`, `active`, `customer` (profile, points, tier). Creates at Silver, or updates the profile |
| `pos.customer.query` | `external_id` | `active`, `customer` |
| `pos.tier.query` | `external_id` | `tier`: level, label, color, guarantee, cycle (start, end, spent), next (threshold, remaining) |
| `pos.tier.history` | `external_id`, `page`? | page of tier events |
| `pos.point.create` | `external_id`, `points`, `reference`, `name`? (required for a new customer), `email`?, `phone`?, `note`?, `spent_amount`? | reference, points, expires_on, replayed, customer.balance |
| `pos.point.query` | `external_id`, `page`? | balance, next expiry, by expiry, months, `history` page |
| `pos.giftcard.list` | `external_id` | `items`: cards with `merchant` (`{id, name}`, or null = any partner shop), `available` + `reason` for this customer |
| `pos.giftcard.exchange` | `external_id`, `gift_card_id` | exchange: `status` `issued` (with `code`) or `pending` (code emailed to the customer) |
| `pos.giftcard.verify` | `external_id`, `exchange_id`, `code` (6 digits) | the issued exchange |
| `pos.giftcard.exchanges` | `external_id`, `page`? | page of issued / used / cancelled gift cards |
| `pos.giftcard.use` | `external_id`, `exchange_id`, `branch_id`, `code` (the **branch's** 6 digits, typed by shop staff) | the gift card, now `used`, with `used_at`, `used_at_merchant`, `used_at_branch`, `replayed` |
| `pos.merchant.list` | — | `items`: shops where gift cards can be used: active merchants with their active branches (never branch codes or payouts) |

Pages: `{"items": [...], "page": 1, "per_page": 20, "has_more": false}`.

### Gift card life cycle

```
pos.giftcard.exchange ──► issued (code GC-…, points taken) ──► pos.giftcard.use at a branch ──► used
        │                                                          (cashier types the branch code)
        └─ two-step card ──► pending ──► pos.giftcard.verify ──► issued
```

- **issued**: the customer owns the gift card; staff can still cancel it (points and stock back).
- **used**: completed at one branch; the card's value is owed to that merchant (claimed and paid later). It can't be
  cancelled or used again.
- **Which shops:** a card with a `merchant` works only at that merchant's active branches (`WRONG_SHOP` elsewhere, and that
  doesn't count as a wrong code); a card without one works at any active partner shop. Exchanges carry `for_merchant`.
- An expired card (`valid_days`) can't be used (`EXPIRED`).

`pos.point.create` without `name` only works for a customer we already have. With `name` it creates or
updates the customer. In both `pos.point.create` and `pos.customer.register`, a profile field you **leave out
keeps** its current value, and one you send **empty clears** it (the partner owns name / email / phone).

## Partner code (Laravel)

```php
// config/services.php: 'pos' => ['url' => env('POS_URL'), 'appid' => env('POS_APPID'), 'secret' => env('POS_SECRET')]

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PosGateway
{
    public function call(string $method, array $biz = []): array
    {
        $request = [
            'timestamp' => (string) time(),
            'method' => $method,
            'nonce_str' => Str::random(32),
            'version' => '1.0',
            'biz_content' => ['appid' => config('services.pos.appid')] + array_map('strval', $biz),
        ];
        $request['sign_type'] = 'SHA256';
        $request['sign'] = $this->sign($request);

        $body = Http::timeout(10)->post(rtrim(config('services.pos.url'), '/').'/api/v1/gateway', ['Request' => $request])
            ->json('Response') ?? throw new RuntimeException('POS gateway: no response');

        if (isset($body['sign']) && ! hash_equals($this->sign($body), $body['sign'])) {
            throw new RuntimeException('POS gateway: response signature mismatch');
        }

        return $body; // check $body['result'] === 'SUCCESS', then use $body['biz_content']
    }

    private function sign(array $envelope): string
    {
        $pairs = [];
        foreach (Arr::dot(Arr::except($envelope, ['sign', 'sign_type'])) as $key => $value) {
            if ($value === null || $value === '' || is_array($value)) {
                continue;
            }
            $pairs[$key] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }
        ksort($pairs, SORT_STRING);
        $string = implode('&', array_map(fn ($k, $v) => "{$k}={$v}", array_keys($pairs), $pairs));

        return strtoupper(hash('sha256', "{$string}&key=".config('services.pos.secret')));
    }
}

// app(PosGateway::class)->call('pos.point.create', ['external_id' => $user->id, 'name' => $user->name,
//     'points' => 250, 'reference' => "ORDER-{$order->id}", 'spent_amount' => $order->total]);
```

## Not built yet

- **IP allow-list per client**: the hook is in `GatewayKernel::handle()` (after the rate limit,
  before the signature check).
- Per-client method permissions (today every active client may call every method).
