<?php

namespace Laravel\CashierChargebee\Tests\Feature;

use Laravel\CashierChargebee\Subscription;

class CheckoutTest extends FeatureTestCase
{
    public function test_customers_can_start_a_product_checkout_session()
    {
        $subscription = (new Subscription());
        $this->assertNotNull($subscription->checkoutDiscounts());
    }

    public function test_customers_can_start_a_product_checkout_session_with_a_coupon_applied()
    {
        $subscription = (new Subscription())->withCoupons(['TEST-123']);
        $this->assertNotNull($subscription->checkoutDiscounts());
    }
}
