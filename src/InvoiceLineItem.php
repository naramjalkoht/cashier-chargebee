<?php

namespace Laravel\CashierChargebee;

use Carbon\Carbon;
use ChargeBee\ChargeBee\Models\InvoiceLineItem as ChargeBeeInvoiceLineItem;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

class InvoiceLineItem implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * The Cashier Invoice instance.
     *
     * @var \Laravel\CashierChargebee\Invoice
     */
    protected $invoice;

    /**
     * The Chargebee invoice line item instance.
     *
     * @var \ChargeBee\ChargeBee\Models\InvoiceLineItem
     */
    protected $item;

    /**
     * Create a new invoice line item instance.
     *
     * @param  \Laravel\CashierChargebee\Invoice  $invoice
     * @param  \ChargeBee\ChargeBee\Models\InvoiceLineItem  $item
     * @return void
     */
    public function __construct(Invoice $invoice, ChargeBeeInvoiceLineItem $item)
    {
        $this->invoice = $invoice;
        $this->item = $item;
    }

    /**
     * Get the total for the invoice line item.
     *
     * @return string
     */
    public function total(): string
    {
        return $this->formatAmount($this->item->amount);
    }

    /**
     * Get the unit amount excluding tax for the invoice line item.
     *
     * @return string
     */
    public function unitAmountExcludingTax(): string
    {
        return $this->formatAmount($this->item->unitAmount - $this->item->taxAmount);
    }

    /**
     * Get the total percentage of the default inclusive tax for the invoice line item.
     *
     * @return float
     */
    public function inclusiveTaxPercentage(): mixed
    {
        return $this->invoice->priceType == 'tax_inclusive' ? $this->taxRate : 0;
    }

    /**
     * Get the total percentage of the default exclusive tax for the invoice line item.
     *
     * @return float
     */
    public function exclusiveTaxPercentage(): mixed
    {
        return $this->invoice->priceType == 'tax_exclusive' ? $this->taxRate : 0;
    }

    /**
     * Determine if the invoice line item has tax rates.
     *
     * @return bool
     */
    public function hasTaxRates(): bool
    {
        return ! empty($this->item->taxRate);
    }

    /**
     * Get a human readable date for the start date.
     *
     * @return string|null
     */
    public function startDate(): ?string
    {
        if ($this->hasPeriod()) {
            return $this->startDateAsCarbon()->toFormattedDateString();
        }
    }

    /**
     * Get a human readable date for the end date.
     *
     * @return string|null
     */
    public function endDate(): ?string
    {
        if ($this->hasPeriod()) {
            return $this->endDateAsCarbon()->toFormattedDateString();
        }
    }

    /**
     * Get a Carbon instance for the start date.
     *
     * @return \Carbon\Carbon|null
     */
    public function startDateAsCarbon(): ?Carbon
    {
        if ($this->hasPeriod()) {
            return Carbon::createFromTimestampUTC($this->item->dateFrom);
        }
    }

    /**
     * Get a Carbon instance for the end date.
     *
     * @return \Carbon\Carbon|null
     */
    public function endDateAsCarbon()
    {
        if ($this->hasPeriod()) {
            return Carbon::createFromTimestampUTC($this->item->dateTo);
        }
    }

    /**
     * Determine if the invoice line item has a defined period.
     *
     * @return bool
     */
    public function hasPeriod()
    {
        return ! is_null($this->item->dateFrom) && ! is_null($this->item->dateTo);
    }

    /**
     * Determine if the invoice line item has a period with the same start and end date.
     *
     * @return bool
     */
    public function periodStartAndEndAreEqual()
    {
        return $this->hasPeriod() ? $this->item->dateFrom === $this->item->dateTo : false;
    }

    /**
     * Determine if the invoice line item is for a subscription.
     *
     * @return bool
     */
    public function isSubscription()
    {
        return $this->item->subscriptionId != null;
    }

    /**
     * Format the given amount into a displayable currency.
     *
     * @param  int  $amount
     * @return string
     */
    protected function formatAmount($amount)
    {
        return Cashier::formatAmount($amount, $this->invoice()->currencyCode);
    }

    /**
     * Get the Chargebee model instance.
     *
     * @return \Laravel\CashierChargebee\Invoice
     */
    public function invoice()
    {
        return $this->invoice;
    }

    /**
     * Get the underlying Chargebee invoice line item.
     *
     * @return \ChargeBee\ChargeBee\Models\InvoiceLineItem
     */
    public function asChargebeeInvoiceLineItem()
    {
        return $this->item;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->asChargebeeInvoiceLineItem()->getValues();
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Dynamically access the Chargebee invoice line item instance.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->item->{$key};
    }
}
