<?php

namespace Laravel\CashierChargebee\Concerns;

trait HandlesTaxes
{
    /**
     * The IP address of the customer used to determine the tax location.
     */
    public ?string $customerIpAddress;

    /**
     * Set the IP address of the customer used to determine the tax location.
     */
    public function withTaxIpAddress(?string $ipAddress): self
    {
        $this->customerIpAddress = $ipAddress;

        return $this;
    }
}
