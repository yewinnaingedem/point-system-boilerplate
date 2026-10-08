@can('edit-loyaltytier')
    <a href="{{ route('admin.loyalty.tiers.edit', $tier) }}" class="btn btn-info btn-sm" title="{{ __('Edit :tier', ['tier' => $tier->tier_level->label()]) }}"><i class="fas fa-pencil-alt"></i></a>
@endcan
