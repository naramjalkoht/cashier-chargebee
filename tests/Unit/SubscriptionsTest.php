<?php

namespace Laravel\Cashier\Tests\Feature;

use Laravel\CashierChargebee\Subscription;
use Laravel\CashierChargebee\Tests\Feature\FeatureTestCase;

class SubscriptionsTest extends FeatureTestCase
{
    public function test_subscription_changes_can_be_prorated()
    {
        $subscription = new Subscription();
        $subscription->prorate();

        $this->assertEquals(true, $subscription->prorateBehavior());
    }

    public function test_no_prorate_on_subscription_create()
    {
        $subscription = new Subscription();
        $subscription->noProrate();

        $this->assertEquals(false, $subscription->prorateBehavior());
    }
}
