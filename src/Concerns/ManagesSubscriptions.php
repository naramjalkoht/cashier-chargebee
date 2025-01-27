<?php

namespace Laravel\CashierChargebee\Concerns;

use Laravel\CashierChargebee\SubscriptionBuilder;

trait ManagesSubscriptions
{
    /**
     * Begin creating a new subscription.
     *
     * @param  string  $type
     * @param  string|string[]  $prices
     * @return \Laravel\CashierChargebee\SubscriptionBuilder
     */
    public function newSubscription($type, $prices = [])
    {
        return new SubscriptionBuilder($this, $type, $prices);
    }
}
