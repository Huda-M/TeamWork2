<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;
use OpenApi\Analysers\AnnotationAnalyser;
use L5Swagger\Generator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Custom password reset URL
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url') . "/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        // Set Swagger annotation analyser only in local environment
        if ($this->app->environment('local')) {
            $this->app->extend(Generator::class, function ($generator, $app) {
                $generator->setAnalyser(new AnnotationAnalyser());
                return $generator;
            });
        }
    }
}
