<?php

namespace App\Providers;

use App\Models\Parametre\Materiel;
use App\Observers\MaterielObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
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
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Materiel::observe(MaterielObserver::class);
    }
}
