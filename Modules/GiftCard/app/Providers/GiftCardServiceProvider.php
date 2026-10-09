<?php

namespace Modules\GiftCard\Providers;

use App\Support\Menu\MenuGroup;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use Modules\GiftCard\Enums\ExchangeStatus;
use Modules\GiftCard\Models\GiftCard;
use Nwidart\Modules\Support\ModuleServiceProvider;

class GiftCardServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'GiftCard';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'giftcard';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        // The merchant page lists that merchant's gift cards (the Merchant module doesn't know gift cards).
        $this->app['view']->composer('merchant::merchants.show', function ($view) {
            $view->with('merchantGiftCards', GiftCard::query()->where('merchant_id', $view->getData()['merchant']->id)
                ->withCount([
                    'exchanges as issued_count' => fn ($q) => $q->where('status', ExchangeStatus::Issued),
                    'exchanges as used_count' => fn ($q) => $q->where('status', ExchangeStatus::Used),
                ])->orderBy('points_cost')->get());
        });

        $this->app->make(MenuRegistry::class)
            ->addGroup(new MenuGroup('gift-cards', 'Gift Cards', 'fas fa-gift', 'Management', 70))
            ->add(new MenuItem('Gift Cards', 'admin.gift-cards.index', 'fas fa-credit-card', 'Management', 'view-giftcard', 10, 'admin.gift-cards.*', 'gift-cards'))
            ->add(new MenuItem('Exchanges', 'admin.gift-card-exchanges.index', 'fas fa-exchange-alt', 'Management', 'view-giftcardexchange', 20, 'admin.gift-card-exchanges.*', 'gift-cards'))
            // Under the Merchant module's "Merchants" group: what we owe them for used gift cards.
            ->add(new MenuItem('Claims', 'admin.claims.index', 'fas fa-file-invoice-dollar', 'Management', 'view-merchantclaim', 30, 'admin.claims.*', 'merchants'));
    }
}
