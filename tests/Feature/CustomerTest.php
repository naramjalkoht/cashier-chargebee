<?php

namespace Laravel\CashierChargebee\Tests\Feature;

use ChargeBee\ChargeBee\Models\Customer;
use Illuminate\Support\Str;
use Laravel\CashierChargebee\Cashier;
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

    public function test_with_tax_ip_address(): void
    {
        $testIp = '10.10.10.1';
        $user = $this->createCustomer();
        $user->withTaxIpAddress($testIp);

        $this->assertSame($testIp, $user->customerIpAddress);
    }

    public function test_is_automatic_tax_enabled(): void
    {
        $user = $this->createCustomer();
        $reflectedMethod = new \ReflectionMethod(
            User::class,
            'isAutomaticTaxEnabled'
        );

        $this->assertSame(false, $reflectedMethod->invoke($user));
    }

    public function test_with_tax_address(): void
    {
        $country = $postalCode = $state = Str::random();

        $testAddress = [
            'country' => $country,
        ];

        $user = $this->createCustomer();
        $user->withTaxAddress($country);

        $this->assertNotEmpty($user->estimationBillingAddress['country']);
        $this->assertArrayNotHasKey('postal_code', $user->estimationBillingAddress);
        $this->assertArrayNotHasKey('state', $user->estimationBillingAddress);
        $this->assertArrayHasKey('country', $user->estimationBillingAddress);
        $this->assertEquals($user->estimationBillingAddress, $testAddress);

        $testAddress = [
            'country' => $country,
            'postal_code' => $postalCode,
        ];
        $user->withTaxAddress($country, $postalCode);

        $this->assertNotEmpty($user->estimationBillingAddress['country']);
        $this->assertNotEmpty($user->estimationBillingAddress['postal_code']);
        $this->assertArrayNotHasKey('state', $user->estimationBillingAddress);
        $this->assertArrayHasKey('country', $user->estimationBillingAddress);
        $this->assertArrayHasKey('postal_code', $user->estimationBillingAddress);
        $this->assertEquals($user->estimationBillingAddress, $testAddress);

        $testAddress = [
            'country' => $country,
            'postal_code' => $postalCode,
            'state' => $state,
        ];

        $user->withTaxAddress($country, $postalCode, $state);

        $this->assertNotEmpty($user->estimationBillingAddress['country']);
        $this->assertNotEmpty($user->estimationBillingAddress['postal_code']);
        $this->assertNotEmpty($user->estimationBillingAddress['state']);
        $this->assertArrayHasKey('country', $user->estimationBillingAddress);
        $this->assertArrayHasKey('postal_code', $user->estimationBillingAddress);
        $this->assertArrayHasKey('state', $user->estimationBillingAddress);
        $this->assertEquals($user->estimationBillingAddress, $testAddress);
    }

    public function test_automatic_tax_payload(): void
    {
        $user = $this->createCustomer();
        $user->withTaxIpAddress('10.10.10.1');
        $user->withTaxAddress(Str::random());

        $reflectedMethod = new \ReflectionMethod(
            User::class,
            'automaticTaxPayload'
        );

        $result = $reflectedMethod->invoke($user);

        $this->assertArrayHasKey('customer_ip_address', $result);
        $this->assertArrayHasKey('estimation_billing_address', $result);
        $this->assertArrayHasKey('country', $result['estimation_billing_address']);
        $this->assertArrayNotHasKey('enabled', $result);

        Cashier::$calculatesTaxes = true;

        $reflectedMethod = new \ReflectionMethod(
            User::class,
            'automaticTaxPayload'
        );

        $result = $reflectedMethod->invoke($user);
        $this->assertArrayHasKey('enabled', $result);
        $this->assertTrue($result['enabled']);
    }

    public function test_collect_tax_ids(): void
    {
        $user = $this->createCustomer();
        $user->collectTaxIds();

        $this->assertSame(true, $user->collectTaxIds);
    }
}
