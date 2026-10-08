<?php

namespace Modules\GiftCard\Providers;

use App\Support\Menu\MenuGroup;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
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

        $this->app->make(MenuRegistry::class)
            ->addGroup(new MenuGroup('gift-cards', 'Gift Cards', 'fas fa-gift', 'Sales', 70))
            ->add(new MenuItem('Gift Cards', 'admin.gift-cards.index', 'fas fa-credit-card', 'Sales', 'view-giftcard', 10, 'admin.gift-cards.*', 'gift-cards'))
            ->add(new MenuItem('Exchanges', 'admin.gift-card-exchanges.index', 'fas fa-exchange-alt', 'Sales', 'view-giftcardexchange', 20, 'admin.gift-card-exchanges.*', 'gift-cards'));
    }
}
