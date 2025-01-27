<?php

namespace Laravel\CashierChargebee\Tests\Feature;

use ChargeBee\ChargeBee\Models\Coupon;
use ChargeBee\ChargeBee\Models\Item;
use ChargeBee\ChargeBee\Models\ItemFamily;
use ChargeBee\ChargeBee\Models\ItemPrice;
use Laravel\CashierChargebee\Checkout;

class CheckoutTest extends FeatureTestCase
{
    /**
     * @param  \Illuminate\Routing\Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->get('/home', fn() => 'Hello World!')->name('home');
    }

    // public function test_customers_can_start_a_product_checkout_session()
    // {
    //     $user = $this->createCustomer('can_start_a_product_checkout_session');

    //     $shirtPrice = $this->createItemPrice('T-shirt', 1500);
    //     $carPrice = $this->createItemPrice('Car', 30000);

    //     $items = [$shirtPrice->id => 5, $carPrice->id];

    //     $checkout = $user->checkout($items, [
    //         'success_url' => 'http://example.com',
    //         'cancel_url' => 'http://example.com',
    //     ]);

    //     $this->assertInstanceOf(Checkout::class, $checkout);
    // }

    // public function test_customers_can_start_a_product_checkout_session_with_a_coupon_applied()
    // {
    //     $user = $this->createCustomer('can_start_checkout_session_with_coupon');

    //     $shirtPrice = $this->createItemPrice('T-shirt', 1500);


    //     $id = 'coupon_' . now()->timestamp;
    //     $coupon = (Coupon::createForItems([
    //         'id' => $id,
    //         'name' => $id,
    //         'discountType' => 'fixed_amount',
    //         'discountAmount' => 500,
    //         'durationType' => 'one_time',
    //         'applyOn' => 'invoice_amount'
    //     ]))->coupon();

    //     $checkout = $user->withCoupons([$coupon->id])
    //         ->checkout($shirtPrice->id, [
    //             'success_url' => 'http://example.com',
    //             'cancel_url' => 'http://example.com',
    //         ]);

    //     $this->assertInstanceOf(Checkout::class, $checkout);
    // }

    // public function test_customers_can_start_a_one_off_charge_checkout_session()
    // {
    //     $user = $this->createCustomer('can_start_one_off_checkout_session');

    //     $checkout = $user->checkoutCharge(1200, 'T-shirt', 1, [
    //         'success_url' => 'http://example.com',
    //         'cancel_url' => 'http://example.com',
    //     ]);

    //     $this->assertInstanceOf(Checkout::class, $checkout);
    // }

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
        $this->assertTrue($checkout->allow_promotion_codes);
        $this->assertSame(1815, $checkout->amount_total);

        $id = 'coupon_' . now()->timestamp;
        $coupon = (Coupon::createForItems([
            'id' => $id,
            'name' => $id,
            'discountType' => 'fixed_amount',
            'discountAmount' => 500,
            'durationType' => 'one_time',
            'applyOn' => 'invoice_amount',
            'period' => 3,
            'periodUnit' => 'month'
        ]))->coupon();

        $checkout = $user->newSubscription('default', $price->id)
            ->withCoupons([$coupon->id])
            ->checkout([
                'success_url' => 'http://example.com',
                'cancel_url' => 'http://example.com',
            ]);

        $this->assertInstanceOf(Checkout::class, $checkout);
        $this->assertNull($checkout->allow_promotion_codes);
        $this->assertSame(1210, $checkout->amount_total);
    }

    // public function test_guest_customers_can_start_a_checkout_session()
    // {
    //     $shirtPrice = $this->createItemPrice('T-shirt', 1500);

    //     $checkout = Checkout::guest()->create($shirtPrice->id, [
    //         'success_url' => 'http://example.com',
    //         'cancel_url' => 'http://example.com',
    //     ]);

    //     $this->assertInstanceOf(Checkout::class, $checkout);
    // }


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
            'itemFamilyId' => $itemFamily->itemFamily()->id
        ]);

        $itemPrice = ItemPrice::create([
            'id' => "$price-$ts",
            'name' => "$price-$ts",
            'price' => $amount,
            'pricingModel' => 'per_unit',
            'itemId' => $item->item()->id,
            'itemFamilyId' => $itemFamily->itemFamily()->id,
            'currencyCode' => '',
            'period' => 1,
            'periodUnit' => 'year'
        ]);

        return $itemPrice->itemPrice();
    }


    protected function createItemPrice($price, $amount)
    {
        $ts = now()->timestamp;

        $itemFamily = ItemFamily::create([
            'id' => "$price-$ts",
            'name' => "$price-$ts",
        ]);

        $item = Item::create([
            'id' => "$price-$ts",
            'name' => "$price-$ts",
            'type' => 'charge',
            'itemFamilyId' => $itemFamily->itemFamily()->id
        ]);

        $itemPrice = ItemPrice::create([
            'id' => "$price-$ts",
            'name' => "$price-$ts",
            'price' => $amount,
            'pricingModel' => 'per_unit',
            'itemId' => $item->item()->id,
            'itemFamilyId' => $itemFamily->itemFamily()->id
        ]);


        return $itemPrice->itemPrice();
    }
}
