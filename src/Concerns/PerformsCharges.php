<?php

namespace Laravel\CashierChargebee\Concerns;

use ChargeBee\ChargeBee\Exceptions\InvalidRequestException;
use ChargeBee\ChargeBee\Models\PaymentIntent;
use Illuminate\Support\Arr;
use Laravel\CashierChargebee\Checkout;
use Laravel\CashierChargebee\Exceptions\PaymentNotFound;
use Laravel\CashierChargebee\Payment;

trait PerformsCharges
{
    use AllowsCoupons;

    /**
     * Create a new PaymentIntent instance.
     */
    public function pay(int $amount, array $options = []): Payment
    {
        return $this->createPayment($amount, $options);
    }

    /**
     * Create a new Payment instance with a Chargebee PaymentIntent.
     */
    public function createPayment(int $amount, array $options = []): Payment
    {
        $options = array_merge([
            'currencyCode' => $this->preferredCurrency(),
        ], $options);

        $options['amount'] = $amount;

        if ($this->hasChargebeeId()) {
            $options['customerId'] = $this->chargebeeId();
        }

        $result = PaymentIntent::create($options);

        return new Payment(
            $result->paymentIntent()
        );
    }

    /**
     * Find a payment intent by ID.
     */
    public function findPayment(string $id): Payment
    {
        try {
            $result = PaymentIntent::retrieve($id);

            return new Payment(
                $result->paymentIntent()
            );
        } catch (InvalidRequestException $exception) {
            if (strpos($exception->getApiErrorCode(), 'resource_not_found') !== false) {
                throw PaymentNotFound::notFound($id);
            }
            throw $exception;
        }
    }

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
                'description' => Arr::get($productData, 'description', $name) ?? $name,
            ]),
        ];

        return $this->checkout([], array_merge($sessionOptions, [
            'currencyCode' => strtoupper($this->preferredCurrency()),
            'charges' => $charges,
        ]), $customerOptions);
    }
}
