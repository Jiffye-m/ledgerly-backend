<?php

namespace App\Providers;

use App\Models\Business;
use App\Models\BusinessMember;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

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
        // Resolved by ResolveBusinessContext (the 'has.business' middleware)
        // from the X-Business-Id header. Available uniformly on both
        // controllers ($request->business()) and FormRequest classes
        // ($this->business(), since FormRequest extends Request) — one
        // macro, no separate helper needed in each place.
        Request::macro('business', function (): ?Business {
            /** @var Request $this */
            return $this->attributes->get('business');
        });

        Request::macro('membership', function (): ?BusinessMember {
            /** @var Request $this */
            return $this->attributes->get('membership');
        });
    }
}
