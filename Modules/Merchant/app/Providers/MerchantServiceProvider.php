<?php

namespace Modules\Merchant\Providers;

use App\Support\Menu\MenuGroup;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use Modules\Merchant\Console\DemoDataCommand;
use Modules\Merchant\Models\Merchant;
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

        // The Users form shows a "Merchant" select for a merchant's own staff.
        $this->app['view']->composer('access::users.form', fn ($view) => $view->with(
            'merchants', Merchant::query()->orderBy('name')->pluck('name', 'id')
        ));

        $this->app->make(MenuRegistry::class)
            ->addGroup(new MenuGroup('merchants', 'Merchants', 'fas fa-store', 'Management', 60))
            ->add(new MenuItem('Merchants', 'admin.merchants.index', 'fas fa-store-alt', 'Management', 'view-merchant', 10, 'admin.merchants.*', 'merchants'));
    }
}
