<?php

namespace Laravel\CashierChargebee\Tests\Unit;

use Laravel\CashierChargebee\Exceptions\CustomerAlreadyCreated;
use Laravel\CashierChargebee\Tests\Fixtures\User;
use Laravel\CashierChargebee\Tests\TestCase;

class CustomerTest extends TestCase
{
    public function test_chargebee_customer_cannot_be_created_when_chargebee_id_is_already_set(): void
    {
        $user = new User();
        $user->chargebee_id = 'foo';

        $this->expectException(CustomerAlreadyCreated::class);

        $user->createAsChargebeeCustomer();
    }
}
