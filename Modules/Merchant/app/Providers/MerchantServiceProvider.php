<?php

namespace Modules\Merchant\Providers;

use App\Support\Menu\MenuGroup;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use Modules\Merchant\Console\DemoDataCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class MerchantServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Merchant';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'merchant';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        DemoDataCommand::class,
    ];

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
            ->addGroup(new MenuGroup('merchants', 'Merchants', 'fas fa-store', 'Sales', 60))
            ->add(new MenuItem('Merchants', 'admin.merchants.index', 'fas fa-store-alt', 'Sales', 'view-merchant', 10, 'admin.merchants.*', 'merchants'))
            ->add(new MenuItem('Redemptions', 'admin.redemptions.index', 'fas fa-gift', 'Sales', 'view-redemption', 20, 'admin.redemptions.*', 'merchants'));
    }
}
