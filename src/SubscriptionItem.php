<?php

namespace Laravel\CashierChargebee;

use ChargeBee\ChargeBee\Models\SubscriptionSubscriptionItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    // /**
    //  * Update the underlying Chargebee subscription item information for the model.
    //  */
    // public function updateStripeSubscriptionItem(array $options = []): SubscriptionSubscriptionItem
    // {
    //     // return $this->subscription->owner->stripe()->subscriptionItems->update(
    //     //     $this->stripe_id, $options
    //     // );
    // }

    // /**
    //  * Get the subscription item as a Chargebee subscription item object.
    //  */
    // public function asChargebeeSubscriptionItem(): SubscriptionSubscriptionItem
    // {
    //     return $this->subscription->owner->stripe()->subscriptionItems->retrieve(
    //         $this->stripe_id, ['expand' => $expand]
    //     );
    // }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return SubscriptionItemFactory::new();
    }
}
