# Customer sign-in from the partner project (SSO)

Customers live in the **partner Laravel project** (where they register and log in). This POS
system never asks them for a password: the partner signs a short-lived token for its
logged-in customer, the customer app sends it here once, and gets our own long-lived token.

```
Partner project (customer is logged in)
   │ 1. sign a JWT (HS256, shared secret, lives ≤ 5 min)
   ▼
Customer app ── 2. POST /api/v1/customer/auth/sso { token, device_name } ──► POS system
   │                                                     3. verify, create/update customer,
   │                                                        new customers start at Silver
   ◄──────────── 4. { token: "<our token>", expires_at (90 days), customer } ────┘
   │ 5. Authorization: Bearer <our token> on every /api/v1/customer/... call
```

## 1. Configure both sides

POS system `.env`:

```dotenv
CUSTOMER_SSO_SECRET=<same random string on both sides, at least 32 characters>
CUSTOMER_SSO_ISSUER=https://shop.example.com     # the partner's APP_URL
CUSTOMER_SSO_AUDIENCE=https://pos.example.com    # this system's APP_URL (default)
CUSTOMER_TOKEN_TTL_DAYS=90                        # how long our customer token lasts
CUSTOMER_MAX_DEVICES=5
```

Generate a secret with `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`. Until the secret
is set, customer sign-in is off (every attempt is refused and an error is logged).

## 2. Partner project: sign the token

```bash
composer require firebase/php-jwt
```

```php
use Firebase\JWT\JWT;
use Illuminate\Support\Str;

// e.g. in a controller the customer app calls right after the customer logs in
public function posToken(Request $request)
{
    $user = $request->user();
    $now = time();

    $jwt = JWT::encode([
        'iss' => config('app.url'),               // = CUSTOMER_SSO_ISSUER on the POS side
        'aud' => 'https://pos.example.com',       // = CUSTOMER_SSO_AUDIENCE on the POS side
        'sub' => (string) $user->id,              // your customer id: the identity on the POS side
        'name' => $user->name,
        'email' => $user->email,
        'phone' => $user->phone,
        'iat' => $now,
        'exp' => $now + 120,                      // short-lived: at most 5 minutes after iat
        'jti' => (string) Str::uuid(),            // unique: each token can be used once
    ], config('services.pos.sso_secret'), 'HS256');

    return ['token' => $jwt];
}
```

Required claims: `iss`, `aud`, `sub`, `name`, `iat`, `exp`, `jti`. `email` and `phone` are
optional. Name, email and phone are copied into the POS customer on **every** sign-in, so the
partner stays the owner of that data.

## 3. Customer app: exchange and use

```http
POST /api/v1/customer/auth/sso
Content-Type: application/json

{ "token": "<partner JWT>", "device_name": "Aye's iPhone" }
```

`201` for a new customer (`200` afterwards):

```json
{ "data": {
    "token": "12|abc...", "token_type": "Bearer", "expires_at": "2027-01-06T10:00:00+06:30",
    "new_customer": true,
    "customer": { "id": 1, "external_id": "42", "name": "Aye Aye", "email": "...", "phone": "...",
      "points": 0,
      "tier": { "level": "silver", "label": "Silver", "color": "#8e9aa6", "guarantee_expires_at": null,
        "cycle": { "start": "...", "end": "...", "spent": "0.00" },
        "next": { "level": "gold", "label": "Gold", "threshold": "500000.00", "remaining": 500000 } } } } }
```

Any problem with the partner token (bad signature, expired, wrong issuer/audience, lives too
long, missing claim, used twice, customer deactivated) is the same `422` with
`errors.token`: the reason is only written to the POS log. Rate limit: 30 sign-ins per minute per IP.

Then, with `Authorization: Bearer <our token>`:

| Method | Path | |
|---|---|---|
| GET | `/api/v1/customer/me` | profile, points, tier + progress to the next tier |
| GET | `/api/v1/customer/tier-history` | enrolled / upgraded / requalified / protected / demoted, newest first, paginated |
| GET | `/api/v1/customer/merchants` | where points can be spent (active branches + rewards) |
| GET | `/api/v1/customer/points` | balance + last 20 changes |
| GET / POST | `/api/v1/customer/redemptions` | own redemptions / redeem (`branch_id`, `reward_id`, `code`, optional `request_id`) |
| POST | `/api/v1/customer/auth/logout`, `/logout-all` | sign out this device / every device |

When our token expires (or after `401`), get a fresh partner JWT and call `/auth/sso` again:
that is the "auto login". One token per `device_name`; a 6th device pushes out the least recently used.

Staff tokens (POS app, `/api/v1/auth/login`) can't call customer endpoints and customer tokens
can't call staff endpoints (`403`).
