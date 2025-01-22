<?php

namespace Laravel\CashierChargebee\Concerns;

use ChargeBee\ChargeBee\Exceptions\InvalidRequestException;
use ChargeBee\ChargeBee\Models\Customer;
use Laravel\CashierChargebee\Exceptions\CustomerAlreadyCreated;
use Laravel\CashierChargebee\Exceptions\CustomerNotFound;

trait ManagesCustomer
{
    /**
     * Retrieve the Chargebee customer ID.
     */
    public function chargebeeId(): string|null
    {
        return $this->chargebee_id;
    }

    /**
     * Determine if the customer has a Chargebee customer ID.
     */
    public function hasChargebeeId(): bool
    {
        return ! is_null($this->chargebee_id);
    }

    /**
     * Create a Chargebee customer for the given model.
     *
     * @throws \Laravel\CashierChargebee\Exceptions\CustomerAlreadyCreated
     */
    public function createAsChargebeeCustomer(array $options = []): Customer
    {
        if ($this->hasChargebeeId()) {
            throw CustomerAlreadyCreated::exists($this);
        }

        $defaultOptions = [
            'firstName' => $this->chargebeeFirstName(),
            'lastName' => $this->chargebeeLastName(),
            'email' => $this->chargebeeEmail(),
            'phone' => $this->chargebeePhone(),
            'billingAddress' => $this->chargebeeBillingAddress(),
            'locale' => $this->chargebeeLocale(),
            'metaData' => $this->chargebeeMetaData(),
        ];

        $options = array_merge(array_filter($defaultOptions), $options);

        // Create a customer instance on Chargebee and store its ID for future retrieval.
        $result = Customer::create($options);
        $customer = $result->customer();

        $this->chargebee_id = $customer->id;
        $this->save();

        return $customer;
    }

    /**
     * Get the Chargebee customer for the model.
     *
     * @todo Add retrieving subscription info.
     */
    public function asChargebeeCustomer(): Customer
    {
        if (! $this->hasChargebeeId()) {
            throw CustomerNotFound::notFound($this);
        }

        try {
            $response = Customer::retrieve($this->chargebeeId());

            return $response->customer();
        } catch (InvalidRequestException $exception) {
            if (strpos($exception->getMessage(), "Sorry, we couldn't find that resource") !== false) {
                throw CustomerNotFound::notFound($this);
            }
            throw $exception;
        }
    }

    /**
     * Get the default first name.
     */
    public function chargebeeFirstName(): string|null
    {
        return $this->first_name ?? null;
    }

    /**
     * Get the default last name.
     */
    public function chargebeeLastName(): string|null
    {
        return $this->last_name ?? null;
    }

    /**
     * Get the default email address.
     */
    public function chargebeeEmail(): string|null
    {
        return $this->email ?? null;
    }

    /**
     * Get the default phone number.
     */
    public function chargebeePhone(): string|null
    {
        return $this->phone ?? null;
    }

    /**
     * Get the default billing address.
     */
    public function chargebeeBillingAddress(): array
    {
        return [];
    }

    /**
     * Get the default locale.
     */
    public function chargebeeLocale(): string|null
    {
        return $this->locale ?? null;
    }

    /**
     * Get the default metadata.
     */
    public function chargebeeMetaData(): string|null
    {
        return $this->chargebee_metadata ?? null;
    }
}
