<?php

namespace Laravel\CashierChargebee\Tests\Feature;

use Illuminate\Support\Str;
use Laravel\CashierChargebee\Cashier;
use Laravel\CashierChargebee\Tests\Fixtures\User;

class CustomerTest extends FeatureTestCase
{
    public function test_create_as_chargebee_customer_creates_a_new_customer(): void
    {
        $user = $this->createCustomer('testuser');
        $customer = $user->createAsChargebeeCustomer();

        $this->assertTrue($user->hasChargebeeId());
        $this->assertSame($customer->id, $user->chargebeeId());
        $this->assertSame($customer->email, 'testuser@cashier-chargebee.com');
    }

    public function test_create_as_chargebee_customer_with_options(): void
    {
        $user = $this->createCustomer();

        $options = [
            'firstName' => 'Test',
            'lastName' => 'User',
            'phone' => '123456789',
            'billingAddress' => [
                'firstName' => 'Test',
                'lastName' => 'User',
                'line1' => 'PO Box 9999',
                'city' => 'Walnut',
                'state' => 'California',
                'zip' => '91789',
                'country' => 'US',
            ],
            'locale' => 'fr-FR',
            'metaData' => json_encode([
                'info' => 'This is a test customer.',
            ]),
        ];

        $customer = $user->createAsChargebeeCustomer($options);

        $this->assertTrue($user->hasChargebeeId());
        $this->assertSame($customer->id, $user->chargebeeId());

        $this->assertSame($customer->firstName, 'Test');
        $this->assertSame($customer->lastName, 'User');
        $this->assertSame($customer->phone, '123456789');

        $this->assertSame($customer->billingAddress->firstName, 'Test');
        $this->assertSame($customer->billingAddress->lastName, 'User');
        $this->assertSame($customer->billingAddress->line1, 'PO Box 9999');
        $this->assertSame($customer->billingAddress->city, 'Walnut');
        $this->assertSame($customer->billingAddress->state, 'California');
        $this->assertSame($customer->billingAddress->zip, '91789');
        $this->assertSame($customer->billingAddress->country, 'US');

        $this->assertSame($customer->locale, 'fr-FR');
        $this->assertSame($customer->metaData['info'], 'This is a test customer.');
    }

    public function test_retrieving_chargebee_customer_with_valid_chargebee_id(): void
    {
        $user = $this->createCustomer();
        $user->createAsChargebeeCustomer();

        $customer = $user->asChargebeeCustomer();

        $this->assertSame($user->chargebeeId(), $customer->id);
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
