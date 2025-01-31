<?php

namespace Laravel\CashierChargebee\Concerns;

use ChargeBee\ChargeBee\Exceptions\InvalidRequestException;
use ChargeBee\ChargeBee\Models\PaymentIntent;
use Laravel\CashierChargebee\Exceptions\PaymentNotFound;
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
}
