# Partner API: award points (server to server)

The partner Laravel project (where customers shop) tells the POS system when a customer earned
points. This is **server to server**: the key never goes into an app or a browser.

## Configure

POS `.env`:

```dotenv
PARTNER_API_KEY=<random, at least 32 characters; same value in the partner's .env>
PARTNER_API_ALLOWED_IPS=203.0.113.10        # optional, comma-separated; empty = any address
PARTNER_API_RATE_PER_MINUTE=120
```

Generate a key with `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`. Until it is set, every
partner call gets `401` (and an error is logged).

## Award points

```http
POST /api/v1/partner/points
Authorization: Bearer <PARTNER_API_KEY>
Content-Type: application/json

{
  "customer": { "external_id": "42", "name": "Mya Mya", "email": "mya@shop.test", "phone": "0977..." },
  "points": 250,
  "reference": "ORDER-1001",
  "note": "Order ORDER-1001",
  "spent_amount": "125000.00"
}
```

- `customer.external_id`: the customer's id in the partner project (same as `sub` in the SSO token).
  An unknown customer is created and starts at Silver; name / email / phone are updated every time.
- `reference` (required): your order number or similar. **Idempotent**: sending the same request again
  (e.g. after a timeout) returns the first award with `200` and `"replayed": true`; nothing is added twice.
  The same reference with a different customer or number of points is `409`.
- `spent_amount` (optional): also counts towards the customer's tier this cycle.
- Points expire per the POS settings (Loyalty → points expire after / cutoff day); `expires_on` in the
  answer is the last day they can be used.

```json
201 { "data": { "reference": "ORDER-1001", "points": 250, "expires_on": "2027-03-31",
               "awarded_at": "2027-01-20T10:00:00+06:30", "replayed": false,
               "customer": { "external_id": "42", "balance": 250 } } }
```

Errors: `401` wrong/missing key or address not allowed, `409` reference reused, `422` validation or
deactivated customer, `429` rate limit.

```php
// partner project, e.g. after an order is paid
Http::withToken(config('services.pos.partner_key'))
    ->timeout(10)->retry(3, 500)               // safe to retry: the reference makes it idempotent
    ->post(config('services.pos.url').'/api/v1/partner/points', [
        'customer' => ['external_id' => (string) $user->id, 'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone],
        'points' => intdiv($order->total, 1000),
        'reference' => "ORDER-{$order->id}",
        'spent_amount' => (string) $order->total,
    ])->throw();
```

## Read a customer's points

```http
GET /api/v1/partner/customers/{external_id}/points
Authorization: Bearer <PARTNER_API_KEY>
```

```json
{ "data": { "external_id": "42", "active": true,
    "tier": { "level": "silver", "label": "Silver", "cycle_spent": "125000.00" },
    "balance": 250,
    "next_expiry": { "points": 250, "expires_on": "2027-03-31" },
    "by_expiry": [ { "points": 250, "expires_on": "2027-03-31" } ],
    "months": [ { "month": "2027-01", "earned": 250, "redeemed": 0, "reversed": 0, "adjusted_in": 0, "adjusted_out": 0, "expired": 0 } ] } }
```

The customer app gets the same balance / expiry / months data from `GET /api/v1/customer/points`.
