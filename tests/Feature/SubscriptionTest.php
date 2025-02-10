<?php

namespace Laravel\CashierChargebee\Tests\Feature;

use Carbon\Carbon;
use ChargeBee\ChargeBee\Models\Item;
use ChargeBee\ChargeBee\Models\ItemFamily;
use ChargeBee\ChargeBee\Models\ItemPrice;
use ChargeBee\ChargeBee\Models\PaymentSource;
use ChargeBee\ChargeBee\Models\Subscription as ChargebeeSubscription;
use Illuminate\Database\Eloquent\Model;
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
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$euroPriceId)
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

        $subscription = $user->newSubscription('main', static::$euroPriceId)
            ->add();

        $updateOptions = [
            'subscriptionItems' => [
                [
                    'itemPriceId' => static::$euroPriceId,
                    'quantity' => 4,
                    'unitPrice' => 2000,
                ]
            ],
        ];

        $updatedSubscription = $subscription->updateChargebeeSubscription($updateOptions);
        
        $this->assertSame(static::$euroPriceId, $updatedSubscription->subscriptionItems[0]->itemPriceId);
        $this->assertSame(4, $updatedSubscription->subscriptionItems[0]->quantity);
        $this->assertSame(2000, $updatedSubscription->subscriptionItems[0]->unitPrice);
    }

    public function test_subscription_can_be_cancelled_at_the_end_of_the_billing_period(): void
    {
        $user = $this->createCustomer('subscription_can_be_cancelled');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$euroPriceId)
            ->create($paymentSource);
        
        $this->assertSame('active', $subscription->chargebee_status);

        $subscription->cancel();

        $retrievedSubscription = $subscription->asChargebeeSubscription();

        $this->assertSame($retrievedSubscription->status, $subscription->chargebee_status);
        $this->assertEquals(Carbon::createFromTimestamp($retrievedSubscription->currentTermEnd), $subscription->ends_at);
    }

    public function test_subscription_can_be_cancelled_at_specific_date(): void
    {
        $user = $this->createCustomer('subscription_can_be_cancelled');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$euroPriceId)
            ->create($paymentSource);
        
        $this->assertSame('active', $subscription->chargebee_status);

        $subscription->cancelAt(Carbon::now()->addDay());

        $retrievedSubscription = $subscription->asChargebeeSubscription();

        $this->assertSame($retrievedSubscription->status, $subscription->chargebee_status);
        $this->assertEquals(Carbon::createFromTimestamp($retrievedSubscription->cancelledAt), $subscription->ends_at);
    }

    public function test_subscription_can_be_cancelled_now(): void
    {
        $user = $this->createCustomer('subscription_can_be_cancelled');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$euroPriceId)
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
        $user = $this->createCustomer('subscription_can_be_cancelled');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$euroPriceId)
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
        $user = $this->createCustomer('subscription_can_be_resumed');
        $user->createAsChargebeeCustomer();
        $paymentSource = $this->createCard($user);

        $subscription = $user->newSubscription('main', static::$euroPriceId)
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

        $user->newSubscription('main', static::$euroPriceId)
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

        $subscription = $user->newSubscription('main', static::$euroPriceId)
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

        $subscription = $user->newSubscription('main', static::$euroPriceId)
            ->trialDays(10)
            ->create($paymentSource);

        $this->assertSame('in_trial', $subscription->asChargebeeSubscription()->status);

        $subscription->endTrial();

        $this->assertNull($subscription->trial_ends_at);
        $this->assertSame('active', $subscription->asChargebeeSubscription()->status);
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
