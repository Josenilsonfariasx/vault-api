<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->header('X-Tenant-Slug');

        if (!$slug) {
            return response()->json(['message' => 'X-Tenant-Slug header is required.'], 400);
        }

        $tenant = Tenant::where('slug', $slug)->first();

        if (!$tenant) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        app(TenantContext::class)->set($tenant);

        return $next($request);
    }
}
