<?php

namespace Laravel\CashierChargebee\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\CashierChargebee\Cashier;
use Laravel\CashierChargebee\Subscription;
use Laravel\CashierChargebee\SubscriptionBuilder;

trait ManagesSubscriptions
{
    /**
     * Begin creating a new subscription.
     */
    public function newSubscription(string $type, string|array $prices = []): SubscriptionBuilder
    {
        return new SubscriptionBuilder($this, $type, $prices);
    }

    /**
     * Get a subscription instance by $type.
     */
    public function subscription(string $type = 'default'): Subscription
    {
        return $this->subscriptions->where('type', $type)->first();
    }

    /**
     * Get all of the subscriptions for the Chargebee model.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Cashier::$subscriptionModel, $this->getForeignKey())->orderBy('created_at', 'desc');
    }
}