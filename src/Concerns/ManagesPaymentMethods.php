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
            'amount'        => 0,
            'currency_code' => !empty($options['currency_code'])
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

        return $paymentIntent?->paymentIntent();
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
        if (!$this->hasChargebeeId()) {
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
    public function addPaymentMethod(PaymentSource $paymentSource, bool $setAsDefault = false): PaymentMethod
    {
        $this->assertCustomerExists();

        if($setAsDefault) {
            $this->setDefaultPaymentMethod($paymentSource);
        }

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

        $customer = $this->asChargebeeCustomer();

        if (! empty($customer->primaryPaymentSourceId) && $paymentSource->id === $customer->primaryPaymentSourceId) {
            $this->forceFill([
                'pm_type'      => null,
                'pm_last_four' => null,
            ])->save();
        }

        PaymentSource::delete($paymentSource->id);
    }

    /**
     * Get the default payment method for the customer.
     *
     * @throws CustomerNotFound
     * @throws InvalidRequestException
     * @throws InvalidPaymentMethod
     */
    public function defaultPaymentMethod(): ?PaymentMethod
    {
        $this->assertCustomerExists();

        $customer = $this->asChargebeeCustomer();

        if (! empty($customer->primaryPaymentSourceId)) {
            return new PaymentMethod($this, $this->resolveChargebeePaymentMethod($customer->primaryPaymentSourceId));
        }

        return null;
    }

    /**
     * Synchronises the customer's default payment method from Chargebee back into the database.
     *
     * @throws InvalidPaymentMethod
     * @throws InvalidRequestException
     * @throws CustomerNotFound
     */
    public function updateDefaultPaymentMethodFromChargebee(): self
    {
        $defaultPaymentMethod = $this->defaultPaymentMethod();

        if ($defaultPaymentMethod && $defaultPaymentMethod instanceof PaymentMethod) {
            $this->fillPaymentMethodDetails(
                $defaultPaymentMethod->asChargebeePaymentMethod()
            )->save();
        } else {
            $this->forceFill([
                'pm_type'      => null,
                'pm_last_four' => null,
            ])->save();
        }

        return $this;
    }

    /**
     * Set Default PaymentMethod
     *
     * @throws InvalidPaymentMethod
     * @throws InvalidRequestException
     * @throws CustomerNotFound
     */
    public function setDefaultPaymentMethod(PaymentSource|string $paymentSource): ?Customer
    {
        $this->assertCustomerExists();

        $paymentSource = $this->resolveChargebeePaymentMethod($paymentSource);

        if ($paymentSource) {
            Customer::assignPaymentRole(
                $this->chargebeeId(),
                [
                    'payment_source_id' => $paymentSource->id,
                    'role' => 'PRIMARY'
                ]
            );

            $this->fillPaymentMethodDetails($paymentSource)->save();
        }

        return null;
    }

    /**
     * Fills the model's properties with the payment method from Chargebee.
     */
    protected function fillPaymentMethodDetails(PaymentSource $paymentSource): self
    {
        if ($paymentSource->type === 'card') {
            $this->pm_type = $paymentSource->card->brand;
            $this->pm_last_four = $paymentSource->card->last4;
        } else {
            $this->pm_type = $type = $paymentSource->type;
            $this->pm_last_four = $paymentSource?->$type->last4 ?? null;
        }

        return $this;
    }

    /**
     * Find a PaymentMethod by ID.
     *
     * @throws InvalidPaymentMethod
     */
    public function findPaymentMethod(PaymentSource|string $paymentSource): ?PaymentMethod
    {
        $paymentSource = $this->resolveChargebeePaymentMethod($paymentSource);
        return $paymentSource
            ? new PaymentMethod($this, $paymentSource)
            : null;
    }

    /**
     * Resolve a PaymentMethod as a Chargebee PaymentSource object.
     *
     * @throws InvalidRequestException
     * @throws InvalidPaymentMethod
     */
    protected function resolveChargebeePaymentMethod(PaymentSource|string $paymentSource): ?PaymentSource
    {
        if ($paymentSource instanceof PaymentSource) {
            return $paymentSource;
        }

        return PaymentSource::retrieve($paymentSource)?->paymentSource();
    }
}
