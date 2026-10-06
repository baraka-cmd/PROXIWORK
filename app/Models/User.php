<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function notificationPreference(): HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function hasRole(string|array $roles): bool
    {
        return $this->roles()->whereIn('name', (array) $roles)->exists();
    }

    public function hasAnyRole(array $roles): bool
    {
        return $this->hasRole($roles);
    }

    public function hasPermissionTo(string $permission): bool
    {
        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('name', $permission))
            ->exists();
    }

    public function assignRole(Role|string ...$roles): static
    {
        $roleIds = collect($roles)
            ->map(fn (Role|string $role) => $role instanceof Role
                ? $role->getKey()
                : Role::where('name', $role)->value('id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->roles()->syncWithoutDetaching($roleIds);

        return $this->refresh();
    }

    public function removeRole(Role|string ...$roles): static
    {
        $roleIds = collect($roles)
            ->map(fn (Role|string $role) => $role instanceof Role
                ? $role->getKey()
                : Role::where('name', $role)->value('id'))
            ->filter()
            ->all();

        $this->roles()->detach($roleIds);

        return $this->refresh();
    }
}
