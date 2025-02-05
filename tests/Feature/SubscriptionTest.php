<?php

namespace Laravel\CashierChargebee\Tests\Feature;

use Carbon\Carbon;
use ChargeBee\ChargeBee\Models\Item;
use ChargeBee\ChargeBee\Models\ItemFamily;
use ChargeBee\ChargeBee\Models\ItemPrice;
use Illuminate\Support\Str;

class SubscriptionTest extends FeatureTestCase
{
    /**
     * @var string
     */
    protected static $itemFamilyId;

    /**
     * @var string
     */
    protected static $itemId;

    /**
     * @var string
     */
    protected static $euroPriceId;

    /**
     * @var string
     */
    protected static $usdPriceId;

    /**
     * @var string
     */
    protected static $yearlyPriceId;

    protected function setUp(): void
    {
        parent::setUp();

        static::$itemFamilyId = ItemFamily::create(array(
            'id' => Str::random(40),
            'name' => Str::random(40),
        ))->itemFamily()->id;

        static::$itemId = Item::create(array(
            'id' => Str::random(40),
            'name' => Str::random(40),
            'type' => 'plan',
            'itemFamilyId' => static::$itemFamilyId,
        ))->item()->id;

        static::$euroPriceId = ItemPrice::create(array(
            'id' => Str::random(40),
            'itemId' => static::$itemId,
            'name' => Str::random(40),
            'pricingModel' => 'per_unit',
            'price' => 1000,
            'externalName' => 'Test ItemPrice',
            'periodUnit' => 'month',
            'period' => 1,
            'currencyCode' => 'EUR',
        ))->itemPrice()->id;

        static::$usdPriceId = ItemPrice::create(array(
            'id' => Str::random(40),
            'itemId' => static::$itemId,
            'name' => Str::random(40),
            'pricingModel' => 'per_unit',
            'price' => 500,
            'externalName' => 'Test Second ItemPrice',
            'periodUnit' => 'month',
            'period' => 1,
            'currencyCode' => 'USD',
        ))->itemPrice()->id;

        static::$yearlyPriceId = ItemPrice::create(array(
            'id' => Str::random(40),
            'itemId' => static::$itemId,
            'name' => Str::random(40),
            'pricingModel' => 'per_unit',
            'price' => 2000,
            'externalName' => 'Test Premium ItemPrice',
            'periodUnit' => 'year',
            'period' => 1,
            'currencyCode' => 'EUR',
        ))->itemPrice()->id;
    }

    public function test_subscription_can_be_created_and_status_synced(): void
    {
        $user = $this->createCustomer('subscription_can_be_created');

        $subscription = $user->newSubscription('main', static::$euroPriceId)
            ->create('pm_card_visa');

        $this->assertEquals(1, count($user->subscriptions));
        $this->assertNotNull(($subscription = $user->subscription('main'))->chargebee_id);

        $retrievedSubscription = $subscription->asChargebeeSubscription();
        $this->assertSame($subscription->chargebee_id, $retrievedSubscription->id);

        $subscription->chargebee_status = null;
        $subscription->syncChargebeeStatus();
        $this->assertSame($retrievedSubscription->status, $subscription->chargebee_status);
    }

    // public function test_subscriptions_can_be_updated(): void
    // {
    //     $user = $this->createCustomer('subscriptions_can_be_updated');

    //     $subscription = $user->newSubscription('main', static::$euroPriceId)
    //         ->create('pm_card_visa');

    //     $updateOptions = [
    //         'subscriptionItems' => [
    //             [
    //                 'itemPriceId' => static::$euroPriceId,
    //                 'quantity' => 4,
    //                 'unitPrice' => 2000,
    //             ]
    //         ],
    //     ];

    //     $updatedSubscription = $subscription->updateChargebeeSubscription($updateOptions);
    //     $this->assertTrue(true);
    // }

    public function test_subscription_can_be_cancelled_at_the_end_of_the_billing_period(): void
    {
        $user = $this->createCustomer('subscription_can_be_cancelled');

        $subscription = $user->newSubscription('main', static::$euroPriceId)
            ->create('pm_card_visa');
        
        $this->assertSame('active', $subscription->chargebee_status);

        $subscription->cancel();

        $retrievedSubscription = $subscription->asChargebeeSubscription();

        $this->assertSame($retrievedSubscription->status, $subscription->chargebee_status);
        $this->assertEquals(Carbon::createFromTimestamp($retrievedSubscription->currentTermEnd), $subscription->ends_at);
    }

    public function test_subscription_can_be_cancelled_at_specific_date(): void
    {
        $user = $this->createCustomer('subscription_can_be_cancelled');

        $subscription = $user->newSubscription('main', static::$euroPriceId)
            ->create('pm_card_visa');
        
        $this->assertSame('active', $subscription->chargebee_status);

        $subscription->cancelAt(Carbon::now()->addDay());

        $retrievedSubscription = $subscription->asChargebeeSubscription();

        $this->assertSame($retrievedSubscription->status, $subscription->chargebee_status);
        $this->assertEquals(Carbon::createFromTimestamp($retrievedSubscription->cancelledAt), $subscription->ends_at);
    }

    public function test_subscription_can_be_cancelled_now(): void
    {
        $user = $this->createCustomer('subscription_can_be_cancelled');

        $subscription = $user->newSubscription('main', static::$euroPriceId)
            ->create('pm_card_visa');
        
        $this->assertSame('active', $subscription->chargebee_status);

        $subscription->cancelNow();

        $retrievedSubscription = $subscription->asChargebeeSubscription();

        $this->assertSame('cancelled', $retrievedSubscription->status);
        $this->assertEquals('cancelled', $subscription->chargebee_status);
        $this->assertTrue($subscription->ends_at->isToday());
    }

    public function test_subscription_can_be_cancelled_now_and_invoiced(): void
    {
        $user = $this->createCustomer('subscription_can_be_cancelled');

        $subscription = $user->newSubscription('main', static::$euroPriceId)
            ->create('pm_card_visa');
        
        $this->assertSame('active', $subscription->chargebee_status);

        $subscription->cancelNowAndInvoice();

        $retrievedSubscription = $subscription->asChargebeeSubscription();

        $this->assertSame('cancelled', $retrievedSubscription->status);
        $this->assertEquals('cancelled', $subscription->chargebee_status);
        $this->assertTrue($subscription->ends_at->isToday());
    }
}
