<?php

namespace Modules\Api\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\AppSetting\Services\SettingService;

/**
 * Settings a POS client needs to show prices and print receipts.
 *
 * @property SettingService $resource
 */
class SettingsResource extends JsonResource
{
    private const EXPOSED = [
        'app_name', 'company_name', 'company_phone', 'company_email', 'company_address',
        'currency_code', 'currency_symbol', 'currency_position', 'tax_rate',
        'invoice_prefix', 'receipt_footer', 'timezone', 'date_format',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $values = collect(self::EXPOSED)->mapWithKeys(fn (string $key) => [$key => $this->resource->get($key)])->all();

        return [
            ...$values,
            'tax_rate' => (float) $values['tax_rate'],
            'logo_url' => $this->resource->url('logo'),
        ];
    }
}
