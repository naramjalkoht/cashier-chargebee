<?php

namespace Laravel\CashierChargebee\Tests\Feature;

use ChargeBee\ChargeBee\Models\Customer;
use ChargeBee\ChargeBee\Models\PaymentSource;
use Laravel\CashierChargebee\Exceptions\InvalidInvoice;
use Laravel\CashierChargebee\Exceptions\CustomerNotFound;
use Laravel\CashierChargebee\Invoice;
use Laravel\CashierChargebee\Tests\Fixtures\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use ChargeBee\ChargeBee\Models\InvoiceLineItem as ChargeBeeInvoiceLineItem;

class InvoicesTest extends FeatureTestCase
{
    public function test_require_stripe_customer_for_invoicing()
    {
        $user = $this->createCustomer('require_stripe_customer_for_invoicing');
        $this->expectException(CustomerNotFound::class);
        $user->newInvoice()->invoice();
    }

    public function test_customer_can_be_invoiced()
    {
        $user = $this->createCustomerWithPaymentSource('customer_can_be_invoiced');
        $invoice = $user->newInvoice()
            ->tabFor('Laracon', 49900)
            ->invoice();

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals(49900, $invoice->total);
    }

    public function test_customer_can_be_invoiced_with_a_price()
    {
        $user = $this->createCustomerWithPaymentSource('customer_can_be_invoiced');
        $price = $this->createItemPrice('Laravel T-shirt', amount: 499);

        $invoice = $user->newInvoice()
            ->tabPrice($price->id, 2)
            ->invoice();


        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals(998, $invoice->total);
    }

    public function test_customer_can_be_invoiced_with_inline_price_data()
    {
        $user = $this->createCustomerWithPaymentSource('customer_can_be_invoiced_with_inline_price_data');

        $response = $user->newInvoice()
            ->tabFor('Laravel T-shirt', 599, [
                'taxable' => false
            ])
            ->invoice();

        $this->assertInstanceOf(Invoice::class, $response);
        $this->assertEquals(599, $response->total);
        $this->assertEquals(false, $response->invoiceLineItems()[0]->isTaxed);
    }

    public function test_find_invoice_by_id()
    {
        $user = $this->createCustomerWithPaymentSource('find_invoice_by_id');
        $invoice = $user->newInvoice()
            ->tabFor('Laravel T-shirt', 49900)
            ->invoice();

        $invoice = $user->findInvoice($invoice->id);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals(49900, $invoice->rawTotal());
    }

    public function test_it_throws_an_exception_if_the_invoice_does_not_belong_to_the_user()
    {
        $user = $this->createCustomerWithPaymentSource('throws_exception_invoice_doesnt_belong_to_user');

        $otherUser = $this->createCustomer('other_user');
        $invoice = $user->newInvoice()
            ->tabFor('Laracon', 49900)
            ->invoice();

        $this->expectException(InvalidInvoice::class);
        $this->expectExceptionMessage(
            "The invoice `{$invoice->id}` does not belong to this customer `$otherUser->stripe_id`."
        );

        $otherUser->findInvoice($invoice->id);
    }

    public function test_find_invoice_by_id_or_fail()
    {
        $user = $this->createCustomerWithPaymentSource('find_invoice_by_id_or_fail');
        $otherUser = $this->createCustomerWithPaymentSource('other_user');
        $invoice = $user->newInvoice()
            ->tabFor('Laracon', 49900)
            ->invoice();


        $this->expectException(AccessDeniedHttpException::class);
        $otherUser->findInvoiceOrFail($invoice->id);
    }

    public function test_customer_can_be_invoiced_with_quantity()
    {
        $user = $this->createCustomerWithPaymentSource('customer_can_be_invoiced_with_quantity');

        $response = $user->newInvoice()
            ->tabFor('Laracon', 1000, ['quantity' => 5])
            ->invoice();

        $this->assertInstanceOf(Invoice::class, $response);
        $this->assertEquals(5000, $response->total);

        $response = $user->tab('Laracon', null, [
            'unit_amount' => 1000,
            'quantity' => 2,
        ]);

        $this->assertInstanceOf(ChargeBeeInvoiceLineItem::class, $response);
        $this->assertEquals(1000, $response->unit_amount);
        $this->assertEquals(2, $response->quantity);
    }

    protected function createCustomerWithPaymentSource($description = 'testuser', array $options = []): User
    {
        $user = $this->createCustomer($description, $options);
        $user->createAsChargebeeCustomer();

        $paymentSource = PaymentSource::createCard([
            'customer_id' => $user->chargebeeId(),
            'replace_primary_payment_source' => true,
            'card' => [
                'number' => '4111 1111 1111 1111',
                'cvv' => '123',
                'expiry_month' => now()->month,
                'expiry_year' => now()->addYear()->year,
            ],
        ])->paymentSource();

        Customer::assignPaymentRole(
            $user->chargebeeId(),
            [
                'payment_source_id' => $paymentSource->id,
                'role' => 'PRIMARY',
            ]
        );
        return $user;
    }
}
