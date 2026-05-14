<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Factory::guessFactoryNamesUsing(function (string $modelName): string {
            $shortName = class_basename($modelName);

            return "Database\\Factories\\{$shortName}Factory";
        });
    }
}
