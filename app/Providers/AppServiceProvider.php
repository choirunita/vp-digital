<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

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
        View::composer('layouts.app', function ($view): void {
            $pendingVendorCount = 0;
            if (auth()->check() && auth()->user()->role === 'checker') {
                $pendingVendorCount = DB::table('vendors')->where('status', 'PENDING')->count()
                    + DB::table('vendor_changes')->where('status', 'PENDING')->count();
            }
            $view->with('pendingVendorCount', $pendingVendorCount);
        });
    }
}
