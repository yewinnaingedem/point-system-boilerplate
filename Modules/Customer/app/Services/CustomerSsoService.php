<?php

namespace Modules\Customer\Services;

use Carbon\CarbonImmutable;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Log;
use Modules\Customer\Exceptions\SsoTokenRejected;
use Modules\Customer\Models\Customer;
use Throwable;

/**
 * Single sign-on from the partner Laravel project.
 *
 * The partner signs a short-lived JWT (HS256, shared secret) for its logged-in customer:
 *   iss, aud, sub (their customer id), name, email, phone, iat, exp, jti.
 * We verify it, create or update the customer from its claims (so the partner stays the owner
 * of name / email / phone), enrol new customers at the base tier, and hand out our own
 * long-lived Sanctum token for the customer API. Each JWT works once (jti), so a leaked one
 * can't be replayed.
 */
class CustomerSsoService
{
    private const ALGORITHM = 'HS256';

    private const MIN_SECRET_LENGTH = 32;

    private const TOKEN_ABILITY = 'customer';

    public function __construct(
        private readonly Cache $cache,
        private readonly CustomerDirectory $customers,
    ) {}

    /**
     * @return array{customer: Customer, token: string, expires_at: CarbonImmutable, created: bool}
     *
     * @throws SsoTokenRejected
     */
    public function signIn(string $jwt, string $deviceName): array
    {
        $claims = $this->verify($jwt);

        [$customer, $created] = $this->customers->upsert($claims, ['last_login_at' => now()]);

        if (! $customer->is_active) {
            throw new SsoTokenRejected("Customer {$customer->id} is deactivated.");
        }

        [$token, $expiresAt] = $this->issueToken($customer, $deviceName);

        return ['customer' => $customer, 'token' => $token, 'expires_at' => $expiresAt, 'created' => $created];
    }

    /** Sign the customer out of every device (deactivation, or "log out everywhere"). */
    public function revokeAll(Customer $customer): int
    {
        return $customer->tokens()->delete();
    }

    /**
     * @return array{external_id: string, name: string, email: ?string, phone: ?string}
     *
     * @throws SsoTokenRejected
     */
    public function verify(string $jwt): array
    {
        $config = config('customer.sso');
        $secret = (string) $config['secret'];
        if (strlen($secret) < self::MIN_SECRET_LENGTH) {
            Log::error('CUSTOMER_SSO_SECRET is missing or shorter than '.self::MIN_SECRET_LENGTH.' characters; customer sign-in is disabled.');
            throw new SsoTokenRejected('SSO is not configured.');
        }

        JWT::$leeway = $config['leeway'];
        try {
            $claims = (array) JWT::decode($jwt, new Key($secret, self::ALGORITHM)); // signature, exp, nbf, iat
        } catch (Throwable $e) {
            throw new SsoTokenRejected("Invalid token: {$e->getMessage()}");
        }

        foreach (['iss', 'aud', 'sub', 'name', 'iat', 'exp', 'jti'] as $claim) {
            if (! isset($claims[$claim]) || $claims[$claim] === '') {
                throw new SsoTokenRejected("Missing claim {$claim}.");
            }
        }
        if ($config['issuer'] && $claims['iss'] !== $config['issuer']) {
            throw new SsoTokenRejected('Wrong issuer.');
        }
        if (! in_array($config['audience'], (array) $claims['aud'], true)) {
            throw new SsoTokenRejected('Wrong audience.');
        }
        if ((int) $claims['exp'] - (int) $claims['iat'] > $config['max_lifetime']) {
            throw new SsoTokenRejected('Token lives too long; sign-in tokens must be short-lived.');
        }

        // One use per token: remember its id until it would have expired anyway.
        $ttl = max(1, (int) $claims['exp'] - time() + $config['leeway']);
        if (! $this->cache->add('customer-sso-jti:'.sha1((string) $claims['jti']), true, $ttl)) {
            throw new SsoTokenRejected('Token already used.');
        }

        return [
            'external_id' => (string) $claims['sub'],
            'name' => (string) $claims['name'],
            'email' => isset($claims['email']) ? (string) $claims['email'] : null,
            'phone' => isset($claims['phone']) ? (string) $claims['phone'] : null,
        ];
    }

    /**
     * One token per device name (signing in again on a device replaces its token); at most
     * max_devices, the least recently used one goes first.
     *
     * @return array{0: string, 1: CarbonImmutable}
     */
    private function issueToken(Customer $customer, string $deviceName): array
    {
        $customer->tokens()->where('name', $deviceName)->delete();

        $keep = max(0, config('customer.max_devices') - 1);
        $customer->tokens()->orderByRaw('coalesce(last_used_at, created_at) desc')->orderByDesc('id')->skip($keep)->take(PHP_INT_MAX)
            ->get()->each->delete();

        $expiresAt = CarbonImmutable::now()->addDays(config('customer.token_ttl_days'));
        $token = $customer->createToken($deviceName, [self::TOKEN_ABILITY], $expiresAt);

        return [$token->plainTextToken, $expiresAt];
    }
}
