<?php

namespace Laravel\CashierChargebee\Tests\Feature;

use ChargeBee\ChargeBee\Models\Coupon;
use ChargeBee\ChargeBee\Models\Item;
use ChargeBee\ChargeBee\Models\ItemFamily;
use ChargeBee\ChargeBee\Models\ItemPrice;
use Laravel\CashierChargebee\Checkout;
use Laravel\CashierChargebee\Session;

class CheckoutTest extends FeatureTestCase
{
    /**
     * @param  \Illuminate\Routing\Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->get('/home', fn() => 'Hello World!')->name('home');
    }

    public function test_customers_can_start_a_product_checkout_session()
    {
        $user = $this->createCustomer('can_start_a_product_checkout_session');

        $shirtPrice = $this->createItemPrice('T-shirt', amount: 1500);
        $carPrice = $this->createItemPrice('Car', 30000);

        $items = [$shirtPrice->id => 5, $carPrice->id];

        $checkout = $user->checkout($items, [
            'success_url' => 'http://example.com',
            'cancel_url' => 'http://example.com',
        ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
        $this->assertSame('checkout_one_time', $checkout->type);
    }

    public function test_customers_can_start_a_product_checkout_session_with_a_coupon_applied()
    {
        $user = $this->createCustomer('can_start_checkout_session_with_coupon');

        $shirtPrice = $this->createItemPrice('T-shirt', 1500);

        $id = 'coupon_' . now()->timestamp;
        $coupon = Coupon::createForItems([
            'id' => $id,
            'name' => $id,
            'discountType' => 'fixed_amount',
            'discountAmount' => 500,
            'durationType' => 'one_time',
            'applyOn' => 'invoice_amount',
            'currencyCode' => config('cashier.currency'),
        ])->coupon();

        $checkout = $user->withCoupons([$coupon->id])
            ->checkout($shirtPrice->id, [
                'success_url' => 'http://example.com',
                'cancel_url' => 'http://example.com',
            ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
        $this->assertSame('checkout_one_time', $checkout->type);
    }

    public function test_customers_can_start_a_one_off_charge_checkout_session()
    {
        $user = $this->createCustomer('can_start_one_off_checkout_session');

        $checkout = $user->checkoutCharge(1200, 'T-shirt', 1, [
            'success_url' => 'http://example.com',
            'cancel_url' => 'http://example.com',
        ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
        $this->assertSame('checkout_one_time', $checkout->type);
    }

    public function test_customers_can_save_payment_details()
    {
        $user = $this->createCustomer('can_save_payment_details');

        $checkout = $user->checkout([], [
            'mode' => Session::MODE_SETUP,
        ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
        $this->assertSame('manage_payment_sources', $checkout->type);
    }

    public function test_customers_can_start_a_subscription_checkout_session()
    {
        $user = $this->createCustomer('can_start_a_subscription_checkout_session');

        $price = $this->createSubscription('Forge-Hobby', 1500);

        $checkout = $user->newSubscription('default', $price->id)
            ->checkout([
                'success_url' => 'http://example.com',
                'cancel_url' => 'http://example.com',
            ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
        $this->assertSame('checkout_new', $checkout->type);

        $id = 'coupon_' . now()->timestamp;
        $coupon = Coupon::createForItems([
            'id' => $id,
            'name' => $id,
            'discountType' => 'fixed_amount',
            'discountAmount' => 500,
            'durationType' => 'one_time',
            'applyOn' => 'invoice_amount',
            'currencyCode' => config('cashier.currency'),
        ])->coupon();

        $checkout = $user->newSubscription('default', $price->id)
            ->withCoupons([$coupon->id])
            ->checkout([
                'success_url' => 'http://example.com',
                'cancel_url' => 'http://example.com',
            ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
        $this->assertSame('checkout_new', $checkout->type);
    }

    public function test_guest_customers_can_start_a_checkout_session()
    {
        $shirtPrice = $this->createItemPrice('T-shirt', 1500);

        $checkout = Checkout::guest()->create($shirtPrice->id, [
            'success_url' => 'http://example.com',
            'cancel_url' => 'http://example.com',
        ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
    }

    protected function createSubscription($price, $amount)
    {
        $ts = now()->timestamp;

        $itemFamily = ItemFamily::create([
            'id' => "$price-$ts",
            'name' => "$price-$ts",
        ]);

        $item = Item::create([
            'id' => "$price-$ts",
            'name' => "$price-$ts",
            'type' => 'plan',
            'itemFamilyId' => $itemFamily->itemFamily()->id,
        ]);

        $itemPrice = ItemPrice::create([
            'id' => "$price-$ts",
            'name' => "$price-$ts",
            'price' => $amount,
            'pricingModel' => 'per_unit',
            'itemId' => $item->item()->id,
            'itemFamilyId' => $itemFamily->itemFamily()->id,
            'currencyCode' => config('cashier.currency'),
            'period' => 1,
            'periodUnit' => 'year',
        ]);

        return $itemPrice->itemPrice();
    }
}
