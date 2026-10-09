<?php

namespace Modules\Loyalty\Providers;

use App\Support\Menu\MenuGroup;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Loyalty\Console\EvaluateTiersCommand;
use Modules\Loyalty\Console\ExpirePointsCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class LoyaltyServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Loyalty';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'loyalty';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        EvaluateTiersCommand::class,
        ExpirePointsCommand::class,
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

    /**
     * Define module schedules.
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        // Sales evaluate on the spot; this catches rollovers and expiries of quiet members.
        $schedule->command('loyalty:evaluate-tiers')->hourly()->withoutOverlapping();
        // Just after midnight: points expire at the start of a month (end of the last counted month).
        $schedule->command('loyalty:expire-points')->dailyAt('00:10')->withoutOverlapping();
    }

    public function boot(): void
    {
        parent::boot();

        // Management: "Loyalty Tiers" on its own, and a "Points" group with every points screen.
        $this->app->make(MenuRegistry::class)
            ->add(new MenuItem('Loyalty Tiers', 'admin.loyalty.tiers.index', 'fas fa-medal', 'Management', 'view-loyaltytier', 50, 'admin.loyalty.tiers.*'))
            ->addGroup(new MenuGroup('points', 'Points', 'fas fa-coins', 'Management', 55))
            ->add(new MenuItem('Earned Points', 'admin.loyalty.points-earned.index', 'fas fa-arrow-down', 'Management', 'view-point', 10, 'admin.loyalty.points-earned.*', 'points'))
            ->add(new MenuItem('Customer Points', 'admin.loyalty.points.index', 'fas fa-wallet', 'Management', 'view-point', 20, 'admin.loyalty.points.*', 'points'))
            ->add(new MenuItem('Points Activity', 'admin.loyalty.points-activity.index', 'fas fa-exchange-alt', 'Management', 'view-point', 30, 'admin.loyalty.points-activity.*', 'points'))
            ->add(new MenuItem('Points Summary', 'admin.loyalty.points-summary.index', 'fas fa-chart-bar', 'Management', 'view-point', 40, 'admin.loyalty.points-summary.*', 'points'));
    }
}
