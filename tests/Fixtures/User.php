<?php

namespace Laravel\CashierChargebee\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Model;
use Illuminate\Notifications\Notifiable;
use Laravel\CashierChargebee\Billable;

class User extends Model
{
    use Billable, Notifiable;

    protected $guarded = [];

    /**
     * Get the default billing address.
     */
    public function chargebeeBillingAddress(): array
    {
        return [
            'firstName' => 'Test',
            'lastName' => 'User',
            'line1' => 'PO Box 9999',
            'city' => 'Walnut',
            'state' => 'California',
            'zip' => '91789',
            'country' => 'US',
        ];
    }

    public function preferredCurrency()
    {
        return config('cashier.currency');
    }
}
