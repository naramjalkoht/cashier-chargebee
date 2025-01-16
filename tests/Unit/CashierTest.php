<?php

namespace Laravel\CashierChargebee\Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\CashierChargebee\Cashier;
use Laravel\CashierChargebee\Tests\Fixtures\User;
use Laravel\CashierChargebee\Tests\TestCase;

class CashierTest extends TestCase
{
    public function test_it_can_format_an_amount() : void
    {
        $this->assertSame('$10.00', Cashier::formatAmount(1000));
    }

    public function test_it_can_format_an_amount_without_digits() : void
    {
        $this->assertSame('$10', Cashier::formatAmount(1000, null, null, ['min_fraction_digits' => 0]));
    }

    public function test_it_can_format_an_amount_with_locale_and_currency() : void
    {
        $formatted = Cashier::formatAmount(1000, 'EUR', 'fr_FR');
        $this->assertStringContainsString('10,00', $formatted);
        $this->assertStringContainsString('€', $formatted);
    }

    public function test_ignore_routes() : void
    {
        $cashier = new Cashier;
        Cashier::ignoreRoutes();

        $this->assertSame(false, $cashier::$registersRoutes);
    }

    public function test_keep_past_subscriptions_active() : void
    {
        $cashier = new Cashier;
        Cashier::keepPastDueSubscriptionsActive();

        $this->assertSame(false, $cashier::$deactivatePastDue);
    }

    public function test_keep_incomplete_subscriptions_active() : void
    {
        $cashier = new Cashier;
        Cashier::keepIncompleteSubscriptionsActive();

        $this->assertSame(false, $cashier::$deactivateIncomplete);
    }

    public function test_use_subscription_model() : void
    {
        $model = 'App\Models\Subscription';
        $cashier = new Cashier;
        Cashier::useSubscriptionModel($model);

        $this->assertSame($model, $cashier::$subscriptionModel);
    }

    public function test_use_subscription_item_model() : void
    {
        $model = 'App\Models\SubscriptionItem';
        $cashier = new Cashier;
        Cashier::useSubscriptionItemModel($model);

        $this->assertSame($model, $cashier::$subscriptionItemModel);
    }
}