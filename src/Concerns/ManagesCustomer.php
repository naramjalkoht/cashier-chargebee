<?php

namespace Laravel\CashierChargebee\Concerns;

use ChargeBee\ChargeBee\Exceptions\InvalidRequestException;
use ChargeBee\ChargeBee\Models\Customer;
use ChargeBee\ChargeBee\Models\PortalSession;
use Illuminate\Http\RedirectResponse;
use Laravel\CashierChargebee\Cashier;
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
     * Determine if the customer has a Chargebee customer ID and throw an exception if not.
     *
     * @throws \Laravel\CashierChargebee\Exceptions\CustomerNotFound
     */
    protected function assertCustomerExists()
    {
        if (! $this->hasChargebeeId()) {
            throw CustomerNotFound::notFound($this);
        }
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
        $this->assertCustomerExists();

        try {
            $response = Customer::retrieve($this->chargebeeId());

            return $response->customer();
        } catch (InvalidRequestException $exception) {
            if (strpos($exception->getApiErrorCode(), 'resource_not_found') !== false) {
                throw CustomerNotFound::notFound($this);
            }
            throw $exception;
        }
    }

    /**
     * Update Chargebee customer information for the model.
     */
    public function updateChargebeeCustomer(array $options = []): Customer
    {
        $this->assertCustomerExists();

        try {
            // Non-empty billingAddress is required for the updateBillingInfo API call.
            $billingAddress = [
                'billingAddress' => $this->chargebeeBillingAddress(),
            ];
            $options = array_merge($billingAddress, $options);

            // We need to make 2 separate API calls to update customer and billing info.
            Customer::update($this->chargebeeId(), $options);
            $response = Customer::updateBillingInfo($this->chargebeeId(), $options);

            return $response->customer();
        } catch (InvalidRequestException $exception) {
            if (strpos($exception->getApiErrorCode(), 'resource_not_found') !== false) {
                throw CustomerNotFound::notFound($this);
            }
            throw $exception;
        }
    }

    /**
     * Get the Chargebee customer instance for the current user or create one.
     */
    public function createOrGetChargebeeCustomer(array $options = []): Customer
    {
        if ($this->hasChargebeeId()) {
            return $this->asChargebeeCustomer();
        }

        return $this->createAsChargebeeCustomer($options);
    }

    /**
     * Update the Chargebee customer information for the current user or create one.
     */
    public function updateOrCreateChargebeeCustomer(array $options = []): Customer
    {
        if ($this->hasChargebeeId()) {
            return $this->updateChargebeeCustomer($options);
        }

        return $this->createAsChargebeeCustomer($options);
    }

    /**
     * Sync the customer's information to Chargebee.
     */
    public function syncChargebeeCustomerDetails(): Customer
    {
        return $this->updateChargebeeCustomer([
            'firstName' => $this->chargebeeFirstName(),
            'lastName' => $this->chargebeeLastName(),
            'email' => $this->chargebeeEmail(),
            'phone' => $this->chargebeePhone(),
            'billingAddress' => $this->chargebeeBillingAddress(),
            'locale' => $this->chargebeeLocale(),
            'metaData' => $this->chargebeeMetaData(),
        ]);
    }

    /**
     * Sync the customer's information to Chargebee for the current user or create one.
     */
    public function syncOrCreateChargebeeCustomer(array $options = []): Customer
    {
        if ($this->hasChargebeeId()) {
            return $this->syncChargebeeCustomerDetails();
        }

        return $this->createAsChargebeeCustomer($options);
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

    /**
     * Get the Chargebee supported currency used by the customer.
     */
    public function preferredCurrency(): string
    {
        return config('cashier.currency');
    }

    /**
     * Format the given amount into a displayable currency.
     */
    protected function formatAmount(int $amount): string
    {
        return Cashier::formatAmount($amount, $this->preferredCurrency());
    }

    /*
     * Get the Chargebee billing portal session for this customer.
     */
    public function billingPortalUrl($returnUrl = null, array $options = []): string
    {
        $this->assertCustomerExists();

        $response = PortalSession::create(array_merge([
            'redirect_url' => $returnUrl ?? route('home'),
            'customer' => [
                'id' => $this->chargebeeId(),
            ],
        ], $options));

        return $response->portalSession()->accessUrl;
    }

    /**
     * Generate a redirect response to the customer's Chargebee billing portal session.
     */
    public function redirectToBillingPortal($returnUrl = null, array $options = []): RedirectResponse
    {
        return new RedirectResponse(
            $this->billingPortalUrl($returnUrl, $options)
        );
    }
}
