<?php

namespace Laravel\CashierChargebee;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use ChargeBee\ChargeBee\Models\ItemPrice;
use ChargeBee\ChargeBee\Models\Subscription as ChargebeeSubscription;
use ChargeBee\ChargeBee\Models\Usage;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Laravel\CashierChargebee\Concerns\AllowsCoupons;
use Laravel\CashierChargebee\Concerns\Prorates;
use Laravel\CashierChargebee\Database\Factories\SubscriptionFactory;
use Laravel\CashierChargebee\Exceptions\SubscriptionUpdateFailure;
use Laravel\CashierChargebee\SubscriptionItem;
use LogicException;

class Subscription extends Model
{
    use AllowsCoupons, HasFactory, Prorates;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The relationships that should always be loaded.
     *
     * @var array
     */
    protected $with = ['items'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'ends_at' => 'datetime',
        'quantity' => 'integer',
        'trial_ends_at' => 'datetime',
    ];

    /**
     * The date on which the billing cycle should be anchored.
     *
     * @var string|null
     */
    protected $billingCycleAnchor = null;

    /**
     * Get the user that owns the subscription.
     */
    public function user(): BelongsTo
    {
        return $this->owner();
    }

    /**
     * Get the model related to the subscription.
     */
    public function owner(): BelongsTo
    {
        $model = Cashier::$customerModel;

        return $this->belongsTo($model, (new $model)->getForeignKey());
    }

    /**
     * Get the subscription items related to the subscription.
     */
    public function items(): HasMany
    {
        return $this->hasMany(Cashier::$subscriptionItemModel);
    }

    /**
     * Determine if the subscription has multiple prices.
     */
    public function hasMultiplePrices(): bool
    {
        return is_null($this->chargebee_price);
    }

    /**
     * Determine if the subscription has a single price.
     */
    public function hasSinglePrice(): bool
    {
        return ! $this->hasMultiplePrices();
    }

    /**
     * Determine if the subscription has a specific product.
     */
    public function hasProduct(string $product): bool
    {
        return $this->items->contains(function (SubscriptionItem $item) use ($product) {
            return $item->chargebee_product === $product;
        });
    }

    /**
     * Determine if the subscription has a specific price.
     */
    public function hasPrice(string $price): bool
    {
        if ($this->hasMultiplePrices()) {
            return $this->items->contains(function (SubscriptionItem $item) use ($price) {
                return $item->chargebee_price === $price;
            });
        }

        return $this->chargebee_price === $price;
    }

    /**
     * Get the subscription item for the given price.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findItemOrFail(string $price): SubscriptionItem
    {
        return $this->items()->where('chargebee_price', $price)->firstOrFail();
    }

    /**
     * Determine if the subscription is active, on trial, or within its grace period.
     */
    public function valid(): bool
    {
        return $this->active() || $this->onTrial() || $this->onGracePeriod();
    }

    /**
     * Determine if the subscription is active.
     */
    public function active(): bool
    {
        return $this->chargebee_status === 'active';
    }

    /**
     * Filter query by active.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('chargebee_status', 'active');
    }

    /**
     * Sync the Chargebee status of the subscription.
     */
    public function syncChargebeeStatus(): void
    {
        $subscription = $this->asChargebeeSubscription();

        $this->chargebee_status = $subscription->status;

        $this->save();
    }

    /**
     * Determine if the subscription is recurring and not on trial.
     */
    public function recurring(): bool
    {
        return ! $this->onTrial() && ! $this->canceled();
    }

    /**
     * Filter query by recurring.
     */
    public function scopeRecurring(Builder $query): void
    {
        $query->notOnTrial()->notCanceled();
    }

    /**
     * Determine if the subscription is no longer active.
     */
    public function canceled(): bool
    {
        return ! is_null($this->ends_at);
    }

    /**
     * Filter query by canceled.
     */
    public function scopeCanceled(Builder $query): void
    {
        $query->whereNotNull('ends_at');
    }

    /**
     * Filter query by not canceled.
     */
    public function scopeNotCanceled(Builder $query): void
    {
        $query->whereNull('ends_at');
    }

    /**
     * Determine if the subscription has ended and the grace period has expired.
     */
    public function ended(): bool
    {
        return $this->canceled() && ! $this->onGracePeriod();
    }

    /**
     * Filter query by ended.
     */
    public function scopeEnded(Builder $query): void
    {
        $query->canceled()->notOnGracePeriod();
    }

    /**
     * Determine if the subscription is within its trial period.
     */
    public function onTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Filter query by on trial.
     */
    public function scopeOnTrial(Builder $query): void
    {
        $query->whereNotNull('trial_ends_at')->where('trial_ends_at', '>', Carbon::now());
    }

    /**
     * Determine if the subscription's trial has expired.
     */
    public function hasExpiredTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    /**
     * Filter query by expired trial.
     */
    public function scopeExpiredTrial(Builder $query): void
    {
        $query->whereNotNull('trial_ends_at')->where('trial_ends_at', '<', Carbon::now());
    }

    /**
     * Filter query by not on trial.
     */
    public function scopeNotOnTrial(Builder $query): void
    {
        $query->whereNull('trial_ends_at')->orWhere('trial_ends_at', '<=', Carbon::now());
    }

    /**
     * Determine if the subscription is within its grace period after cancellation.
     */
    public function onGracePeriod(): bool
    {
        return $this->ends_at && $this->ends_at->isFuture();
    }

    /**
     * Filter query by on grace period.
     */
    public function scopeOnGracePeriod(Builder $query): void
    {
        $query->whereNotNull('ends_at')->where('ends_at', '>', Carbon::now());
    }

    /**
     * Filter query by not on grace period.
     */
    public function scopeNotOnGracePeriod(Builder $query): void
    {
        $query->whereNull('ends_at')->orWhere('ends_at', '<=', Carbon::now());
    }

    /**
     * Determine if the subscription is paused.
     */
    public function paused(): bool
    {
        return $this->chargebee_status === 'paused';
    }

    // /**
    //  * Increment the quantity of the subscription.
    //  *
    //  * @throws \Laravel\CashierChargebee\Exceptions\SubscriptionUpdateFailure
    //  */
    // public function incrementQuantity(int $count = 1, ?string $price = null): static
    // {
    //     $this->guardAgainstIncomplete();

    //     if ($price) {
    //         $this->findItemOrFail($price)
    //             ->setPaymentBehavior($this->paymentBehavior)
    //             ->setProrationBehavior($this->prorationBehavior)
    //             ->incrementQuantity($count);

    //         return $this->refresh();
    //     }

    //     $this->guardAgainstMultiplePrices();

    //     return $this->updateQuantity($this->quantity + $count, $price);
    // }

    // /**
    //  *  Increment the quantity of the subscription, and invoice immediately.
    //  *
    //  * @throws \Laravel\CashierChargebee\Exceptions\IncompletePayment
    //  * @throws \Laravel\CashierChargebee\Exceptions\SubscriptionUpdateFailure
    //  */
    // public function incrementAndInvoice(int $count = 1, ?string $price = null): static
    // {
    //     $this->guardAgainstIncomplete();

    //     $this->alwaysInvoice();

    //     return $this->incrementQuantity($count, $price);
    // }

    // /**
    //  * Decrement the quantity of the subscription.
    //  *
    //  * @throws \Laravel\CashierChargebee\Exceptions\SubscriptionUpdateFailure
    //  */
    // public function decrementQuantity(int $count = 1, ?string $price = null): static
    // {
    //     $this->guardAgainstIncomplete();

    //     if ($price) {
    //         $this->findItemOrFail($price)
    //             ->setPaymentBehavior($this->paymentBehavior)
    //             ->setProrationBehavior($this->prorationBehavior)
    //             ->decrementQuantity($count);

    //         return $this->refresh();
    //     }

    //     $this->guardAgainstMultiplePrices();

    //     return $this->updateQuantity(max(1, $this->quantity - $count), $price);
    // }

    // /**
    //  * Update the quantity of the subscription.
    //  *
    //  * @throws \Laravel\CashierChargebee\Exceptions\SubscriptionUpdateFailure
    //  */
    // public function updateQuantity(int $quantity, ?string $price = null): static
    // {
    //     if ($price) {
    //         $this->findItemOrFail($price)
    //             ->setProrationBehavior($this->prorationBehavior)
    //             ->updateQuantity($quantity);

    //         return $this->refresh();
    //     }

    //     $this->guardAgainstMultiplePrices();

    //     $chargebeeSubscription = $this->updateChargebeeSubscription([
    //         'payment_behavior' => $this->paymentBehavior(),
    //         'proration_behavior' => $this->prorateBehavior(),
    //         'quantity' => $quantity,
    //     ]);

    //     $this->fill([
    //         'chargebee_status' => $chargebeeSubscription->status,
    //         'quantity' => $chargebeeSubscription->quantity,
    //     ])->save();

    //     $this->handlePaymentFailure($this);

    //     return $this;
    // }

    /**
     * Report usage for a metered product.
     */
    public function reportUsage(int $quantity = 1, DateTimeInterface|int|null $timestamp = null, ?string $price = null): Usage
    {
        if (! $price) {
            $this->guardAgainstMultiplePrices();
        }

        return $this->findItemOrFail($price ?? $this->chargebee_price)->reportUsage($quantity, $timestamp);
    }

    /**
     * Report usage for specific price of a metered product.
     */
    public function reportUsageFor(string $price, int $quantity = 1, DateTimeInterface|int|null $timestamp = null): Usage
    {
        return $this->reportUsage($quantity, $timestamp, $price);
    }

    /**
     * Get the usage records for a metered product.
     */
    public function usageRecords(array $options = [], ?string $price = null): Collection
    {
        if (! $price) {
            $this->guardAgainstMultiplePrices();
        }

        return $this->findItemOrFail($price ?? $this->chargebee_price)->usageRecords($options);
    }

    /**
     * Get the usage records for a specific price of a metered product.
     */
    public function usageRecordsFor(string $price, array $options = []): Collection
    {
        return $this->usageRecords($options, $price);
    }

    /**
     * Change the billing cycle anchor on a price change.
     */
    public function anchorBillingCycleOn(DateTimeInterface|int|string $date = 'now'): static
    {
        if ($date instanceof DateTimeInterface) {
            $date = $date->getTimestamp();
        }

        $this->billingCycleAnchor = $date;

        return $this;
    }

    /**
     * Force the trial to end immediately.
     *
     * This method must be combined with swap, resume, etc.
     */
    public function skipTrial(): static
    {
        $this->trial_ends_at = null;

        return $this;
    }

    /**
     * Force the subscription's trial to end immediately.
     */
    public function endTrial(): static
    {
        if (is_null($this->trial_ends_at)) {
            return $this;
        }

        $updateData = ['trialEnd' => 0];

        $prorateBehavior = $this->prorateBehavior();
        if (! is_null($prorateBehavior)) {
            $updateData['prorate'] = $prorateBehavior;
        }

        $this->updateChargebeeSubscription($updateData);

        $this->trial_ends_at = null;
        $this->save();

        return $this;
    }

    /**
     * Extend an existing subscription's trial period.
     */
    public function extendTrial(CarbonInterface $date): self
    {
        if (! $date->isFuture()) {
            throw new InvalidArgumentException("Extending a subscription's trial requires a date in the future.");
        }

        $updateData = ['trialEnd' => $date->getTimestamp()];

        $prorateBehavior = $this->prorateBehavior();
        if (! is_null($prorateBehavior)) {
            $updateData['prorate'] = $prorateBehavior;
        }

        $this->updateChargebeeSubscription($updateData);

        $this->trial_ends_at = $date;

        $this->save();

        return $this;
    }

    // /**
    //  * Swap the subscription to new Chargebee prices.
    //  *
    //  * @throws \Laravel\Cashier\Exceptions\IncompletePayment
    //  * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
    //  */
    // public function swap(string|array $prices, array $options = []): self
    // {
    //     if (empty($prices = (array) $prices)) {
    //         throw new InvalidArgumentException('Please provide at least one price when swapping.');
    //     }

    //     $this->guardAgainstIncomplete();

    //     $items = $this->mergeItemsThatShouldBeDeletedDuringSwap(
    //         $this->parseSwapPrices($prices)
    //     );

    //     $chargebeeSubscription = $this->owner->stripe()->subscriptions->update(
    //         $this->chargebee_id, $this->getSwapOptions($items, $options)
    //     );

    //     $firstItem = $chargebeeSubscription->items->first();
    //     $isSinglePrice = $chargebeeSubscription->items->count() === 1;

    //     $this->fill([
    //         'chargebee_status' => $chargebeeSubscription->status,
    //         'chargebee_price' => $isSinglePrice ? $firstItem->price->id : null,
    //         'quantity' => $isSinglePrice ? ($firstItem->quantity ?? null) : null,
    //         'ends_at' => null,
    //     ])->save();

    //     $subscriptionItemIds = [];

    //     foreach ($chargebeeSubscription->items as $item) {
    //         $subscriptionItemIds[] = $item->id;

    //         $this->items()->updateOrCreate([
    //             'chargebee_id' => $item->id,
    //         ], [
    //             'chargebee_product' => $item->price->product,
    //             'chargebee_price' => $item->price->id,
    //             'quantity' => $item->quantity ?? null,
    //         ]);
    //     }

    //     // Delete items that aren't attached to the subscription anymore...
    //     $this->items()->whereNotIn('chargebee_id', $subscriptionItemIds)->delete();

    //     $this->unsetRelation('items');

    //     $this->handlePaymentFailure($this);

    //     return $this;
    // }

    // /**
    //  * Swap the subscription to new Chargebee prices, and invoice immediately.
    //  *
    //  * @throws \Laravel\Cashier\Exceptions\IncompletePayment
    //  * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
    //  */
    // public function swapAndInvoice(string|array $prices, array $options = []): self
    // {
    //     $this->alwaysInvoice();

    //     return $this->swap($prices, $options);
    // }

    // /**
    //  * Parse the given prices for a swap operation.
    //  */
    // protected function parseSwapPrices(array $prices): Collection
    // {
    //     $isSinglePriceSwap = $this->hasSinglePrice() && count($prices) === 1;

    //     return Collection::make($prices)->mapWithKeys(function ($options, $price) use ($isSinglePriceSwap) {
    //         $price = is_string($options) ? $options : $price;

    //         $options = is_string($options) ? [] : $options;

    //         $payload = [
    //             'tax_rates' => $this->getPriceTaxRatesForPayload($price),
    //         ];

    //         if (! isset($options['price_data'])) {
    //             $payload['price'] = $price;
    //         }

    //         if ($isSinglePriceSwap && ! is_null($this->quantity)) {
    //             $payload['quantity'] = $this->quantity;
    //         }

    //         return [$price => array_merge($payload, $options)];
    //     });
    // }

    // /**
    //  * Merge the items that should be deleted during swap into the given items collection.
    //  */
    // protected function mergeItemsThatShouldBeDeletedDuringSwap(Collection $items): Collection
    // {
    //     foreach ($this->asChargebeeSubscription()->items->data as $chargebeeSubscriptionItem) {
    //         $price = $chargebeeSubscriptionItem->price;

    //         if (! $item = $items->get($price->id, [])) {
    //             $item['deleted'] = true;

    //             if ($price->recurring->usage_type == 'metered') {
    //                 $item['clear_usage'] = true;
    //             }
    //         }

    //         $items->put($price->id, $item + ['id' => $chargebeeSubscriptionItem->id]);
    //     }

    //     return $items;
    // }

    // /**
    //  * Get the options array for a swap operation.
    //  */
    // protected function getSwapOptions(Collection $items, array $options = []): array
    // {
    //     $payload = array_filter([
    //         'items' => $items->values()->all(),
    //         'payment_behavior' => $this->paymentBehavior(),
    //         'promotion_code' => $this->promotionCodeId,
    //         'proration_behavior' => $this->prorateBehavior(),
    //         'expand' => ['latest_invoice.payment_intent'],
    //     ]);

    //     if ($payload['payment_behavior'] !== ChargebeeSubscription::PAYMENT_BEHAVIOR_PENDING_IF_INCOMPLETE) {
    //         $payload['cancel_at_period_end'] = false;
    //     }

    //     $payload = array_merge($payload, $options);

    //     if (! is_null($this->billingCycleAnchor)) {
    //         $payload['billing_cycle_anchor'] = $this->billingCycleAnchor;
    //     }

    //     $payload['trial_end'] = $this->onTrial()
    //                     ? $this->trial_ends_at->getTimestamp()
    //                     : 'now';

    //     return $payload;
    // }

    /**
     * Add a new Chargebee price to the subscription.
     *
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function addPrice(string $price, ?int $quantity = 1, array $options = []): self
    {
        if ($this->items->contains('chargebee_price', $price)) {
            throw SubscriptionUpdateFailure::duplicatePrice($this, $price);
        }

        $subscriptionItem = [
            'itemPriceId' => $price,
        ];

        if (!is_null($quantity)) {
            $subscriptionItem['quantity'] = $quantity;
        }

        $chargebeeSubscription = $this->updateChargebeeSubscription(array_filter(array_merge([
            'subscriptionItems' => array($subscriptionItem),
            'prorate' => $this->prorateBehavior(),
        ], $options)), fn($value) => !is_null($value));

        $priceDetails = ItemPrice::retrieve($price)->itemPrice();
        $this->items()->create([
            'chargebee_product' => $priceDetails->itemId,
            'chargebee_price' => $price,
            'quantity' => $quantity,
        ]);

        $this->unsetRelation('items');

        if ($this->hasSinglePrice()) {
            $this->fill([
                'chargebee_price' => null,
                'quantity' => null,
            ]);
        }

        $this->fill([
            'chargebee_status' => $chargebeeSubscription->status,
        ])->save();

        return $this;
    }

    /**
     * Add a new Chargebee price to the subscription, and invoice immediately.
     *
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function addPriceAndInvoice(string $price, ?int $quantity = 1, array $options = []): self
    {
        $options = array_merge($options, [
            'invoiceImmediately' => true,
        ]);

        return $this->addPrice($price, $quantity, $options);
    }

    /**
     * Add a new Chargebee metered price to the subscription.
     *
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function addMeteredPrice(string $price, array $options = []): self
    {
        return $this->addPrice($price, null, $options);
    }

    /**
     * Add a new Chargebee metered price to the subscription, and invoice immediately.
     *
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function addMeteredPriceAndInvoice(string $price, array $options = []): self
    {
        return $this->addPriceAndInvoice($price, null, $options);
    }

    /**
     * Remove a Chargebee price from the subscription.
     *
     * @throws \Laravel\CashierChargebee\Exceptions\SubscriptionUpdateFailure
     */
    public function removePrice(string $price): self
    {
        if ($this->hasSinglePrice()) {
            throw SubscriptionUpdateFailure::cannotDeleteLastPrice($this);
        }

        $chargebeeSubscription = $this->asChargebeeSubscription();


        $subscriptionItems = array_filter($chargebeeSubscription->subscriptionItems, function ($item) use ($price) {
            return $item->itemPriceId !== $price;
        });

        $subscriptionItems = array_map(fn($item) => $item->getValues(), $subscriptionItems);

        $updateData = [
            'replaceItemsList' => true,
            'subscriptionItems' => array_values($subscriptionItems),
        ];

        $prorateBehavior = $this->prorateBehavior();
        if (!is_null($prorateBehavior)) {
            $updateData['prorate'] = $prorateBehavior;
        }

        $this->updateChargebeeSubscription($updateData);

        $this->items()->where('chargebee_price', $price)->delete();

        $this->unsetRelation('items');

        if ($this->items()->count() === 1) {
            $item = $this->items()->first();

            $this->fill([
                'chargebee_price' => $item->chargebee_price,
                'quantity' => $item->quantity,
            ])->save();
        }

        return $this;
    }

    /**
     * Cancel the subscription at the end of the billing period.
     */
    public function cancel(): self
    {
        $chargebeeSubscription = ChargebeeSubscription::cancelForItems($this->chargebee_id, [
            'cancelOption' => 'end_of_term',
        ])->subscription();

        $this->chargebee_status = $chargebeeSubscription->status;

        // If the user was on trial, we will set the grace period to end when the trial
        // would have ended. Otherwise, we'll retrieve the end of the billing period
        // period and make that the end of the grace period for this current user.
        if ($this->onTrial()) {
            $this->ends_at = $this->trial_ends_at;
        } else {
            $this->ends_at = Carbon::createFromTimestamp(
                $chargebeeSubscription->currentTermEnd
            );
        }

        $this->save();

        return $this;
    }

    /**
     * Cancel the subscription at a specific moment in time.
     *
     * @todo creditOptionForCurrentTermCharges
     */
    public function cancelAt(DateTimeInterface|int $endsAt): self
    {
        if ($endsAt instanceof DateTimeInterface) {
            $endsAt = $endsAt->getTimestamp();
        }

        $chargebeeSubscription = ChargebeeSubscription::cancelForItems($this->chargebee_id, [
            'cancelOption' => 'specific_date',
            'cancelAt' => $endsAt,
            'creditOptionForCurrentTermCharges' => $this->getCreditOptionForCurrentCharges(),
        ])->subscription();

        $this->chargebee_status = $chargebeeSubscription->status;

        $this->ends_at = Carbon::createFromTimestamp($chargebeeSubscription->cancelledAt);

        $this->save();

        return $this;
    }

    /**
     * Cancel the subscription immediately without invoicing.
     */
    public function cancelNow(): self
    {
        ChargebeeSubscription::cancelForItems($this->chargebee_id, [
            'cancelOption' => 'immediately',
            'creditOptionForCurrentTermCharges' => $this->getCreditOptionForCurrentCharges(),
        ])->subscription();

        $this->markAsCanceled();

        return $this;
    }

    /**
     * Cancel the subscription immediately and invoice.
     */
    public function cancelNowAndInvoice(): self
    {
        ChargebeeSubscription::cancelForItems($this->chargebee_id, [
            'cancelOption' => 'immediately',
            'unbilledChargesOption' => 'invoice',
        ])->subscription();

        $this->markAsCanceled();

        return $this;
    }

    /**
     * Mark the subscription as canceled.
     *
     * @internal
     */
    public function markAsCanceled(): void
    {
        $this->fill([
            'chargebee_status' => 'cancelled',
            'ends_at' => Carbon::now(),
        ])->save();
    }

    /**
     * Resume the paused subscription.
     *
     * @throws \LogicException
     */
    public function resume(): self
    {
        if (! $this->paused()) {
            throw new LogicException('Only paused subscriptions can be resumed.');
        }

        $chargebeeSubscription = ChargebeeSubscription::resume($this->chargebee_id, [
            'resumeOption' => 'immediately',
        ])->subscription();

        $this->fill([
            'chargebee_status' => $chargebeeSubscription->status,
        ])->save();

        return $this;
    }

    // /**
    //  * Determine if the subscription has pending updates.
    //  */
    // public function pending(): bool
    // {
    //     return ! is_null($this->asChargebeeSubscription()->hasScheduledChanges);
    // }

    // /**
    //  * Invoice the subscription outside of the regular billing cycle.
    //  *
    //  * @throws \Laravel\CashierChargebee\Exceptions\IncompletePayment
    //  */
    // public function invoice(array $options = []): Invoice
    // {
    //     try {
    //         return $this->user->invoice(array_merge($options, ['subscription' => $this->chargebee_id]));
    //     } catch (IncompletePayment $exception) {
    //         // Set the new Chargebee subscription status immediately when payment fails...
    //         $this->fill([
    //             'chargebee_status' => $exception->payment->invoice->subscription->status,
    //         ])->save();

    //         throw $exception;
    //     }
    // }

    // /**
    //  * Get the latest invoice for the subscription.
    //  */
    // public function latestInvoice(array $expand = []): ?Invoice
    // {
    //     $chargebeeSubscription = $this->asChargebeeSubscription(['latest_invoice', ...$expand]);

    //     if ($chargebeeSubscription->latest_invoice) {
    //         return new Invoice($this->owner, $chargebeeSubscription->latest_invoice);
    //     }
    // }

    // /**
    //  * Fetches upcoming invoice for this subscription.
    //  */
    // public function upcomingInvoice(array $options = []): ?Invoice
    // {
    //     if ($this->canceled()) {
    //         return null;
    //     }

    //     return $this->owner->upcomingInvoice(array_merge([
    //         'subscription' => $this->chargebee_id,
    //     ], $options));
    // }

    // /**
    //  * Preview the upcoming invoice with new Chargebee prices.
    //  */
    // public function previewInvoice(string|array $prices, array $options = []): ?Invoice
    // {
    //     if (empty($prices = (array) $prices)) {
    //         throw new InvalidArgumentException('Please provide at least one price when swapping.');
    //     }

    //     $this->guardAgainstIncomplete();

    //     $items = $this->mergeItemsThatShouldBeDeletedDuringSwap(
    //         $this->parseSwapPrices($prices)
    //     );

    //     $swapOptions = Collection::make($this->getSwapOptions($items))
    //         ->only([
    //             'billing_cycle_anchor',
    //             'cancel_at_period_end',
    //             'items',
    //             'proration_behavior',
    //             'trial_end',
    //         ])
    //         ->mapWithKeys(function ($value, $key) {
    //             return ["subscription_$key" => $value];
    //         })
    //         ->merge($options)
    //         ->all();

    //     return $this->upcomingInvoice($swapOptions);
    // }

    // /**
    //  * Get a collection of the subscription's invoices.
    //  */
    // public function invoices(bool $includePending = false, array $parameters = []): Collection|Invoice
    // {
    //     return $this->owner->invoices(
    //         $includePending, array_merge($parameters, ['subscription' => $this->chargebee_id])
    //     );
    // }

    // /**
    //  * Get an array of the subscription's invoices, including pending invoices.
    //  */
    // public function invoicesIncludingPending(array $parameters = []): Collection|Invoice
    // {
    //     return $this->invoices(true, $parameters);
    // }

    // /**
    //  * Get the latest payment for a Subscription.
    //  */
    // public function latestPayment(): ?Payment
    // {
    //     $subscription = $this->asChargebeeSubscription(['latest_invoice.payment_intent']);

    //     if ($invoice = $subscription->latest_invoice) {
    //         return $invoice->payment_intent
    //             ? new Payment($invoice->payment_intent)
    //             : null;
    //     }
    // }

    // /**
    //  * The discount that applies to the subscription, if applicable.
    //  */
    // public function discount(): Discount
    // {
    //     $subscription = $this->asChargebeeSubscription(['discount.promotion_code']);

    //     return $subscription->discount
    //         ? new Discount($subscription->discount)
    //         : null;
    // }

    /**
     * Apply coupons to the subscription.
     */
    public function applyCoupon(string|array $coupons): void
    {
        if (! is_array($coupons)) {
            $coupons = array($coupons);
        }
        
        $this->updateChargebeeSubscription([
            'couponIds' => $coupons,
        ]);
    }

    /**
     * Make sure a price argument is provided when the subscription is a subscription with multiple prices.
     *
     * @throws \InvalidArgumentException
     */
    public function guardAgainstMultiplePrices(): void
    {
        if ($this->hasMultiplePrices()) {
            throw new InvalidArgumentException(
                'This method requires a price argument since the subscription has multiple prices.'
            );
        }
    }

    public function getCreditOptionForCurrentCharges(): string
    {
        $prorateBehavior = $this->prorateBehavior();

        if ($prorateBehavior === true) {
            return 'prorate';
        }

        if ($prorateBehavior === false) {
            return 'full';
        }

        return 'none';
    }

    /**
     * Update the underlying Chargebee subscription information for the model.
     */
    public function updateChargebeeSubscription(array $options = []): ChargebeeSubscription
    {
        $result = ChargebeeSubscription::updateForItems($this->chargebee_id, $options);

        return $result->subscription();
    }

    /**
     * Get the subscription as a Chargebee subscription object.
     */
    public function asChargebeeSubscription(): ChargebeeSubscription
    {
        $response = ChargebeeSubscription::retrieve($this->chargebee_id);

        return $response->subscription();
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return SubscriptionFactory::new();
    }
}
