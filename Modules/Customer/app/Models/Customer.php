<?php

namespace Modules\Customer\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Customer\Database\Factories\CustomerFactory;
use Modules\Loyalty\Models\MemberTierStatus;
use Modules\Loyalty\Models\PointAccount;
use Modules\Loyalty\Models\TierEvent;

/**
 * Someone who collects and spends points. Created and updated from the partner project's
 * sign-in token, never by hand, so `external_id` (their id over there) is the identity.
 *
 * @property string $external_id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property bool $is_active
 */
class Customer extends Model implements AuthenticatableContract
{
    // Authenticatable: Sanctum's guard only accepts token owners that implement the contract.
    // Customers have no password here; they sign in with the partner project's token.
    use Authenticatable, HasApiTokens, HasFactory, Notifiable;

    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }

    protected $fillable = ['external_id', 'name', 'email', 'phone', 'is_active', 'last_login_at'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_login_at' => 'immutable_datetime',
        ];
    }

    public function tierStatus(): HasOne
    {
        return $this->hasOne(MemberTierStatus::class);
    }

    public function tierEvents(): HasMany
    {
        return $this->hasMany(TierEvent::class);
    }

    public function pointAccount(): HasOne
    {
        return $this->hasOne(PointAccount::class);
    }

    /** Prefix search on name, email or phone (indexed), like the users list. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where(fn (Builder $q) => $q
            ->where('name', 'like', "{$term}%")
            ->orWhere('email', 'like', "{$term}%")
            ->orWhere('phone', 'like', "{$term}%")));
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)))->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    }
}
