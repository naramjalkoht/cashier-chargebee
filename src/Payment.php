<?php

namespace Laravel\CashierChargebee;

use ChargeBee\ChargeBee\Models\PaymentIntent;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Model;
use JsonSerializable;

class Payment implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * The Chargebee PaymentIntent instance.
     *
     * @var \ChargeBee\ChargeBee\Models\PaymentIntent
     */
    protected $paymentIntent;

    /**
     * The related customer instance.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $customer;

    /**
     * Create a new Payment instance.
     */
    public function __construct(PaymentIntent $paymentIntent)
    {
        $this->paymentIntent = $paymentIntent;
    }

    /**
     * Retrieve the related customer for the payment intent if one exists.
     */
    public function customer(): Model|null
    {
        if ($this->customer) {
            return $this->customer;
        }

        return $this->customer = Cashier::findBillable($this->paymentIntent->customerId);
    }

    /**
     * The Chargebee PaymentIntent instance.
     */
    public function asChargebeePaymentIntent(): PaymentIntent
    {
        return $this->paymentIntent;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->asChargebeePaymentIntent()->getValues();
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
     * Dynamically get values from the Chargebee object.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->paymentIntent->{$key};
    }
}
