<?php

namespace Laravel\CashierChargebee;

use ChargeBee\ChargeBee\Models\SubscriptionSubscriptionItem;
use ChargeBee\ChargeBee\Models\Usage;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Laravel\CashierChargebee\Concerns\Prorates;
use Laravel\CashierChargebee\Database\Factories\SubscriptionItemFactory;

class SubscriptionItem extends Model
{
    use HasFactory, Prorates;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * Get the subscription that the item belongs to.
     */
    public function subscription(): BelongsTo
    {
        $model = Cashier::$subscriptionModel;

        return $this->belongsTo($model, (new $model)->getForeignKey());
    }

    /**
     * Report usage for a metered product.
     */
    public function reportUsage(int $quantity = 1, DateTimeInterface|int|null $timestamp = null): Usage
    {
        $timestamp = $timestamp instanceof DateTimeInterface ? $timestamp->getTimestamp() : $timestamp;

        $result = Usage::create($this->subscription->chargebee_id, [
            'itemPriceId' => $this->chargebee_price,
            'quantity' => $quantity,
            'usageDate' => $timestamp ?? time(),
        ]);

        return $result->usage();
    }

    /**
     * Get the usage records for a metered product.
     */
    public function usageRecords(array $options = []): Collection
    {
        $all = Usage::all(array_merge([
            'subscriptionId[is]' => $this->subscription->chargebee_id,
            'itemPriceId[is]' => $this->chargebee_price,
        ], $options));

        $usageRecords = collect($all)->map(function ($entry) {
            return $entry->usage();
        });

        return $usageRecords;
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return SubscriptionItemFactory::new();
    }
}
