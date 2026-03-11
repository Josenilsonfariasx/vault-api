<?php

namespace Tests;

use App\TenantContext;

trait ResetTenantContext
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Reset TenantContext para cada teste
        app()->instance(TenantContext::class, new TenantContext());
    }
}
