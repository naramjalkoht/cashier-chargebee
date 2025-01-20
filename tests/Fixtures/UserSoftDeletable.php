<?php

namespace Laravel\CashierChargebee\Tests\Fixtures;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\CashierChargebee\Billable;

class UserSoftDeletable extends User
{
    use Billable, Notifiable, SoftDeletes;

    protected $table = 'users';
}
