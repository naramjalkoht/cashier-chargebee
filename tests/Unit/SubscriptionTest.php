<?php

namespace Laravel\Cashier\Tests\Feature;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\CashierChargebee\Subscription;
use Laravel\CashierChargebee\SubscriptionItem;
use Laravel\CashierChargebee\Tests\Feature\FeatureTestCase;
use Laravel\CashierChargebee\Tests\Fixtures\User;

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

    public function test_user_relationship()
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create(['user_id' => $user->id]);

        $this->assertEquals($user->id, $subscription->user->id);
    }

    public function test_has_multiple_prices()
    {
        $subscription = Subscription::factory()->create(['chargebee_price' => null]);
        $this->assertTrue($subscription->hasMultiplePrices());
    }

    public function test_has_single_price()
    {
        $subscription = Subscription::factory()->create(['chargebee_price' => 'price_123']);
        $this->assertTrue($subscription->hasSinglePrice());
    }

    public function test_has_product()
    {
        $subscription = Subscription::factory()->create();
        $item = SubscriptionItem::factory()->create([
            'subscription_id' => $subscription->id,
            'chargebee_product' => 'product_123',
        ]);

        $this->assertTrue($subscription->hasProduct('product_123'));
        $this->assertFalse($subscription->hasProduct('product_456'));
    }

    public function test_has_price()
    {
        $subscription = Subscription::factory()->create(['chargebee_price' => null]);
        $item = SubscriptionItem::factory()->create([
            'subscription_id' => $subscription->id,
            'chargebee_price' => 'price_123',
        ]);

        $this->assertTrue($subscription->hasPrice('price_123'));
        $this->assertFalse($subscription->hasPrice('price_456'));
    }

    public function test_find_item_or_fail_with_existing_item()
    {
        $subscription = Subscription::factory()->create();
        $item = SubscriptionItem::factory()->create([
            'subscription_id' => $subscription->id,
            'chargebee_price' => 'price_123',
        ]);

        $foundItem = $subscription->findItemOrFail('price_123');
        $this->assertEquals($item->id, $foundItem->id);
    }

    public function test_find_item_or_fail_with_no_item()
    {
        $this->expectException(ModelNotFoundException::class);
        
        $subscription = Subscription::factory()->create();
        $subscription->findItemOrFail('nonexistent_price');
    }
}
