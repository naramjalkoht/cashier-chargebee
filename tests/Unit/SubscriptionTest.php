<?php

namespace Laravel\Cashier\Tests\Feature;

use Laravel\CashierChargebee\Subscription;
use Laravel\CashierChargebee\Tests\Feature\FeatureTestCase;

class SubscriptionTest extends FeatureTestCase
{
    public function test_prorate_on_subscription_create()
    {
        $subscription = (new Subscription())->prorate();

        $this->assertEquals(true, $subscription->prorateBehavior());
    }

    public function test_no_prorate_on_subscription_create()
    {
        $subscription = (new Subscription())->noProrate();

        $this->assertEquals(false, $subscription->prorateBehavior());
    }

    public function test_prorate_behavior_on_subscription_create()
    {
        $subscription = (new Subscription())->setProrationBehavior(true);

        $this->assertEquals(true, $subscription->prorateBehavior());
    }
}
