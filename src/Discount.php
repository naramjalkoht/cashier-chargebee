<?php

namespace Laravel\CashierChargebee;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;
use \ChargeBee\ChargeBee\Models\Discount as ChargeBeeDiscount;

class Discount implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * The Chargebee Discount instance.
     *
     * @var ChargeBeeDiscount
     */
    protected $discount;

    /**
     * Create a new Discount instance.
     *
     * @param  \ChargeBee\ChargeBee\Models\Discount  $discount
     * @return void
     */
    public function __construct(ChargeBeeDiscount $discount)
    {
        $this->discount = $discount;
    }

    /**
     * Get the coupon applied to the discount.
     *
     * @return \Laravel\CashierChargebee\Coupon
     */
    public function coupon()
    {
        return new Coupon($this->discount->coupon);
    }

    /**
     * Get the date that the coupon was applied.
     *
     * @return \Carbon\Carbon
     */
    public function start()
    {
        return Carbon::createFromTimestamp($this->discount->start);
    }

    /**
     * Get the date that this discount will end.
     *
     * @return \Carbon\Carbon|null
     */
    public function end()
    {
        if (!is_null($this->discount->end)) {
            return Carbon::createFromTimestamp($this->discount->end);
        }
    }

    /**
     * Get the Chargebee Discount instance.
     *
     * @return \ChargeBee\ChargeBee\Models\Discount
     */
    public function asChargebeeDiscount()
    {
        return $this->discount;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->asChargebeeDiscount()->getValues();
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
        return $this->discount->{$key};
    }
}
