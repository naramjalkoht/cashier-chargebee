<?php

namespace Laravel\CashierChargebee\Concerns;

use ChargeBee\ChargeBee\Exceptions\InvalidRequestException;
use ChargeBee\ChargeBee\Models\Customer;
use ChargeBee\ChargeBee\Models\PaymentIntent;
use ChargeBee\ChargeBee\Models\PaymentSource;
use Illuminate\Support\Collection;
use Laravel\CashierChargebee\Exceptions\CustomerNotFound;
use Laravel\CashierChargebee\Exceptions\InvalidPaymentMethod;
use Laravel\CashierChargebee\PaymentMethod;

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
            'amount' => 0,
            'currency_code' => ! empty($options['currency_code'])
                ? $options['currency_code']
                : config('cashier.currency'),
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

    /**
     * Determines if the customer currently has at least one payment method of an optional type.
     */
    public function hasPaymentMethod(?string $type = null): bool
    {
        return $this->paymentMethods($type)->isNotEmpty();
    }

    /**
     * Get a collection of the customer's payment methods of an optional type.
     */
    public function paymentMethods(?string $type = null, array $parameters = []): ?Collection
    {
        if (! $this->hasChargebeeId()) {
            return new Collection();
        }

        $parameters = array_merge(['limit' => 24], $parameters);

        $paymentSources = PaymentSource::all(
            array_filter(['customer' => $this->chargebeeId(), 'type[is]' => $type]) + $parameters
        );

        return Collection::make($paymentSources)->map(function ($paymentSource) {
            return $paymentSource->paymentSource();
        });
    }

    /**
     * Add a payment method to the customer.
     *
     * @throws InvalidPaymentMethod
     * @throws CustomerNotFound
     */
    public function addPaymentMethod(PaymentSource $paymentSource): PaymentMethod
    {
        $this->assertCustomerExists();

        return new PaymentMethod($this, $paymentSource);
    }

    /**
     * Delete a payment method to the customer.
     *
     * @throws CustomerNotFound
     * @throws InvalidRequestException
     */
    public function deletePaymentMethod(PaymentSource $paymentSource): void
    {
        $this->assertCustomerExists();

        if ($this->chargebeeId() !== $paymentSource->customerId) {
            throw InvalidPaymentMethod::invalidOwner($paymentSource, $this);
        }

        PaymentSource::delete($paymentSource->id);
    }
}
