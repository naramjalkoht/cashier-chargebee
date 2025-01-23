<?php

namespace Laravel\CashierChargebee;

use Illuminate\Database\Eloquent\Model;
use Laravel\CashierChargebee\Concerns\AllowsCoupons;
use Laravel\CashierChargebee\Concerns\Prorates;

class Subscription extends Model
{
    use Prorates;
    use AllowsCoupons;

}
