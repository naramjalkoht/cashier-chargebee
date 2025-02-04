<?php

namespace Laravel\CashierChargebee;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use ChargeBee\ChargeBee\Models\Subscription as ChargebeeSubscription;
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
use Laravel\CashierChargebee\Payment;
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
