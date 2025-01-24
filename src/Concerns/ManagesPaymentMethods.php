<?php

namespace Laravel\CashierChargebee\Concerns;

use ChargeBee\ChargeBee\Exceptions\InvalidRequestException;
use ChargeBee\ChargeBee\Exceptions\PaymentException;
use ChargeBee\ChargeBee\Models\Customer;
use ChargeBee\ChargeBee\Models\PaymentIntent;
use ChargeBee\ChargeBee\Models\PaymentSource;
use Illuminate\Support\Collection;
use Laravel\CashierChargebee\Exceptions\CustomerNotFound;

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
     * Get a collection of the customer's payment methods of an optional type.
     */
    public function paymentMethods(?string $type = null, array $parameters = []): ?Collection
    {
        if (! $this->hasChargebeeId()) {
            return new Collection();
        }

        $parameters = array_merge(['limit' => 24], $parameters);

        $paymentSources = PaymentSource::all(
            array_filter(['customer' => $this->chargebee_id, 'type[is]' => $type]) + $parameters
        );

        return Collection::make($paymentSources)->map(function ($paymentSource) {
            return $paymentSource->paymentSource();
        });
    }

    /**
     * Add a payment method to the customer.
     *
     * @throws CustomerNotFound
     * @throws PaymentException
     */
    public function addPaymentMethod(
        string $cardNumber,
        string $cardCVV,
        string $cardExpiryYear,
        string $cardExpiryMonth,
        bool $replaceDefault = false
    ): ?PaymentSource {
        $this->assertCustomerExists();

        $params = [
            'customer_id' => $this->chargebee_id,
            'replace_primary_payment_source' => $replaceDefault,
            'card' => [
                'number' => $cardNumber,
                'cvv' => $cardCVV,
                'expiry_month' => (int) $cardExpiryMonth,
                'expiry_year' => (int) $cardExpiryYear,
            ],
        ];

        $paymentSource = PaymentSource::createCard($params)?->paymentSource();

        if ($paymentSource && $replaceDefault) {
            Customer::assignPaymentRole(
                $this->chargebeeId(),
                [
                    'payment_source_id' => $paymentSource->id,
                    'role' => 'PRIMARY',
                ]
            );
        }

        return $paymentSource;
    }

    /**
     * Delete a payment method to the customer.
     *
     * @throws CustomerNotFound
     * @throws InvalidRequestException
     */
    public function deletePaymentMethod(string $id): ?Customer
    {
        $this->assertCustomerExists();

        return PaymentSource::delete($id)->customer();
    }
}
