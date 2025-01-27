<?php

namespace Laravel\CashierChargebee\Concerns;

use Illuminate\Support\Arr;
use Laravel\CashierChargebee\Checkout;

trait PerformsCharges
{
    use AllowsCoupons;



    /**
     * Begin a new checkout session for existing prices.
     *
     * @param  array|string  $items
     * @param  array  $sessionOptions
     * @param  array  $customerOptions
     * @return \Laravel\CashierChargebee\Checkout
     */
    public function checkout($items, array $sessionOptions = [], array $customerOptions = [])
    {
        return Checkout::customer($this, $this)->create($items, $sessionOptions, $customerOptions);
    }

    /**
     * Begin a new checkout session for a "one-off" charge.
     *
     * @param  int  $amount
     * @param  string  $name
     * @param  int  $quantity
     * @param  array  $sessionOptions
     * @param  array  $customerOptions
     * @param  array  $productData
     * @return \Laravel\CashierChargebee\Checkout
     */
    public function checkoutCharge($amount, $name, $quantity = 1, array $sessionOptions = [], array $customerOptions = [], array $productData = [])
    {

        $charges = Arr::get($sessionOptions, 'charges', []);

        $charges[] = [
            array_merge($productData, [
                'amount' => $amount,
                'description' => Arr::get($productData, 'description', $name) ?? $name
            ])
        ];

        return $this->checkout([], array_merge($sessionOptions, [
            'currencyCode' => strtoupper($this->preferredCurrency()),
            'charges' => $charges
        ]), $customerOptions);
    }
}
