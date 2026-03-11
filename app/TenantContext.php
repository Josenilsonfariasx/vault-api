<?php

namespace App;

use App\Models\Tenant;

class TenantContext
{
    protected ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function getId(): int
    {
        return $this->tenant?->id ?? 0;
    }

    public function getSlug(): string
    {
        return $this->tenant?->slug ?? '';
    }
}
