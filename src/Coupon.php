<?php

namespace Laravel\CashierChargebee;

use ChargeBee\ChargeBee\Models\Coupon as ChargebeeCoupon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

class Coupon implements Arrayable, Jsonable, JsonSerializable
{
    /**
     * The Stripe Coupon instance.
     *
     * @var \ChargeBee\ChargeBee\Models\Coupon
     */
    protected $coupon;

    /**
     * Create a new Coupon instance.
     *
     * @param  \ChargeBee\ChargeBee\Models\Coupon $coupon
     * @return void
     */
    public function __construct(ChargebeeCoupon $coupon)
    {
        $this->coupon = $coupon;
    }

    /**
     * Get the readable name for the Coupon.
     *
     * @return string
     */
    public function name()
    {
        return $this->coupon->name ?: $this->coupon->id;
    }

    /**
     * Determine if the coupon is a percentage.
     *
     * @return bool
     */
    public function isPercentage()
    {
        return $this->coupon->discountType == 'percentage';
    }

    /**
     * Get the discount percentage for the invoice.
     *
     * @return float|null
     */
    public function percentOff()
    {
        return $this->coupon->discountPercentage;
    }

    /**
     * Get the amount off for the coupon.
     *
     * @return string|null
     */
    public function amountOff()
    {
        if (!is_null($this->coupon->discountAmount)) {
            return $this->formatAmount($this->rawAmountOff());
        }
    }

    /**
     * Get the raw amount off for the coupon.
     *
     * @return int|null
     */
    public function rawAmountOff()
    {
        return $this->coupon->discountAmount;
    }

    /**
     * Format the given amount into a displayable currency.
     *
     * @param  int  $amount
     * @return string
     */
    protected function formatAmount($amount)
    {
        return Cashier::formatAmount($amount, $this->coupon->currencyCode);
    }

    /**
     * Get the Chargebee Coupon instance.
     *
     * @return \ChargeBee\ChargeBee\Models\Coupon
     */
    public function asChargebeeCoupon()
    {
        return $this->coupon;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->asChargebeeCoupon()->getValues();
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
        return $this->coupon->{$key};
    }
}
