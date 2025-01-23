<?php

namespace Laravel\CashierChargebee\Concerns;

use ChargeBee\ChargeBee\Models\PaymentIntent;

trait ManagesPaymentMethods
{
    /**
     * Create a new PaymentIntent instance with amount = 0.
     */
    public function createSetupIntent(array $options = []): ?PaymentIntent
    {
        if ($this->hasChargebeeId()) {
            $options['customer_id'] = $this->chargebee_id;
        }

        $defaultOptions = [
            'amount'        => 0,
            'currency_code' => !empty($options['currency_code'])
                ? $options['currency_code']
                : config('cashier.currency')
        ];

        $paymentIntent = PaymentIntent::create(array_merge($options, $defaultOptions));

        return $paymentIntent?->paymentIntent();
    }

    /**
     * Retrieve a PaymentIntent from ChargeBee.
     */
    public function findSetupIntent(string $id): ?PaymentIntent
    {
        $paymentIntent = PaymentIntent::retrieve($id);

        return$paymentIntent?->paymentIntent();
    }
}
