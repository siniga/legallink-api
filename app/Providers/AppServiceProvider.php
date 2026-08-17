<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        JsonResource::withoutWrapping();
        Route::model('member', User::class);
        Route::bind('role', function (string $value) {
            $user = auth()->user();
            if (! $user?->firm_id) {
                abort(404);
            }

            return \App\Models\Role::query()
                ->where('firm_id', $user->firm_id)
                ->where(function ($query) use ($value) {
                    $query->where('slug', $value)->orWhere('id', $value);
                })
                ->firstOrFail();
        });
    }
}
