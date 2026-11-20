<?php

namespace App\Support;

use App\Models\Business;
use Closure;

/** Request-scoped, fail-closed context. Never resolved from a submitted tenant ID. */
class Tenant
{
    private ?Business $business = null;

    public function business(): ?Business
    {
        return $this->business;
    }

    public function id(): ?int
    {
        return $this->business?->id;
    }

    public function set(?Business $business): void
    {
        $this->business = $business;
    }

    public function run(Business $business, Closure $callback): mixed
    {
        $previous = $this->business;
        try {
            $this->business = $business;

            return $callback();
        } finally {
            $this->business = $previous;
        }
    }
}
