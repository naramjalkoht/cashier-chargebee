<?php

namespace Laravel\CashierChargebee;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Conditionable;
use Laravel\CashierChargebee\Concerns\AllowsCoupons;
use Laravel\CashierChargebee\Concerns\HandlesTaxes;
use Laravel\CashierChargebee\Concerns\Prorates;

class SubscriptionBuilder
{
    use AllowsCoupons;
    use Conditionable;
    use HandlesTaxes;
    use Prorates;

    /**
     * The model that is subscribing.
     *
     * @var \Laravel\CashierChargebee\Billable|\Illuminate\Database\Eloquent\Model
     */
    protected $owner;

    /**
     * The type of the subscription.
     *
     * @var string
     */
    protected $type;

    /**
     * The prices the customer is being subscribed to.
     *
     * @var array
     */
    protected $items = [];

    /**
     * The date and time the trial will expire.
     *
     * @var \Carbon\Carbon|\Carbon\CarbonInterface|null
     */
    protected $trialExpires;

    /**
     * Indicates that the trial should end immediately.
     *
     * @var bool
     */
    protected $skipTrial = false;

    /**
     * The date on which the billing cycle should be anchored.
     *
     * @var int|null
     */
    protected $billingCycleAnchor = null;

    /**
     * The metadata to apply to the subscription.
     *
     * @var array
     */
    protected $metadata = [];

    /**
     * Create a new subscription builder instance.
     *
     * @param  mixed  $owner
     * @param  string  $type
     * @param  string|string[]|array[]  $prices
     * @return void
     */
    public function __construct($owner, $type, $prices = [])
    {
        $this->type = $type;
        $this->owner = $owner;

        foreach ((array) $prices as $price) {
            $this->price($price);
        }
    }

    /**
     * Set a price on the subscription builder.
     *
     * @param  string|array  $price
     * @param  int|null  $quantity
     * @return $this
     */
    public function price($price, $quantity = 1)
    {
        $options = is_array($price) ? $price : ['itemPriceId' => $price];

        $quantity = $price['quantity'] ?? $quantity;

        if (!is_null($quantity)) {
            $options['quantity'] = $quantity;
        }

        if (isset($options['price'])) {
            $this->items[$options['itemPriceId']] = $options;
        } else {
            $this->items[] = $options;
        }

        return $this;
    }

    /**
     * Get the price tax rates for the Stripe payload.
     *
     * @param  string  $price
     * @return array|null
     */
    protected function getPriceTaxRatesForPayload($price)
    {
        if ($taxRates = $this->owner->priceTaxRates()) {
            return $taxRates[$price] ?? null;
        }
    }

    /**
     * Begin a new Checkout Session.
     *
     * @param  array  $sessionOptions
     * @param  array  $customerOptions
     * @return \Laravel\CashierChargebee\Checkout
     */
    public function checkout(array $sessionOptions = [], array $customerOptions = [])
    {
        if (empty($this->items)) {
            throw new Exception('At least one price is required when starting subscriptions.');
        }

        if (!$this->skipTrial && $this->trialExpires) {
            // Checkout Sessions are active for 24 hours after their creation and within that time frame the customer
            // can complete the payment at any time. Stripe requires the trial end at least 48 hours in the future
            // so that there is still at least a one day trial if your customer pays at the end of the 24 hours.
            // We also add 10 seconds of extra time to account for any delay with an API request onto Stripe.
            $minimumTrialPeriod = Carbon::now()->addHours(48)->addSeconds(10);

            $trialEnd = $this->trialExpires->gt($minimumTrialPeriod) ? $this->trialExpires : $minimumTrialPeriod;
        } else {
            $trialEnd = null;
        }

        $billingCycleAnchor = $trialEnd === null ? $this->billingCycleAnchor : null;

        $payload = array_filter([
            'subscriptionItems' => Collection::make($this->items)->values()->all(),
            'mode' => Session::MODE_SUBSCRIPTION,
            'subscription' => array_filter([
                'trialEnd' => $trialEnd ? $trialEnd->getTimestamp() : null,
                'metadata' => array_merge($this->metadata, [
                    'name' => $this->type,
                    'type' => $this->type,
                ]),
            ]),
        ]);

        return Checkout::customer($this->owner, $this)
            ->create([], array_merge_recursive($payload, $sessionOptions), $customerOptions);
    }

    /**
     * Get the tax rates for the Stripe payload.
     *
     * @return array|null
     */
    protected function getTaxRatesForPayload()
    {
        if ($taxRates = $this->owner->taxRates()) {
            return $taxRates;
        }
    }


}
