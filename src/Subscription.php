<?php

namespace Laravel\CashierChargebee;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use ChargeBee\ChargeBee\Models\ItemPrice;
use ChargeBee\ChargeBee\Models\Subscription as ChargebeeSubscription;
use ChargeBee\ChargeBee\Models\Usage;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Laravel\CashierChargebee\Cashier;
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

    /**
     * Increment the quantity of the subscription.
     *
     * @throws \InvalidArgumentException
     */
    public function incrementQuantity(int $count = 1, ?string $price = null, bool $invoiceImmediately = false): static
    {
        // Find the SubscriptionItem by price or get the only one if no price is specified
        if ($price) {
            $subscriptionItem = $this->findItemOrFail($price);
        } else {
            $this->guardAgainstMultiplePrices();
            $subscriptionItem = $this->items()->first();
        }

        return $this->updateQuantity($subscriptionItem->quantity + $count, $price, $invoiceImmediately);
    }

    /**
     *  Increment the quantity of the subscription, and invoice immediately.
     * @throws \InvalidArgumentException
     */
    public function incrementAndInvoice(int $count = 1, ?string $price = null): static
    {
        return $this->incrementQuantity($count, $price, true);
    }

    /**
     * Decrement the quantity of the subscription.
     *
     * @throws \InvalidArgumentException
     */
    public function decrementQuantity(int $count = 1, ?string $price = null): static
    {
        // Find the SubscriptionItem by price or get the only one if no price is specified
        if ($price) {
            $subscriptionItem = $this->findItemOrFail($price);
        } else {
            $this->guardAgainstMultiplePrices();
            $subscriptionItem = $this->items()->first();
        }

        return $this->updateQuantity(max(1, $subscriptionItem->quantity - $count), $price);
    }

    /**
     * Update the quantity of the subscription.
     *
     * @throws \InvalidArgumentException
     */
    public function updateQuantity(int $quantity, ?string $price = null, bool $invoiceImmediately = false): static
    {
        // Find the SubscriptionItem by price or get the only one if no price is specified
        if ($price) {
            $subscriptionItem = $this->findItemOrFail($price);
        } else {
            $this->guardAgainstMultiplePrices();
            $subscriptionItem = $this->items()->first();
        }

        // Retrieve subscription items from Chargebee and update the quantity of the specified item
        $chargebeeSubscription = $this->asChargebeeSubscription();

        $subscriptionItems = array_map(function ($item) use ($subscriptionItem, $quantity) {
            if ($item->itemPriceId === $subscriptionItem->chargebee_price) {
                return array_merge($item->getValues(), ['quantity' => $quantity]);
            }
            return $item->getValues();
        }, $chargebeeSubscription->subscriptionItems);
        
        // Prepare the data for $this->updateChargebeeSubscription
        $updateData = [
            'replaceItemsList' => true,
            'subscriptionItems' => array_values($subscriptionItems),
        ];

        if (! is_null($this->prorateBehavior())) {
            $updateData['prorate'] = $this->prorateBehavior();
        }

        if ($invoiceImmediately) {
            $updateData['invoiceImmediately'] = true;
        }

        // Update subscription items in Chargebee
        $updatedChargebeeSubscription = $this->updateChargebeeSubscription($updateData);
        
        // Update the local Subscription model
        $this->fill([
            'chargebee_status' => $updatedChargebeeSubscription->status,
        ])->save();

        if ($this->hasSinglePrice()) {
            $this->fill([
                'quantity' => $quantity,
            ])->save();
        }

        // Update the local SubscriptionItem model
        $subscriptionItem->update(['quantity' => $quantity]);
        
        $this->refresh();

        return $this;
    }

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

        if (! is_null($this->prorateBehavior())) {
            $updateData['prorate'] = $this->prorateBehavior();
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

        if (! is_null($this->prorateBehavior())) {
            $updateData['prorate'] = $this->prorateBehavior();
        }

        $this->updateChargebeeSubscription($updateData);

        $this->trial_ends_at = $date;

        $this->save();

        return $this;
    }

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

        if (! is_null($this->prorateBehavior())) {
            $updateData['prorate'] = $this->prorateBehavior();
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
            "resumeOption" => "immediately",
        ])->subscription();

        $this->fill([
            'chargebee_status' => $chargebeeSubscription->status,
        ])->save();

        return $this;
    }

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
