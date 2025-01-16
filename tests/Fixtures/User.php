<?php

namespace Laravel\CashierChargebee\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Model;
use Illuminate\Notifications\Notifiable;
use Laravel\CashierChargebee\Billable;

class User extends Model
{
    use Billable, Notifiable;

    protected $guarded = [];
}
