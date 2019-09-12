<?php

namespace Appocular\Assessor\Providers;

use Appocular\Assessor\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Boot the authentication services for the application.
     *
     * @return void
     */
    public function boot()
    {
        // Here you may define how you wish users to be authenticated for your Lumen
        // application. The callback which receives the incoming request instance
        // should return either a User instance or null. You're free to obtain
        // the User instance via an API token or any other method necessary.

        $this->app['auth']->viaRequest('frontend', function ($request) {
            if ($request->bearerToken() && $request->bearerToken() == env('FRONTEND_TOKEN')) {
                return new class implements \Illuminate\Contracts\Auth\Authenticatable {
                    public function getAuthIdentifierName()
                    {
                        return 'name';
                    }
                    public function getAuthIdentifier()
                    {
                        return 'frontend';
                    }
                    public function getAuthPassword()
                    {
                    }
                    public function getRememberToken()
                    {
                    }
                    public function setRememberToken($value)
                    {
                    }
                    public function getRememberTokenName()
                    {
                    }
                };
            }
        });
    }
}
