<?php

namespace Laravel\CashierChargebee;

use Laravel\CashierChargebee\Concerns\HandlesTaxes;

trait Billable
{
    use HandlesTaxes;
}
