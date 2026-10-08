@php
    $styles = [
        'enrolled' => ['info', 'fa-user-plus'], 'upgraded' => ['success', 'fa-arrow-up'], 'requalified' => ['primary', 'fa-redo'],
        'protected' => ['warning', 'fa-shield-alt'], 'demoted' => ['danger', 'fa-arrow-down'],
    ];
    [$badge, $icon] = $styles[$transition->value] ?? ['secondary', 'fa-circle'];
@endphp
<span class="badge badge-{{ $badge }}"><i class="fas {{ $icon }} mr-1"></i>{{ __(ucfirst($transition->value)) }}</span>
