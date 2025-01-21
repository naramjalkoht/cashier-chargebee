<?php

namespace Laravel\CashierChargebee;

use Laravel\CashierChargebee\Concerns\HandlesTaxes;
use Laravel\CashierChargebee\Concerns\ManagesCustomer;

trait Billable
{
    use HandlesTaxes;
    use ManagesCustomer;
}
