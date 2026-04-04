<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
class Admin extends Authenticatable implements FilamentUser
{
    use SoftDeletes;
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }
    protected $fillable = [
        'name',
        'email',
        'mobile',
        'password',
        'is_active',
        'app_authentication_secret',
        'app_authentication_recovery_codes',
    ];

    protected $hidden = [
        'password',
        'app_authentication_secret',
        'app_authentication_recovery_codes',
    ];
    protected function casts()
    {
        return [
            'is_active' => 'boolean',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',


        ];
    }


    public function getAppAuthenticationSecret(): ?string
    {
        // This method should return the user's saved app authentication secret.

        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(?string $secret): void
    {
        // This method should save the user's app authentication secret.

        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        // In a user's authentication app, each account can be represented by a "holder name".
        // If the user has multiple accounts in your app, it might be a good idea to use
        // their email address as then they are still uniquely identifiable.

        return $this->email;
    }

    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        // This method should return the user's saved app authentication recovery codes.

        return $this->app_authentication_recovery_codes;
    }

    /**
     * @param  array<string> | null  $codes
     */
    public function saveAppAuthenticationRecoveryCodes(?array $codes): void
    {
        // This method should save the user's app authentication recovery codes.

        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }
}
