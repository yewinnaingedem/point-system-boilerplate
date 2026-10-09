<?php

namespace Modules\Api\Gateway;

use Illuminate\Database\Eloquent\Builder;
use Modules\Customer\Models\Customer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Methods act for one customer, named by `external_id` (the partner project's id). The calling
 * system is trusted to act for its own customers; this only checks they exist and are active.
 */
class GatewayCustomers
{
    public const PAGE_SIZE = 20;

    /** @var list<string> */
    public const EXTERNAL_ID = ['required', 'string', 'max:100'];

    /** @var list<string> */
    public const PAGE = ['nullable', 'integer', 'min:1', 'max:10000'];

    /** @throws GatewayError */
    public function find(string $externalId): Customer
    {
        return Customer::query()->where('external_id', $externalId)->first()
            ?? throw GatewayError::notFound('CUSTOMER_NOT_FOUND', __('No customer with this external_id.'));
    }

    /** @throws GatewayError */
    public function active(string $externalId): Customer
    {
        $customer = $this->find($externalId);
        if (! $customer->is_active) {
            throw new GatewayError('CUSTOMER_INACTIVE', __('This customer is deactivated.'), Response::HTTP_FORBIDDEN);
        }

        return $customer;
    }

    /**
     * The profile to save for CustomerDirectory::upsert(), which replaces name / email / phone.
     * A field the caller didn't send keeps the customer's current value; one sent empty clears it.
     *
     * @param  array<string, mixed>  $input  validated biz_content
     * @return array{external_id: string, name: string, email: ?string, phone: ?string}
     */
    public function profile(array $input, ?Customer $existing): array
    {
        $profile = ['external_id' => $input['external_id']];
        foreach (['name', 'email', 'phone'] as $field) {
            $profile[$field] = array_key_exists($field, $input) && ($field !== 'name' || filled($input['name']))
                ? $input[$field]
                : $existing?->{$field};
        }

        return $profile;
    }

    /**
     * One page of a list, newest first as the query orders it.
     *
     * @param  callable(mixed): array<string, mixed>  $map
     * @return array{items: list<array<string, mixed>>, page: int, per_page: int, has_more: bool}
     */
    public function page(Builder $query, ?int $page, callable $map): array
    {
        $page = max(1, (int) $page);
        $rows = $query->forPage($page, self::PAGE_SIZE)->limit(self::PAGE_SIZE + 1)->get();

        return [
            'items' => $rows->take(self::PAGE_SIZE)->map($map)->values()->all(),
            'page' => $page,
            'per_page' => self::PAGE_SIZE,
            'has_more' => $rows->count() > self::PAGE_SIZE,
        ];
    }
}
