<x-layouts.admin :title="__('Loyalty Tiers')">
    <div class="card card-outline card-secondary" data-remember-card="loyalty.how-tiers-work">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-info-circle mr-1"></i> {{ __('How tiers work') }}</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="{{ __('Show / hide') }}"><i class="fas fa-minus"></i></button>
            </div>
        </div>
        <div class="card-body">
            <p>
                {{ trans_choice('{1} Each cycle lasts :count month.|[2,*] Each cycle lasts :count months.', $cycleMonths) }}
                @can('view-appsetting')
                    <a href="{{ route('admin.settings.edit', 'loyalty') }}">{{ __('Change') }}</a>
                @endcan
            </p>
            <ul class="pl-3 mb-0">
                <li>{{ __('Only spending in the current cycle counts; it starts again at zero each cycle.') }}</li>
                <li>{{ __('A member moves up as soon as their cycle spending reaches a threshold.') }}</li>
                <li>{{ __('Reaching a tier (or reaching it again) guarantees it for its guarantee period.') }}</li>
                <li>{{ __('Once the guarantee has ended, a member who has not reached the threshold in the current cycle drops to the tier their spending qualifies for.') }}</li>
                <li>{{ __('Changes apply from the next sale; guarantees already given are kept.') }}</li>
            </ul>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-medal mr-1"></i> {{ __('Tier qualification') }}</h3>
        </div>
        <div class="card-body p-0">
            <x-datatable id="tiers-table" :source="route('admin.loyalty.tiers.data')" :order="[]" :paging="false"
                         :empty="__('No tiers are configured.')" :loading="__('Loading tiers')" class="text-nowrap" :columns="[
                ['data' => 'tier', 'title' => __('Tier'), 'priority' => 1],
                ['data' => 'threshold', 'title' => __('Spending per cycle'), 'priority' => 3],
                ['data' => 'guarantee', 'title' => __('Guarantee')],
                ['data' => 'members', 'title' => __('Members'), 'class' => 'text-right'],
                ['data' => 'color', 'title' => __('Color')],
                ['data' => 'actions', 'title' => __('Actions'), 'class' => 'text-right', 'priority' => 2],
            ]" />
        </div>
    </div>
</x-layouts.admin>
