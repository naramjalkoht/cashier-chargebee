<?php

namespace Laravel\CashierChargebee\Tests\Feature;

use ChargeBee\ChargeBee\Models\PaymentIntent;
use Laravel\CashierChargebee\Payment;

class PaymentTest extends FeatureTestCase
{
    public function test_create_payment(): void
    {
        $user = $this->createCustomer('test_create_payment');
        $user->createAsChargebeeCustomer();

        $payment = $user->createPayment(1000);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertEquals(1000, $payment->amount);
        $this->assertEquals($user->chargebee_id, $payment->customerId);
    }

    public function test_create_payment_with_currency(): void
    {
        $user = $this->createCustomer('test_create_payment_with_currency');
        $user->createAsChargebeeCustomer();

        $payment = $user->createPayment(1000, [
            'currencyCode' => 'EUR',
        ]);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertEquals(1000, $payment->amount);
        $this->assertSame($user->chargebee_id, $payment->customerId);
        $this->assertSame('EUR', $payment->currencyCode);
    }

    public function test_payment_customer_with_existing_customer(): void
    {
        $user = $this->createCustomer('test_payment_with_existing_customer', [
            'chargebee_id' => 'id_123',
        ]);

        $paymentIntent = new PaymentIntent(['customerId' => 'id_123']);

        $payment = new Payment($paymentIntent);

        $this->assertSame($user->id, $payment->customer()->id);
        $this->assertSame($user->id, $payment->customer()->id);
    }

    public function test_payment_customer_when_no_customer_found(): void
    {
        $paymentIntent = new PaymentIntent(['customerId' => 'cus_123']);

        $payment = new Payment($paymentIntent);

        $this->assertNull($payment->customer());
    }
}
