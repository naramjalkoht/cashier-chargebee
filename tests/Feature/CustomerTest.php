<?php

namespace Laravel\CashierChargebee\Tests\Feature;

use ChargeBee\ChargeBee\Exceptions\InvalidRequestException;
use ChargeBee\ChargeBee\Exceptions\PaymentException;
use ChargeBee\ChargeBee\Models\Customer;
use ChargeBee\ChargeBee\Models\PaymentIntent;
use ChargeBee\ChargeBee\Models\PaymentSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\CashierChargebee\Cashier;
use Laravel\CashierChargebee\Exceptions\CustomerNotFound;
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

    public function test_update_chargebee_customer_with_valid_chargebee_id(): void
    {
        $user = $this->createCustomer();

        $createOptions = [
            'firstName' => 'Test',
            'lastName' => 'User',
            'billingAddress' => [
                'firstName' => 'Test',
                'lastName' => 'User',
                'line1' => '221B Baker Street',
                'city' => 'London',
                'state' => 'England',
                'country' => 'GB',
            ],
            'metaData' => json_encode([
                'info' => 'This is a test customer.',
            ]),
        ];

        $user->createAsChargebeeCustomer($createOptions);

        $updateOptions = [
            'firstName' => 'UpdateTest',
            'phone' => '123456789',
            'billingAddress' => [
                'firstName' => 'UpdateTest',
                'lastName' => 'User',
                'line1' => '221B Baker Street',
                'city' => 'London',
                'state' => 'England',
                'zip' => 'NW1 6XE',
                'country' => 'GB',
            ],
        ];

        $customer = $user->updateChargebeeCustomer($updateOptions);

        $this->assertSame($customer->firstName, 'UpdateTest');
        $this->assertSame($customer->lastName, 'User');
        $this->assertSame($customer->phone, '123456789');

        $this->assertSame($customer->billingAddress->firstName, 'UpdateTest');
        $this->assertSame($customer->billingAddress->lastName, 'User');
        $this->assertSame($customer->billingAddress->line1, '221B Baker Street');
        $this->assertSame($customer->billingAddress->city, 'London');
        $this->assertSame($customer->billingAddress->state, 'England');
        $this->assertSame($customer->billingAddress->zip, 'NW1 6XE');
        $this->assertSame($customer->billingAddress->country, 'GB');

        $this->assertSame($customer->metaData['info'], 'This is a test customer.');
    }

    public function test_update_chargebee_customer_without_modifying_billing_address(): void
    {
        $user = $this->createCustomer();

        $createOptions = [
            'firstName' => 'Test',
            'lastName' => 'User',
            'metaData' => json_encode([
                'info' => 'This is a test customer.',
            ]),
        ];

        $user->createAsChargebeeCustomer($createOptions);

        $updateOptions = [
            'firstName' => 'UpdateTest',
            'phone' => '123456789',
        ];

        $customer = $user->updateChargebeeCustomer($updateOptions);

        $this->assertSame($customer->firstName, 'UpdateTest');
        $this->assertSame($customer->lastName, 'User');
        $this->assertSame($customer->phone, '123456789');

        $this->assertSame($customer->billingAddress->country, 'US');
        $this->assertSame($customer->billingAddress->firstName, 'Test');
        $this->assertSame($customer->billingAddress->lastName, 'User');
        $this->assertSame($customer->billingAddress->line1, 'PO Box 9999');
        $this->assertSame($customer->billingAddress->city, 'Walnut');
        $this->assertSame($customer->billingAddress->state, 'California');
        $this->assertSame($customer->billingAddress->zip, '91789');
        $this->assertSame($customer->billingAddress->country, 'US');

        $this->assertSame($customer->metaData['info'], 'This is a test customer.');
    }

    public function test_create_or_get_chargebee_customer(): void
    {
        $user = $this->createCustomer('testuser');
        $customer = $user->createOrGetChargebeeCustomer();

        $this->assertTrue($user->hasChargebeeId());
        $this->assertSame($customer->id, $user->chargebeeId());

        $customer = $user->createOrGetChargebeeCustomer();

        $this->assertSame($customer->id, $user->chargebeeId());
    }

    public function test_sync_chargebee_customer_details(): void
    {
        $user = $this->createCustomer('testuser');
        $user->createAsChargebeeCustomer();
        $user->first_name = 'TestSync';
        $user->last_name = 'User';
        $user->email = 'testsyncuser@cashier-chargebee.com';
        $user->phone = '123456789';
        $user->locale = 'fr-FR';
        $user->chargebee_metadata = json_encode([
            'info' => 'This is a test customer.',
        ]);

        $customer = $user->syncChargebeeCustomerDetails();

        $this->assertSame($customer->firstName, 'TestSync');
        $this->assertSame($customer->lastName, 'User');
        $this->assertSame($customer->email, 'testsyncuser@cashier-chargebee.com');
        $this->assertSame($customer->phone, '123456789');
        $this->assertSame($customer->locale, 'fr-FR');
        $this->assertSame($customer->metaData['info'], 'This is a test customer.');
    }

    public function test_update_or_create_chargebee_customer(): void
    {
        $user = $this->createCustomer('testuser');

        $customer = $user->updateOrCreateChargebeeCustomer([
            'firstName' => 'Test',
            'lastName' => 'User',
        ]);

        $this->assertTrue($user->hasChargebeeId());
        $this->assertSame($customer->id, $user->chargebeeId());
        $this->assertSame($customer->firstName, 'Test');
        $this->assertSame($customer->lastName, 'User');

        $customer = $user->updateOrCreateChargebeeCustomer([
            'firstName' => 'Updated',
            'lastName' => 'User',
        ]);

        $this->assertSame($customer->id, $user->chargebeeId());
        $this->assertSame($customer->firstName, 'Updated');
        $this->assertSame($customer->lastName, 'User');
    }

    public function test_sync_or_create_chargebee_customer(): void
    {
        $user = $this->createCustomer('testuser');

        $customer = $user->syncOrCreateChargebeeCustomer([
            'firstName' => 'Test',
            'lastName' => 'User',
        ]);

        $this->assertTrue($user->hasChargebeeId());
        $this->assertSame($customer->id, $user->chargebeeId());
        $this->assertSame($customer->firstName, 'Test');
        $this->assertSame($customer->lastName, 'User');

        $user->first_name = 'TestSynced';
        $user->last_name = 'User';

        $customer = $user->syncOrCreateChargebeeCustomer();

        $this->assertSame($customer->id, $user->chargebeeId());
        $this->assertSame($customer->firstName, 'TestSynced');
        $this->assertSame($customer->lastName, 'User');
    }

    public function test_billing_portal_url(): void
    {
        $user = $this->createCustomer('testuser');
        $user->createAsChargebeeCustomer();

        $url = $user->billingPortalUrl('https://example.com');

        $this->assertMatchesRegularExpression(
            '/^https:\/\/[a-z0-9\-]+\.chargebee\.com\/portal\/v2\/authenticate\?token=[a-zA-Z0-9\-_]+$/',
            $url
        );
    }

    public function test_redirect_to_billing_portal(): void
    {
        $user = $this->createCustomer('testuser');
        $user->createAsChargebeeCustomer();

        $response = $user->redirectToBillingPortal('https://example.com');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertMatchesRegularExpression(
            '/^https:\/\/[a-z0-9\-]+\.chargebee\.com\/portal\/v2\/authenticate\?token=[a-zA-Z0-9\-_]+$/',
            $response->getTargetUrl()
        );
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

    public function test_can_create_setup_intent(): void
    {
        $currency = 'EUR';
        $user = $this->createCustomer();
        $user->createAsChargebeeCustomer();

        $paymentIntent = $this->createSetupIntent($user, $currency);

        $this->assertNotNull($paymentIntent);
        $this->assertSame($user->chargebee_id, $paymentIntent->customerId);
        $this->assertSame(0, $paymentIntent->amount);
        $this->assertSame($currency, $paymentIntent->currencyCode);
    }

    public function test_cannot_create_setup_intent(): void
    {
        $currency = Str::random(3);
        $user = $this->createCustomer();
        $user->createAsChargebeeCustomer();

        $this->expectException(InvalidRequestException::class);
        $paymentIntent = $this->createSetupIntent($user, $currency);

        $this->assertNull($paymentIntent);
    }

    public function test_find_setup_intent(): void
    {
        $currency = 'EUR';
        $user = $this->createCustomer();
        $user->createAsChargebeeCustomer();

        $paymentIntent = $this->createSetupIntent($user, $currency);

        $this->assertNotNull($paymentIntent);
        $this->assertSame($user->chargebee_id, $paymentIntent->customerId);
        $this->assertSame(0, $paymentIntent->amount);
        $this->assertSame($currency, $paymentIntent->currencyCode);

        $findPaymentIntent = $user->findSetupIntent($paymentIntent->id);

        $this->assertNotNull($findPaymentIntent);
        $this->assertSame($user->chargebee_id, $findPaymentIntent->customerId);
        $this->assertSame(0, $findPaymentIntent->amount);
        $this->assertSame($currency, $findPaymentIntent->currencyCode);
    }

    public function test_cannot_find_setup_intent(): void
    {
        $user = $this->createCustomer();
        $user->createAsChargebeeCustomer();
        $this->expectException(InvalidRequestException::class);
        $findPaymentIntent = $user->findSetupIntent(Str::random());

        $this->assertNull($findPaymentIntent);
    }

    private function createSetupIntent(User $user, string $currencyCode): ?PaymentIntent
    {
        return $user->createSetupIntent(['currency_code' => $currencyCode]);
    }

    public function test_get_customer_payment_methods(): void
    {
        $user = $this->createCustomer();
        $paymentMethods = $user->paymentMethods();
        $this->assertInstanceOf(Collection::class, $paymentMethods);
        $this->assertTrue($paymentMethods->isEmpty());

        $user->createAsChargebeeCustomer();
        $paymentMethods = $user->paymentMethods();

        $this->assertNotNull($paymentMethods);
        $this->assertInstanceOf(Collection::class, $paymentMethods);
    }

    public function test_can_add_payment_method(): void
    {
        $user = $this->createCustomer();
        $user->createAsChargebeeCustomer();
        $paymentMethod = $user->addPaymentMethod(
            '4111 1111 1111 1111',
            '123',
            date('Y', strtotime('+ 1 year')),
            date('m', strtotime('+ 1 year')),
            true
        );

        $this->assertNotNull($paymentMethod);
        $this->assertInstanceOf(PaymentSource::class, $paymentMethod);
        $this->assertSame($user->chargebeeId(), $paymentMethod->customerId);

        $customer = $user->asChargebeeCustomer();
        $this->assertSame($customer->primaryPaymentSourceId, $paymentMethod->id);

    }

    public function test_non_chargebee_customer_cannot_add_payment_method(): void
    {
        $user = $this->createCustomer();
        $this->expectException(CustomerNotFound::class);
        $user->addPaymentMethod(
            '4111 1111 1111 1111',
            '123',
            date('Y', strtotime('+ 1 year')),
            date('m', strtotime('+ 1 year')),
        );
    }

    public function test_chargebee_customer_cannot_add_payment_method(): void
    {
        $user = $this->createCustomer();
        $user->createAsChargebeeCustomer();

        $this->expectException(PaymentException::class);
        $user->addPaymentMethod(
            '4111 1111 1111 1111',
            '123',
            date('Y', strtotime('+ 1 year')),
            13,
        );
    }

    public function test_non_chargebee_customer_cannot_delete_payment_method(): void
    {
        $user = $this->createCustomer();
        $this->expectException(CustomerNotFound::class);
        $user->deletePaymentMethod(Str::random());
    }

    public function test_chargebee_customer_can_delete_payment_method(): void
    {
        $user = $this->createCustomer();
        $user->createAsChargebeeCustomer();
        $paymentMethod = $user->addPaymentMethod(
            '4111 1111 1111 1111',
            '123',
            date('Y', strtotime('+ 1 year')),
            date('m', strtotime('+ 1 year')),
            true
        );

        $result = $user->deletePaymentMethod($paymentMethod->id);
        $this->assertInstanceOf(Customer::class, $result);
    }

    public function test_chargebee_customer_cannot_delete_payment_method(): void
    {
        $user = $this->createCustomer();
        $user->createAsChargebeeCustomer();
        $this->expectException(InvalidRequestException::class);
        $user->deletePaymentMethod(Str::random());
    }
}
