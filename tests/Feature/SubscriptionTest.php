<?php

namespace Laravel\CashierChargebee\Tests\Feature;

use Carbon\Carbon;
use ChargeBee\ChargeBee\Exceptions\InvalidRequestException;
use ChargeBee\ChargeBee\Models\Coupon;
use ChargeBee\ChargeBee\Models\Item;
use ChargeBee\ChargeBee\Models\ItemFamily;
use ChargeBee\ChargeBee\Models\ItemPrice;
use ChargeBee\ChargeBee\Models\PaymentSource;
use ChargeBee\ChargeBee\Models\Subscription as ChargebeeSubscription;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SubscriptionTest extends FeatureTestCase
{
    /**
     * @var string
     */
    protected static $itemFamilyId;

    /**
     * @var string
     */
    protected static $firstItemId;

    /**
     * @var string
     */
    protected static $secondItemId;

    /**
     * @var string
     */
    protected static $thirdItemId;

    /**
     * @var string
     */
    protected static $firstMeteredItemId;

    /**
     * @var string
     */
    protected static $secondMeteredItemId;

    /**
     * @var string
     */
    protected static $firstPriceId;

    /**
     * @var string
     */
    protected static $secondPriceId;

    /**
     * @var string
     */
    protected static $thirdPriceId;

    /**
     * @var string
     */
    protected static $firstMeteredPriceId;

    /**
     * @var string
     */
    protected static $secondMeteredPriceId;

    /**
     * @var string
     */
    protected static $couponId;

    protected function setUp(): void
    {
        parent::setUp();

        static::$itemFamilyId = ItemFamily::create(array(
            'id' => Str::random(40),
            'name' => Str::random(40),
        ))->itemFamily()->id;

        static::$firstItemId = Item::create(array(
            'id' => Str::random(40),
            'name' => Str::random(40),
            'type' => 'plan',
            'itemFamilyId' => static::$itemFamilyId,
        ))->item()->id;

        static::$firstPriceId = ItemPrice::create(array(
            'id' => Str::random(40),
            'itemId' => static::$firstItemId,
            'name' => Str::random(40),
            'pricingModel' => 'per_unit',
            'price' => 5000,
            'externalName' => 'Test ItemPrice 1',
            'periodUnit' => 'month',
            'period' => 1,
            'currencyCode' => 'EUR',
        ))->itemPrice()->id;

        static::$secondItemId = Item::create(array(
            'id' => Str::random(40),
            'name' => Str::random(40),
            'type' => 'addon',
            'itemFamilyId' => static::$itemFamilyId,
        ))->item()->id;

        static::$secondPriceId = ItemPrice::create(array(
            'id' => Str::random(40),
            'itemId' => static::$secondItemId,
            'name' => Str::random(40),
            'pricingModel' => 'per_unit',
            'price' => 2000,
            'externalName' => 'Test ItemPrice 2',
            'periodUnit' => 'month',
            'period' => 1,
            'currencyCode' => 'EUR',
        ))->itemPrice()->id;

        static::$thirdItemId = Item::create(array(
            'id' => Str::random(40),
            'name' => Str::random(40),
            'type' => 'plan',
            'itemFamilyId' => static::$itemFamilyId,
        ))->item()->id;

        static::$thirdPriceId = ItemPrice::create(array(
            'id' => Str::random(40),
            'itemId' => static::$thirdItemId,
            'name' => Str::random(40),
            'pricingModel' => 'per_unit',
            'price' => 3000,
            'externalName' => 'Test ItemPrice 3',
            'periodUnit' => 'month',
            'period' => 1,
            'currencyCode' => 'EUR',
        ))->itemPrice()->id;

        static::$firstMeteredItemId = Item::create(array(
            'id' => Str::random(40),
            'name' => Str::random(40),
            'type' => 'plan',
            'itemFamilyId' => static::$itemFamilyId,
            'metered' => true,
        ))->item()->id;

        static::$firstMeteredPriceId = ItemPrice::create(array(
            'id' => Str::random(40),
            'itemId' => static::$firstMeteredItemId,
            'name' => Str::random(40),
            'pricingModel' => 'per_unit',
            'price' => 5000,
            'externalName' => 'Test metered ItemPrice 1',
            'periodUnit' => 'month',
            'period' => 1,
            'currencyCode' => 'EUR',
        ))->itemPrice()->id;

        static::$secondMeteredItemId = Item::create(array(
            'id' => Str::random(40),
            'name' => Str::random(40),
            'type' => 'addon',
            'itemFamilyId' => static::$itemFamilyId,
            'metered' => true,
        ))->item()->id;

        static::$secondMeteredPriceId = ItemPrice::create(array(
            'id' => Str::random(40),
            'itemId' => static::$secondMeteredItemId,
            'name' => Str::random(40),
            'pricingModel' => 'per_unit',
            'price' => 1000,
            'externalName' => 'Test metered ItemPrice 2',
            'periodUnit' => 'month',
            'period' => 1,
            'currencyCode' => 'EUR',
        ))->itemPrice()->id;

        static::$couponId = Coupon::createForItems(array(
            'id' => Str::random(40),
            'name' => Str::random(40),
            'discountPercentage' => 10,
            'discountType' => 'PERCENTAGE',
            'durationType' => 'FOREVER',
            'applyOn' => 'EACH_SPECIFIED_ITEM',
            'itemConstraints' => [
                [
                    'constraint' => 'ALL',
                    'itemType' => 'PLAN',
                ]
            ]
        ))->coupon()->id;
    }

    public function test_subscription_can_be_created_and_status_synced(): void
    {
        $user = $this->createCustomer('test_subscription_can_be_created');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->create($paymentSource);

        $this->assertEquals(1, count($user->subscriptions));
        $this->assertNotNull(($subscription = $user->subscription('main'))->chargebee_id);

        $retrievedSubscription = $subscription->asChargebeeSubscription();
        $this->assertSame($subscription->chargebee_id, $retrievedSubscription->id);

        $subscription->chargebee_status = null;
        $subscription->syncChargebeeStatus();
        $this->assertSame($retrievedSubscription->status, $subscription->chargebee_status);
    }

    public function test_subscriptions_can_be_updated(): void
    {
        $user = $this->createCustomer('subscriptions_can_be_updated');

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->add();

        $updateOptions = [
            'subscriptionItems' => [
                [
                    'itemPriceId' => static::$firstPriceId,
                    'quantity' => 4,
                    'unitPrice' => 2000,
                ]
            ],
        ];

        $updatedSubscription = $subscription->updateChargebeeSubscription($updateOptions);
        
        $this->assertSame(static::$firstPriceId, $updatedSubscription->subscriptionItems[0]->itemPriceId);
        $this->assertSame(4, $updatedSubscription->subscriptionItems[0]->quantity);
        $this->assertSame(2000, $updatedSubscription->subscriptionItems[0]->unitPrice);
    }

    public function test_subscription_can_be_cancelled_at_the_end_of_the_billing_period(): void
    {
        $user = $this->createCustomer('test_subscription_can_be_cancelled');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->create($paymentSource);
        
        $this->assertSame('active', $subscription->chargebee_status);

        $subscription->cancel();

        $retrievedSubscription = $subscription->asChargebeeSubscription();

        $this->assertSame($retrievedSubscription->status, $subscription->chargebee_status);
        $this->assertEquals(Carbon::createFromTimestamp($retrievedSubscription->currentTermEnd), $subscription->ends_at);
    }

    public function test_subscription_can_be_cancelled_at_specific_date(): void
    {
        $user = $this->createCustomer('test_subscription_can_be_cancelled');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->create($paymentSource);
        
        $this->assertSame('active', $subscription->chargebee_status);

        $subscription->cancelAt(Carbon::now()->addDay());

        $retrievedSubscription = $subscription->asChargebeeSubscription();

        $this->assertSame($retrievedSubscription->status, $subscription->chargebee_status);
        $this->assertEquals(Carbon::createFromTimestamp($retrievedSubscription->cancelledAt), $subscription->ends_at);
    }

    public function test_subscription_can_be_cancelled_now(): void
    {
        $user = $this->createCustomer('test_subscription_can_be_cancelled');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->create($paymentSource);
        
        $this->assertSame('active', $subscription->chargebee_status);

        $subscription->cancelNow();

        $retrievedSubscription = $subscription->asChargebeeSubscription();

        $this->assertSame('cancelled', $retrievedSubscription->status);
        $this->assertEquals('cancelled', $subscription->chargebee_status);
        $this->assertTrue($subscription->ends_at->isToday());
    }

    public function test_subscription_can_be_cancelled_now_and_invoiced(): void
    {
        $user = $this->createCustomer('test_subscription_can_be_cancelled');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->create($paymentSource);
        
        $this->assertSame('active', $subscription->chargebee_status);

        $subscription->cancelNowAndInvoice();

        $retrievedSubscription = $subscription->asChargebeeSubscription();

        $this->assertSame('cancelled', $retrievedSubscription->status);
        $this->assertEquals('cancelled', $subscription->chargebee_status);
        $this->assertTrue($subscription->ends_at->isToday());
    }

    public function test_subscription_can_be_resumed(): void
    {
        $user = $this->createCustomer('test_subscription_can_be_resumed');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->create($paymentSource);

        ChargebeeSubscription::pause($subscription->chargebee_id, [
            "pauseOption" => "immediately"
        ])->subscription();

        $subscription->syncChargebeeStatus();

        $this->assertSame('paused', $subscription->chargebee_status);

        $subscription->resume();

        $this->assertSame('active', $subscription->chargebee_status);
    }

    public function test_create_subscription_with_trial(): void
    {
        $user = $this->createCustomer('test_create_subscription_with_trial');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $user->newSubscription('main', static::$firstPriceId)
            ->trialDays(7)
            ->create($paymentSource);

        $subscription = $user->subscription('main');

        $this->assertTrue($subscription->onTrial());
        $this->assertSame('in_trial', $subscription->asChargebeeSubscription()->status);
        $this->assertEquals(Carbon::today()->addDays(7)->day, $user->trialEndsAt('main')->day);
    }

    public function test_trial_can_be_extended(): void
    {
        $user = $this->createCustomer('test_trial_can_be_extended');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->trialDays(7)
            ->create($paymentSource);

        $this->assertSame('in_trial', $subscription->asChargebeeSubscription()->status);

        $subscription->extendTrial($trialEndsAt = now()->addDays(8)->floor());

        $this->assertSame('in_trial', $subscription->asChargebeeSubscription()->status);
        $this->assertTrue($trialEndsAt->equalTo($subscription->trial_ends_at));
        $this->assertEquals($subscription->asChargebeeSubscription()->trialEnd, $trialEndsAt->getTimestamp());
    }

    public function test_trial_can_be_ended(): void
    {
        $user = $this->createCustomer('test_trial_can_be_ended');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->trialDays(10)
            ->create($paymentSource);

        $this->assertSame('in_trial', $subscription->asChargebeeSubscription()->status);

        $subscription->endTrial();

        $this->assertNull($subscription->trial_ends_at);
        $this->assertSame('active', $subscription->asChargebeeSubscription()->status);
    }

    public function test_price_can_be_added_and_removed(): void
    {
        $user = $this->createCustomer('test_price_can_be_added_and_removed');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->create($paymentSource);

        // Test adding second price (with default quantity = 1)
        $subscription->addPrice(static::$secondPriceId);

        $this->assertCount(2, $subscription->items);
        $this->assertTrue($subscription->items->pluck('chargebee_price')->contains(static::$firstPriceId));
        $this->assertTrue($subscription->items->pluck('chargebee_price')->contains(static::$secondPriceId));

        $chargebeeSubscription = $subscription->asChargebeeSubscription();
        $chargebeeSubscriptionItems = collect($chargebeeSubscription->subscriptionItems);

        $this->assertCount(2, $chargebeeSubscriptionItems);
        $this->assertTrue($chargebeeSubscriptionItems->pluck('itemPriceId')->contains(static::$firstPriceId));

        $secondPriceItem = $chargebeeSubscriptionItems->firstWhere('itemPriceId', static::$secondPriceId);
        $this->assertNotNull($secondPriceItem);
        $this->assertEquals(1, $secondPriceItem->quantity);

        // Test removing price
        $subscription->removePrice(static::$secondPriceId);
        $chargebeeSubscription = $subscription->asChargebeeSubscription();

        $this->assertCount(1, $subscription->items);
        $this->assertEquals(static::$firstPriceId, $subscription->items->first()->chargebee_price);

        $this->assertCount(1, $chargebeeSubscription->subscriptionItems);
        $this->assertEquals(static::$firstPriceId, $chargebeeSubscription->subscriptionItems[0]->itemPriceId);
    }

    public function test_metered_price_can_be_added_and_removed(): void
    {
        $user = $this->createCustomer('test_metered_price_can_be_added');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main')
            ->meteredPrice(static::$firstMeteredPriceId)
            ->create($paymentSource);

        // Test adding metered price
        $subscription->addMeteredPrice(static::$secondMeteredPriceId);
        $chargebeeSubscription = $subscription->asChargebeeSubscription();

        $this->assertCount(2, $subscription->items);
        $this->assertTrue($subscription->items->pluck('chargebee_price')->contains(static::$firstMeteredPriceId));
        $this->assertTrue($subscription->items->pluck('chargebee_price')->contains(static::$secondMeteredPriceId));

        $chargebeeSubscription = $subscription->asChargebeeSubscription();
        $chargebeeSubscriptionItems = collect($chargebeeSubscription->subscriptionItems);

        $this->assertCount(2, $chargebeeSubscriptionItems);
        $this->assertTrue($chargebeeSubscriptionItems->pluck('itemPriceId')->contains(static::$firstMeteredPriceId));

        $secondPriceItem = $chargebeeSubscriptionItems->firstWhere('itemPriceId', static::$secondMeteredPriceId);
        $this->assertNotNull($secondPriceItem);
        $this->assertNull($secondPriceItem->quantity ?? null);

        // Test removing metered price
        $subscription->removePrice(static::$secondMeteredPriceId);
        $chargebeeSubscription = $subscription->asChargebeeSubscription();

        $this->assertCount(1, $subscription->items);
        $this->assertEquals(static::$firstMeteredPriceId, $subscription->items->first()->chargebee_price);

        $this->assertCount(1, $chargebeeSubscription->subscriptionItems);
        $this->assertEquals(static::$firstMeteredPriceId, $chargebeeSubscription->subscriptionItems[0]->itemPriceId);
    }

    public function test_usage_can_be_reported_and_retrieved(): void
    {
        $user = $this->createCustomer('test_usage_can_be_reported_and_retrieved');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->meteredPrice(static::$secondMeteredPriceId)
            ->create($paymentSource);

        try {
            $subscription->reportUsage();
        } catch (Exception $e) {
            $this->assertInstanceOf(InvalidArgumentException::class, $e);

            $this->assertSame(
                'This method requires a price argument since the subscription has multiple prices.', $e->getMessage()
            );
        }

        $subscription->reportUsageFor(static::$secondMeteredPriceId, 20);
        $usage = $subscription->usageRecordsFor(static::$secondMeteredPriceId)->first();

        $this->assertEquals(20, $usage->quantity);

        try {
            $subscription->reportUsageFor(static::$firstPriceId);
        } catch (Exception $e) {
            $this->assertInstanceOf(InvalidRequestException::class, $e);
        }
    }

    public function test_coupon_can_be_applied(): void
    {
        $user = $this->createCustomer('test_usage_can_be_reported_and_retrieved');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->create($paymentSource);

        $subscription->applyCoupon(static::$couponId);
        $chargebeeSubscription = $subscription->asChargebeeSubscription();

        $coupons = $chargebeeSubscription->coupons;
        
        $this->assertCount(1, $coupons);
        $this->assertEquals(static::$couponId, $coupons[0]->couponId);
    }

    public function test_item_quantity_can_be_updated_from_subscription(): void
    {
        $user = $this->createCustomer('test_item_quantity_can_be_updated');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main')
            ->price(static::$firstPriceId, 2)
            ->create($paymentSource);

        // Initial quantity = 2
        $this->assertCount(1, $subscription->items);
        $this->assertEquals(2, $subscription->items->first()->quantity);
        $this->assertEquals(2, $subscription->quantity);

        // Decrement quantity by 1
        $subscription->decrementQuantity(1);
        $subscription->refresh();

        $this->assertEquals(1, $subscription->items->first()->quantity);
        $this->assertEquals(1, $subscription->quantity); 

        $chargebeeSubscription = $subscription->asChargebeeSubscription();
        $chargebeeItem = collect($chargebeeSubscription->subscriptionItems)->firstWhere('itemPriceId', static::$firstPriceId);
        $this->assertEquals(1, $chargebeeItem->quantity);

        // Add second price and increment its quantity by 2
        $subscription->addPrice(static::$secondPriceId);
        $subscription->incrementAndInvoice(2, static::$secondPriceId);
        $subscription->refresh();

        $this->assertCount(2, $subscription->items);

        $firstItem = $subscription->items->firstWhere('chargebee_price', static::$firstPriceId);
        $secondItem = $subscription->items->firstWhere('chargebee_price', static::$secondPriceId);

        $this->assertEquals(1, $firstItem->quantity);
        $this->assertEquals(3, $secondItem->quantity);
        $this->assertNull($subscription->quantity);

        $chargebeeSubscription = $subscription->asChargebeeSubscription();
        $chargebeeFirstItem = collect($chargebeeSubscription->subscriptionItems)->firstWhere('itemPriceId', static::$firstPriceId);
        $chargebeeSecondItem = collect($chargebeeSubscription->subscriptionItems)->firstWhere('itemPriceId', static::$secondPriceId);

        $this->assertEquals(1, $chargebeeFirstItem->quantity);
        $this->assertEquals(3, $chargebeeSecondItem->quantity);

        // Now that there are two prices we can't use updateQuantity without $price parameter
        $this->expectException(InvalidArgumentException::class);
        $subscription->updateQuantity(1);
    }

    public function test_item_quantity_can_be_updated_from_subscription_item(): void
    {
        $user = $this->createCustomer('test_item_quantity_can_be_updated');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main')
            ->price(static::$firstPriceId, 2)
            ->create($paymentSource);

        $firstItem = $subscription->items->first();

        // Initial quantity = 2
        $this->assertCount(1, $subscription->items);
        $this->assertEquals(2, $firstItem->quantity);
        $this->assertEquals(2, $subscription->quantity);

        // Decrement quantity by 1
        $firstItem->decrementQuantity(1);
        $subscription->refresh();
        $firstItem->refresh();

        $this->assertEquals(1, $firstItem->quantity);
        $this->assertEquals(1, $subscription->quantity);

        $chargebeeSubscription = $subscription->asChargebeeSubscription();
        $chargebeeItem = collect($chargebeeSubscription->subscriptionItems)->firstWhere('itemPriceId', static::$firstPriceId);
        $this->assertEquals(1, $chargebeeItem->quantity);

        // Add second price and increment its quantity by 2
        $subscription->addPrice(static::$secondPriceId);

        $secondItem = $subscription->items->firstWhere('chargebee_price', static::$secondPriceId);
        $secondItem->incrementAndInvoice(2);
        $subscription->refresh();
        $secondItem->refresh();

        $this->assertCount(2, $subscription->items);

        $this->assertEquals(1, $firstItem->quantity);
        $this->assertEquals(3, $secondItem->quantity);
        $this->assertNull($subscription->quantity);

        $chargebeeSubscription = $subscription->asChargebeeSubscription();
        $chargebeeFirstItem = collect($chargebeeSubscription->subscriptionItems)->firstWhere('itemPriceId', static::$firstPriceId);
        $chargebeeSecondItem = collect($chargebeeSubscription->subscriptionItems)->firstWhere('itemPriceId', static::$secondPriceId);

        $this->assertEquals(1, $chargebeeFirstItem->quantity);
        $this->assertEquals(3, $chargebeeSecondItem->quantity);
    }

    public function test_swapping_subscription_and_preserving_quantity()
    {
        $user = $this->createCustomer('test_swapping_subscription');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->quantity(5, static::$firstPriceId)
            ->create($paymentSource);

        $subscription = $subscription->swap(static::$thirdPriceId);

        $this->assertCount(1, $subscription->items);
        $this->assertEquals(static::$thirdPriceId, $subscription->chargebee_price);
        $this->assertEquals(5, $subscription->quantity);

        $item = $subscription->items->first();

        $this->assertEquals(static::$thirdPriceId, $item->chargebee_price);
        $this->assertEquals(5, $item->quantity);

        $chargebeeItem = $subscription->items->first()->asChargebeeSubscriptionItem();

        $this->assertEquals(static::$thirdPriceId, $chargebeeItem->itemPriceId);
        $this->assertEquals(5, $chargebeeItem->quantity);
    }

    public function test_swapping_subscription_and_adopting_new_quantity()
    {
        $user = $this->createCustomer('test_swapping_subscription');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->quantity(5, static::$firstPriceId)
            ->create($paymentSource);

        $subscription = $subscription->swap([static::$thirdPriceId => ['quantity' => 3]]);

        $this->assertCount(1, $subscription->items);
        $this->assertEquals(static::$thirdPriceId, $subscription->chargebee_price);
        $this->assertEquals(3, $subscription->quantity);

        $item = $subscription->items->first();

        $this->assertEquals(static::$thirdPriceId, $item->chargebee_price);
        $this->assertEquals(3, $item->quantity);

        $chargebeeItem = $subscription->items->first()->asChargebeeSubscriptionItem();

        $this->assertEquals(static::$thirdPriceId, $chargebeeItem->itemPriceId);
        $this->assertEquals(3, $chargebeeItem->quantity);
    }

    public function test_swapping_subscription_item_and_preserving_quantity()
    {
        $user = $this->createCustomer('test_swapping_subscription_item');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->quantity(5, static::$firstPriceId)
            ->create($paymentSource);

        $item = $subscription->items->first()->swap(static::$thirdPriceId);
        $subscription->refresh();

        $this->assertCount(1, $subscription->items);
        $this->assertEquals(static::$thirdPriceId, $subscription->chargebee_price);
        $this->assertEquals(5, $subscription->quantity);
        $this->assertEquals(static::$thirdPriceId, $item->chargebee_price);
        $this->assertEquals(5, $item->quantity);

        $chargebeeItem = $subscription->items->first()->asChargebeeSubscriptionItem();

        $this->assertEquals(static::$thirdPriceId, $chargebeeItem->itemPriceId);
        $this->assertEquals(5, $chargebeeItem->quantity);
    }

    public function test_swapping_subscription_item_and_adopting_new_quantity()
    {
        $user = $this->createCustomer('test_swapping_subscription_item');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$firstPriceId)
            ->quantity(5, static::$firstPriceId)
            ->create($paymentSource);

        $item = $subscription->items->first()->swap(static::$thirdPriceId, ['quantity' => 3]);
        $subscription->refresh();

        $this->assertCount(1, $subscription->items);
        $this->assertEquals(static::$thirdPriceId, $subscription->chargebee_price);
        $this->assertEquals(3, $subscription->quantity);
        $this->assertEquals(static::$thirdPriceId, $item->chargebee_price);
        $this->assertEquals(3, $item->quantity);

        $chargebeeItem = $subscription->items->first()->asChargebeeSubscriptionItem();

        $this->assertEquals(static::$thirdPriceId, $chargebeeItem->itemPriceId);
        $this->assertEquals(3, $chargebeeItem->quantity);
    }

    private function createCard(Model $user): ?PaymentSource
    {
        return PaymentSource::createCard([
            'customer_id' => $user->chargebeeId(),
            'card' => [
                'number' => '4111 1111 1111 1111',
                'cvv' => '123',
                'expiry_year' => date('Y', strtotime('+ 1 year')),
                'expiry_month' => date('m', strtotime('+ 1 year')),
            ],
        ]
        )->paymentSource();
    }
}
