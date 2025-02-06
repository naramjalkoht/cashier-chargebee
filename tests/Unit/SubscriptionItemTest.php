<?php

namespace Laravel\CashierChargebee\Tests\Unit;

use Laravel\CashierChargebee\Subscription;
use Laravel\CashierChargebee\SubscriptionItem;
use Laravel\CashierChargebee\Tests\Feature\FeatureTestCase;

class SubscriptionItemTest extends FeatureTestCase
{
    public function test_subscription_relationship(): void
    {
        $subscription = Subscription::factory()->create();
        $subscriptionItem = SubscriptionItem::factory()->create(['subscription_id' => $subscription->id]);

        $this->assertEquals($subscription->id, $subscriptionItem->subscription->id);
    }
}
