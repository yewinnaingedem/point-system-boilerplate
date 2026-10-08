<?php

namespace Modules\Customer\Tests\Feature;

use Carbon\CarbonImmutable;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Customer\Models\Customer;
use Modules\Loyalty\Services\TierQualificationEngine;
use Tests\TestCase;

/**
 * Sign-in from the partner project: it signs a short-lived JWT, the app exchanges it here.
 */
class CustomerSsoTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'a-shared-secret-of-at-least-32-characters!!';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'customer.sso.secret' => self::SECRET,
            'customer.sso.issuer' => 'https://shop.test',
            'customer.sso.audience' => 'https://pos.test',
        ]);
    }

    public function test_first_sign_in_creates_the_customer_at_silver_and_returns_a_long_lived_token(): void
    {
        $response = $this->sso($this->jwt())->assertCreated()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.new_customer', true)
            ->assertJsonPath('data.customer.name', 'Aye Aye')
            ->assertJsonPath('data.customer.tier.level', 'silver')
            ->assertJsonPath('data.customer.tier.next.level', 'gold')
            ->assertJsonPath('data.customer.points', 0);

        $this->assertTrue(CarbonImmutable::parse($response->json('data.expires_at'))->isAfter(now()->addDays(89)));
        $customer = Customer::query()->sole();
        $this->assertSame('shop-42', $customer->external_id);
        $this->assertSame('enrolled', $customer->tierEvents()->sole()->transition->value);

        $this->getJson('/api/v1/customer/me', $this->bearer($response->json('data.token')))
            ->assertOk()->assertJsonPath('data.email', 'aye@shop.test')->assertJsonPath('data.tier.label', 'Silver');
    }

    public function test_signing_in_again_updates_the_profile_and_replaces_the_device_token(): void
    {
        $first = $this->sso($this->jwt())->json('data.token');
        $this->sso($this->jwt(['name' => 'Aye Aye Maung', 'phone' => '0911']))->assertOk()->assertJsonPath('data.new_customer', false);

        $customer = Customer::query()->sole();
        $this->assertSame('Aye Aye Maung', $customer->name);
        $this->assertSame('0911', $customer->phone);
        $this->assertSame(1, $customer->tokens()->count(), 'same device name keeps one token');
        $this->getJson('/api/v1/customer/me', $this->bearer($first))->assertUnauthorized();
    }

    public function test_bad_tokens_are_refused_with_one_generic_message(): void
    {
        $cases = [
            'forged' => JWT::encode($this->claims(), str_repeat('x', 40), 'HS256'),
            'expired' => $this->jwt(['iat' => time() - 600, 'exp' => time() - 300]),
            'wrong issuer' => $this->jwt(['iss' => 'https://evil.test']),
            'wrong audience' => $this->jwt(['aud' => 'https://other.test']),
            'lives too long' => $this->jwt(['exp' => time() + 86400]),
            'no subject' => $this->jwt(['sub' => '']),
            'garbage' => 'not-a-jwt',
        ];
        foreach ($cases as $case => $jwt) {
            $this->sso($jwt)->assertUnprocessable()
                ->assertJsonPath('errors.token.0', 'This sign-in link is invalid or has expired. Please sign in again.');
        }
        $this->assertSame(0, Customer::query()->count(), 'nothing created');
    }

    public function test_a_sign_in_token_works_only_once(): void
    {
        $jwt = $this->jwt();

        $this->sso($jwt)->assertCreated();
        $this->sso($jwt)->assertUnprocessable();
    }

    public function test_sign_in_is_off_until_a_strong_secret_is_configured(): void
    {
        config(['customer.sso.secret' => 'short']);

        $this->sso(JWT::encode($this->claims(), 'short', 'HS256'))->assertUnprocessable();
    }

    public function test_a_deactivated_customer_is_cut_off(): void
    {
        $token = $this->sso($this->jwt())->json('data.token');
        Customer::query()->sole()->update(['is_active' => false]);

        $this->getJson('/api/v1/customer/me', $this->bearer($token))->assertUnauthorized();
        $this->sso($this->jwt())->assertUnprocessable();
    }

    public function test_tier_history_lists_changes_newest_first(): void
    {
        $token = $this->sso($this->jwt())->json('data.token');
        app(TierQualificationEngine::class)->processTransaction(Customer::query()->sole()->id, 500000, now());

        $this->getJson('/api/v1/customer/tier-history', $this->bearer($token))->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.transition', 'upgraded')
            ->assertJsonPath('data.0.from', 'silver')
            ->assertJsonPath('data.0.to', 'gold')
            ->assertJsonPath('data.1.transition', 'enrolled');

        $this->getJson('/api/v1/customer/me', $this->bearer($token))
            ->assertJsonPath('data.tier.level', 'gold')->assertJsonPath('data.tier.next.level', 'platinum');
    }

    public function test_logout_revokes_the_token(): void
    {
        $token = $this->sso($this->jwt())->json('data.token');

        $this->postJson('/api/v1/customer/auth/logout', [], $this->bearer($token))->assertOk();
        $this->getJson('/api/v1/customer/me', $this->bearer($token))->assertUnauthorized();
    }

    /** @return array<string, mixed> */
    private function claims(array $override = []): array
    {
        return [
            'iss' => 'https://shop.test', 'aud' => 'https://pos.test', 'sub' => 'shop-42',
            'name' => 'Aye Aye', 'email' => 'aye@shop.test', 'phone' => '0999',
            'iat' => time(), 'exp' => time() + 120, 'jti' => (string) Str::uuid(),
            ...$override,
        ];
    }

    private function jwt(array $override = []): string
    {
        return JWT::encode($this->claims($override), self::SECRET, 'HS256');
    }

    private function sso(string $jwt)
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/v1/customer/auth/sso', ['token' => $jwt, 'device_name' => 'Aye phone']);
    }

    private function bearer(string $token): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => "Bearer {$token}"];
    }
}
