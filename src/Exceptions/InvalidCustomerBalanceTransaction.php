<?php

namespace Laravel\CashierChargebee\Exceptions;

use Exception;
use ChargeBee\ChargeBee\Models\PromotionalCredit;

class InvalidCustomerBalanceTransaction extends Exception
{
    /**
     * Create a new CustomerBalanceTransaction instance.
     *
     * @param  \ChargeBee\ChargeBee\Models\PromotionalCredit  $transaction
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @return static
     */
    public static function invalidOwner(PromotionalCredit $transaction, $owner)
    {
        return new static("The transaction `{$transaction->id}` does not belong to customer `$owner->chargebee_id`.");
    }
}
