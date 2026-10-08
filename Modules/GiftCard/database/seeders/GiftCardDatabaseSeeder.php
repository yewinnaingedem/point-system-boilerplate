<?php

namespace Modules\GiftCard\Database\Seeders;

use App\Support\Access\ModulePermissionSeeder;

class GiftCardDatabaseSeeder extends ModulePermissionSeeder
{
    protected function permissions(): array
    {
        return [
            'giftcard' => self::CRUD,
            'giftcardexchange' => ['view', 'cancel'],
        ];
    }
}
