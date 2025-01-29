<?php

namespace Laravel\CashierChargebee\Tests\Unit;

use ChargeBee\ChargeBee\Models\PaymentIntent;
use Laravel\CashierChargebee\Payment;
use Laravel\CashierChargebee\Cashier;
use Laravel\CashierChargebee\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Mockery;

class PaymentTest extends TestCase
{
    public function test_as_chargebee_payment_intent(): void
    {
        $paymentIntent = new PaymentIntent(['id' => 'id_123']);
        $payment = new Payment($paymentIntent);

        $this->assertSame($paymentIntent, $payment->asChargebeePaymentIntent());
    }

    public function test_to_array(): void
    {
        $paymentIntent = new PaymentIntent(['id' => 'id_123']);
        $payment = new Payment($paymentIntent);

        $this->assertSame(['id' => 'id_123'], $payment->toArray());
    }

    public function test_to_json(): void
    {
        $paymentIntent = new PaymentIntent(['id' => 'id_123']);
        $payment = new Payment($paymentIntent);

        $this->assertSame(json_encode(['id' => 'id_123']), $payment->toJson());
    }

    public function test_json_serialize(): void
    {
        $paymentIntent = new PaymentIntent(['id' => 'id_123']);
        $payment = new Payment($paymentIntent);

        $this->assertSame(['id' => 'id_123'], $payment->jsonSerialize());
    }

    public function test_dynamic_property_access(): void
    {
        $paymentIntent = new PaymentIntent(['id' => 'id_123', 'currencyCode' => 'EUR']);
        $payment = new Payment($paymentIntent);

        $this->assertSame('EUR', $payment->currencyCode);
    }
}
