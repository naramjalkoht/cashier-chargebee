<?php

namespace Laravel\CashierChargebee\Tests\Feature;

use ChargeBee\ChargeBee\Models\Customer;
use Laravel\CashierChargebee\Tests\Fixtures\User;

class CustomerTest extends FeatureTestCase
{
    public function test_it_can_fetch_customer_by_chargebee_id(): void
    {
        // NOTE: This test is only temporary and assumes the existence of 'cbdemo_douglas'.
        // Once a function for creating customers in Chargebee is implemented:
        // 1. Create a customer.
        // 2. Fetch the created customer and validate the response.

        $response = Customer::retrieve('cbdemo_douglas');

        $this->assertNotNull($response);
        $this->assertSame('cbdemo_douglas', $response->customer()->id);
    }

    public function test_we_can_set_ip_address_for_tax(): void
    {
        $testIp = '10.10.10.1';
        $user = new User;
        $user->withTaxIpAddress($testIp);

        $this->assertSame($testIp, $user->customerIpAddress);
    }
}
