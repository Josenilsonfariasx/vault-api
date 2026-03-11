<?php

namespace Tests\Feature;

use App\Http\Middleware\ResolveTenant;
use App\Models\Tenant;
use App\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Tests\ResetTenantContext;
use Tests\TestCase;

class ResolveTenantMiddlewareTest extends TestCase
{
    use RefreshDatabase, ResetTenantContext;

    public function test_it_returns_400_when_tenant_header_is_missing(): void
    {
        $middleware = new ResolveTenant();
        $request = Request::create('/api/account/balance', 'GET');

        $response = TestResponse::fromBaseResponse($middleware->handle($request, function () {
            return response()->json(['ok' => true]);
        }));

        $response
            ->assertStatus(400)
            ->assertJson([
                'message' => 'X-Tenant-Slug header is required.',
            ]);
    }

    public function test_it_returns_404_when_tenant_slug_does_not_exist(): void
    {
        $middleware = new ResolveTenant();
        $request = Request::create('/api/account/balance', 'GET');
        $request->headers->set('X-Tenant-Slug', 'missing-tenant');

        $response = TestResponse::fromBaseResponse($middleware->handle($request, function () {
            return response()->json(['ok' => true]);
        }));

        $response
            ->assertStatus(404)
            ->assertJson([
                'message' => 'Tenant not found.',
            ]);
    }

    public function test_it_resolves_the_tenant_and_exposes_it_in_the_request_context(): void
    {
        $middleware = new ResolveTenant();
        $tenant = Tenant::create([
            'name' => 'Operadora Alpha',
            'slug' => 'alpha',
        ]);

        $request = Request::create('/api/account/balance', 'GET');
        $request->headers->set('X-Tenant-Slug', 'alpha');

        $response = TestResponse::fromBaseResponse($middleware->handle($request, function () {
            $tenantContext = app(TenantContext::class);

            return response()->json([
                'tenant_id' => $tenantContext->getId(),
                'tenant_slug' => $tenantContext->getSlug(),
            ]);
        }));

        $response
            ->assertOk()
            ->assertJson([
                'tenant_id' => $tenant->id,
                'tenant_slug' => 'alpha',
            ]);
    }
}