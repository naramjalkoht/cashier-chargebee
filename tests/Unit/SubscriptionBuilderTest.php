<?php

namespace Laravel\CashierChargebee\Tests\Unit;

use Carbon\Carbon;
use Laravel\CashierChargebee\SubscriptionBuilder;
use Laravel\CashierChargebee\Tests\Fixtures\User;
use Laravel\CashierChargebee\Tests\TestCase;

class SubscriptionBuilderTest extends TestCase
{
    public function test_it_can_be_instantiated(): void
    {
        $builder = new SubscriptionBuilder(new User, 'default', [
            'price_foo',
            ['itemPriceId' => 'price_bux'],
            ['itemPriceId' => 'price_bar', 'quantity' => 1],
            ['itemPriceId' => 'price_baz', 'quantity' => 2],
        ]);

        $this->assertSame([
            'price_foo' => ['itemPriceId' => 'price_foo', 'quantity' => 1],
            'price_bux' => ['itemPriceId' => 'price_bux', 'quantity' => 1],
            'price_bar' => ['itemPriceId' => 'price_bar', 'quantity' => 1],
            'price_baz' => ['itemPriceId' => 'price_baz', 'quantity' => 2],
        ], $builder->getItems());
    }

    public function test_price(): void
    {
        $builder = new SubscriptionBuilder(new User, 'default');
        $builder->price('price_xyz', 2);

        $this->assertSame([
            'price_xyz' => ['itemPriceId' => 'price_xyz', 'quantity' => 2],
        ], $builder->getItems());
    }

    public function test_metered_price(): void
    {
        $builder = new SubscriptionBuilder(new User, 'default');
        $builder->meteredPrice('metered_price_abc');

        $this->assertSame([
            'metered_price_abc' => ['itemPriceId' => 'metered_price_abc'],
        ], $builder->getItems());
    }

    public function test_quantity(): void
    {
        $builder = new SubscriptionBuilder(new User, 'default', 'price_123');
        $builder->quantity(5);

        $this->assertSame([
            'price_123' => ['itemPriceId' => 'price_123', 'quantity' => 5],
        ], $builder->getItems());
    }

    public function test_trial_days(): void
    {
        $builder = new SubscriptionBuilder(new User, 'default');
        $builder->trialDays(10);

        $expectedDate = Carbon::now()->addDays(10);
        $actualDate = $this->getProtectedProperty($builder, 'trialExpires');

        $this->assertInstanceOf(Carbon::class, $actualDate);
        $this->assertSame($expectedDate->toDateString(), $actualDate->toDateString());
    }

    public function test_trial_until(): void
    {
        $trialUntil = Carbon::now()->addDays(15);
        $builder = new SubscriptionBuilder(new User, 'default');
        $builder->trialUntil($trialUntil);

        $this->assertSame($trialUntil, $this->getProtectedProperty($builder, 'trialExpires'));
    }

    public function test_skip_trial(): void
    {
        $builder = new SubscriptionBuilder(new User, 'default');
        $builder->skipTrial();

        $this->assertTrue($this->getProtectedProperty($builder, 'skipTrial'));
    }

    public function test_anchor_billing_cycle_on(): void
    {
        $date = Carbon::now()->addDays(30)->getTimestamp();
        $builder = new SubscriptionBuilder(new User, 'default');
        $builder->anchorBillingCycleOn($date);

        $this->assertSame($date, $this->getProtectedProperty($builder, 'billingCycleAnchor'));
    }

    public function test_with_metadata(): void
    {
        $metadata = ['key1' => 'value1', 'key2' => 'value2'];
        $builder = new SubscriptionBuilder(new User, 'default');
        $builder->withMetadata($metadata);

        $this->assertSame($metadata, $this->getProtectedProperty($builder, 'metadata'));
    }
}
