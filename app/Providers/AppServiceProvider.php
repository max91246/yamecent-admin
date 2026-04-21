<?php

namespace App\Providers;

use App\AdminUser;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Opcodes\LogViewer\Facades\LogViewer;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);

        // Log Viewer：只允許超級管理員存取
        LogViewer::auth(function ($request) {
            $admin = $request->session()->get('admin');
            return $admin instanceof AdminUser && $admin->hasSuperRole();
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
