<?php

namespace Modules\GiftCard\Database\Seeders;

use App\Support\Access\ModulePermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class GiftCardDatabaseSeeder extends ModulePermissionSeeder
{
    /** Role for a merchant's own staff (users linked to a merchant). */
    public const MERCHANT_ROLE = 'Merchant';

    /**
     * What a merchant's staff can do, on their own merchant only (LimitToOwnMerchant): see and
     * edit their shop and branches (create / delete-merchant are the branch actions for them),
     * see and change branch codes, and make claims. Never settle-merchantclaim.
     */
    private const MERCHANT_ROLE_PERMISSIONS = [
        'view-merchant', 'edit-merchant', 'create-merchant', 'delete-merchant',
        'view-merchantcode', 'edit-merchantcode',
        'view-merchantclaim', 'create-merchantclaim', 'edit-merchantclaim', 'delete-merchantclaim',
    ];

    protected function permissions(): array
    {
        return [
            'giftcard' => self::CRUD,
            'giftcardexchange' => ['view', 'create', 'cancel'], // create = exchange for a customer at the counter
            // claims: merchants (and our staff for them) create; only our staff settle (pay / reject)
            'merchantclaim' => [...self::CRUD, 'settle'],
        ];
    }

    public function run(): void
    {
        parent::run();

        // Starting grants only when the role is first made, so changes on the Roles screen stay.
        $role = Role::query()->where('name', self::MERCHANT_ROLE)->where('guard_name', 'web')->first();
        if ($role === null) {
            $role = Role::create(['name' => self::MERCHANT_ROLE, 'guard_name' => 'web']);
            $role->givePermissionTo(array_map(fn (string $name) => Permission::findOrCreate($name, 'web'), self::MERCHANT_ROLE_PERMISSIONS));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
