<?php

namespace Laravel\CashierChargebee;

use Laravel\CashierChargebee\Concerns\HandlesTaxes;
use Laravel\CashierChargebee\Concerns\ManagesCustomer;
use Laravel\CashierChargebee\Concerns\ManagesSubscriptions;
use Laravel\CashierChargebee\Concerns\PerformsCharges;

trait Billable
{
    use HandlesTaxes;
    use ManagesCustomer;
    use PerformsCharges;
    use ManagesSubscriptions;
}
