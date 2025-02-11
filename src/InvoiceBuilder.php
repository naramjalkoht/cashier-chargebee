<?php

namespace Laravel\CashierChargebee;

use ChargeBee\ChargeBee\Models\Invoice as ChargeBeeInvoice;

class InvoiceBuilder
{
    /**
     * The model that is subscribing.
     *
     * @var \Laravel\CashierChargebee\Billable|\Illuminate\Database\Eloquent\Model
     */
    protected $owner;

    protected $charges;

    protected $itemPrices;

    /**
     * Create a new subscription builder instance.
     *
     * @param  mixed  $owner
     * @param  string  $type
     * @param  string|string[]|array[]  $prices
     * @return void
     */
    public function __construct($owner)
    {
        $this->owner = $owner;
    }

    /**
     * Invoice the customer for the given amount and generate an invoice immediately.
     *
     * @param  string  $description
     * @param  int  $amount
     * @param  array  $tabOptions
     * @return \Laravel\CashierChargebee\InvoiceBuilder
     *
     * @throws \Laravel\CashierChargebee\Exceptions\IncompletePayment
     */
    public function tabFor($description, $amount, array $tabOptions = [])
    {
        $this->charges[] = array_merge([
            'amount' => $amount,
            'description' => $description,
        ], $tabOptions);

        return $this;
    }

    /**
     * Invoice the customer for the given Price ID and generate an invoice immediately.
     *
     * @param  string  $price
     * @param  int  $quantity
     * @param  array  $tabOptions
     * @return \Laravel\CashierChargebee\InvoiceBuilder
     *
     * @throws \Laravel\CashierChargebee\Exceptions\IncompletePayment
     */
    public function tabPrice($price, $quantity = 1, array $tabOptions = [])
    {
        $this->itemPrices[] = array_merge([
            'itemPriceId' => $price,
            'quantity' => $quantity,
        ], $tabOptions);

        return $this;
    }

    /**
     * Begin a new Checkout Session.
     *
     * @param  array  $sessionOptions
     * @param  array  $customerOptions
     * @return \Laravel\CashierChargebee\Invoice
     */
    public function invoice(?array $options = [])
    {
        $data = array_filter(array_merge([
            'customerId' => $this->owner->chargebeeId(),
            'currencyCode' => $this->owner->preferredCurrency(),
            'charges' => $this->charges,
            'itemPrices' => $this->itemPrices,
        ], $options));

        $response = ChargeBeeInvoice::createForChargeItemsAndCharges($data);

        return new Invoice($this->owner, $response->invoice());
    }
}
