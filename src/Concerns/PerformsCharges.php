<?php

namespace Laravel\CashierChargebee\Concerns;

use ChargeBee\ChargeBee\Models\PaymentIntent;
use Laravel\CashierChargebee\Payment;

trait PerformsCharges
{
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
}
