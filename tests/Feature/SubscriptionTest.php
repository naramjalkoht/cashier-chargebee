<?php

namespace Laravel\CashierChargebee\Tests\Feature;

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

    public function test_subscriptions_can_be_created_and_retrieved()
    {
        $user = $this->createCustomer('subscriptions_can_be_created');

        $subscription = $user->newSubscription('main', static::$euroPriceId)
            ->create('pm_card_visa');

        $this->assertEquals(1, count($user->subscriptions));
        $this->assertNotNull(($subscription = $user->subscription('main'))->chargebee_id);

        $retrievedSubscription = $subscription->asChargebeeSubscription();
        $this->assertSame($subscription->chargebee_id, $retrievedSubscription->id);
    }
}
