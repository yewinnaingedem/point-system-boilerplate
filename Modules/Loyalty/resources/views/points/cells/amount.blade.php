<span @class(['font-weight-bold', 'text-success' => $points > 0, 'text-danger' => $points < 0])>{{ ($points > 0 ? '+' : '').number_format($points) }}</span>
