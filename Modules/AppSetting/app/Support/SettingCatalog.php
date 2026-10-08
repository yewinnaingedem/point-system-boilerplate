<?php

namespace Modules\AppSetting\Support;

use Illuminate\Support\Collection;

/**
 * Every setting the application knows about. To add a setting, add a field here:
 * the settings page, validation and the default value all come from this list.
 */
final class SettingCatalog
{
    private const HEX_COLOR = 'regex:/^#[0-9a-fA-F]{6}$/';

    private const DATE_FORMATS = [
        'd/m/Y' => '31/12/2026',
        'Y-m-d' => '2026-12-31',
        'd M Y' => '31 Dec 2026',
        'M d, Y' => 'Dec 31, 2026',
    ];

    private const LOYALTY_CYCLES = [
        '1' => '1 month',
        '2' => '2 months',
        '3' => '3 months',
        '6' => '6 months',
        '12' => '12 months',
    ];

    private const POINT_EXPIRY = [
        '0' => 'Never',
        '1' => '1 month',
        '2' => '2 months',
        '3' => '3 months',
        '6' => '6 months',
        '9' => '9 months',
        '12' => '12 months',
        '18' => '18 months',
        '24' => '24 months',
    ];

    /** @var Collection<string, SettingGroup>|null */
    private ?Collection $groups = null;

    /**
     * @return Collection<string, SettingGroup>
     */
    public function groups(): Collection
    {
        return $this->groups ??= collect($this->define())->keyBy('key');
    }

    public function group(string $key): ?SettingGroup
    {
        return $this->groups()->get($key);
    }

    public function field(string $key): ?SettingField
    {
        return $this->fields()->get($key);
    }

    /**
     * @return Collection<string, SettingField>
     */
    public function fields(): Collection
    {
        return $this->groups()->flatMap(fn (SettingGroup $group) => $group->fields)->keyBy('key');
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return $this->fields()->map(fn (SettingField $field) => $field->default)->all();
    }

    /**
     * @return list<SettingGroup>
     */
    private function define(): array
    {
        return [
            new SettingGroup('general', 'General', 'Business identity shown across the app and on receipts.', 'fas fa-store', [
                new SettingField('app_name', 'Application name', default: 'POS System', rules: ['string', 'max:100'], required: true),
                new SettingField('company_name', 'Business name', default: 'My Store', rules: ['string', 'max:150'], required: true),
                new SettingField('company_phone', 'Phone', rules: ['string', 'max:50']),
                new SettingField('company_email', 'Email', SettingField::EMAIL, rules: ['email', 'max:150']),
                new SettingField('company_address', 'Address', SettingField::TEXTAREA, rules: ['string', 'max:500'], wide: true),
                new SettingField('logo', 'Logo', SettingField::IMAGE, rules: ['image', 'max:2048'], help: 'PNG or SVG, up to 2 MB.'),
                new SettingField('favicon', 'Favicon', SettingField::IMAGE, rules: ['image', 'max:512'], help: 'Square image, up to 512 KB.'),
            ]),
            new SettingGroup('pos', 'Sales & Receipt', 'Currency, tax and what prints on a receipt.', 'fas fa-receipt', [
                new SettingField('currency_code', 'Currency code', default: 'MMK', rules: ['string', 'size:3'], required: true),
                new SettingField('currency_symbol', 'Currency symbol', default: 'Ks', rules: ['string', 'max:5'], required: true),
                new SettingField('currency_position', 'Symbol position', SettingField::SELECT, default: 'after', rules: ['in:before,after'], required: true, options: [
                    'before' => 'Before amount (Ks 1,000)',
                    'after' => 'After amount (1,000 Ks)',
                ]),
                new SettingField('tax_rate', 'Default tax rate (%)', SettingField::NUMBER, default: '0', rules: ['numeric', 'min:0', 'max:100'], required: true),
                new SettingField('invoice_prefix', 'Invoice prefix', default: 'INV-', rules: ['string', 'max:10'], required: true),
                new SettingField('receipt_footer', 'Receipt footer', SettingField::TEXTAREA, default: 'Thank you for shopping with us!', rules: ['string', 'max:500'], wide: true),
            ]),
            new SettingGroup('localization', 'Localization', 'Time zone and how dates are displayed.', 'fas fa-globe-asia', [
                new SettingField('timezone', 'Time zone', SettingField::SELECT, default: 'Asia/Yangon', rules: ['timezone'], required: true, options: $this->timezones()),
                new SettingField('date_format', 'Date format', SettingField::SELECT, default: 'd/m/Y', rules: ['in:'.implode(',', array_keys(self::DATE_FORMATS))], required: true, options: self::DATE_FORMATS),
            ]),
            new SettingGroup('loyalty', 'Loyalty', 'Tier qualification cycle and when points expire. Tier thresholds and guarantees are set on the Loyalty Tiers screen.', 'fas fa-medal', [
                new SettingField('loyalty_cycle_months', 'Cycle length', SettingField::SELECT, default: '1', rules: ['in:'.implode(',', array_keys(self::LOYALTY_CYCLES))], required: true, options: self::LOYALTY_CYCLES,
                    help: 'Spending resets at the start of every cycle. A change applies from each customer\'s next cycle.'),
                new SettingField('point_expiry_months', 'Points expire after', SettingField::SELECT, default: '12', rules: ['in:'.implode(',', array_keys(self::POINT_EXPIRY))], required: true, options: self::POINT_EXPIRY,
                    help: 'Months counted from the month the points were earned. A change applies to points earned afterwards.'),
                new SettingField('point_expiry_cutoff_day', 'Earning month counts until day', SettingField::NUMBER, default: '15', rules: ['integer', 'min:1', 'max:28'], required: true,
                    help: 'Earned on or before this day: the earning month is the first counted month. Later: counting starts next month. E.g. 2 months, day 15: earned 10 Jan → expire end of Feb; earned 20 Jan → end of Mar.'),
            ]),
            new SettingGroup('appearance', 'Appearance', 'Brand color used by buttons, links and highlights.', 'fas fa-palette', [
                new SettingField('primary_color', 'Primary color', SettingField::COLOR, default: '#007bff', rules: [self::HEX_COLOR], required: true),
            ]),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function timezones(): array
    {
        $zones = timezone_identifiers_list();

        return array_combine($zones, $zones);
    }
}
