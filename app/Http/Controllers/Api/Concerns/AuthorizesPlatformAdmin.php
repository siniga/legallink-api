<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\Request;

trait AuthorizesPlatformAdmin
{
    private function authorizePlatform(Request $request): void
    {
        if (! $request->user()?->is_platform_admin) {
            abort(403, 'Platform administrator access required.');
        }
    }
}
