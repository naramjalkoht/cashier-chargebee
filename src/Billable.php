<?php

namespace Laravel\CashierChargebee;

use Laravel\CashierChargebee\Concerns\HandlesTaxes;
use Laravel\CashierChargebee\Concerns\ManagesCustomer;
use Laravel\CashierChargebee\Concerns\ManagesPaymentMethods;

trait Billable
{
    use HandlesTaxes;
    use ManagesCustomer;
    use ManagesPaymentMethods;
}
