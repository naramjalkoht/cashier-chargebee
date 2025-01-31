<?php

namespace Laravel\CashierChargebee\Tests\Unit;

use Carbon\Carbon;
use Carbon\CarbonTimeZone;
use Laravel\Cashier\Discount;
use Laravel\CashierChargebee\Invoice;
use Laravel\CashierChargebee\Tests\Fixtures\User;
use Laravel\CashierChargebee\Tests\TestCase;
use Mockery as m;
use stdClass;
use Stripe\Customer as StripeCustomer;
use Stripe\Discount as StripeDiscount;
use ChargeBee\ChargeBee\Models\Invoice as ChargeBeeInvoice;

class InvoiceTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();

        parent::tearDown();
    }

    public function test_it_can_return_the_invoice_date()
    {
        $chargebeeInvoice = new ChargeBeeInvoice([
            'customerId' => 'foo',
            'date' => 1560541724
        ]);


        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);

        $date = $invoice->date();

        $this->assertInstanceOf(Carbon::class, $date);
        $this->assertEquals(1560541724, $date->unix());
    }

    public function test_it_can_return_the_invoice_date_with_a_timezone()
    {
        $chargebeeInvoice = new ChargeBeeInvoice([
            'customerId' => 'foo',
            'date' => 1560541724
        ]);

        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);

        $date = $invoice->date('CET');

        $this->assertInstanceOf(CarbonTimeZone::class, $timezone = $date->getTimezone());
        $this->assertEquals('CET', $timezone->getName());
    }

    public function test_it_can_return_its_total()
    {
        $chargebeeInvoice = new ChargeBeeInvoice([
            'customerId' => 'foo',
            'total' => 1000,
            'currencyCode' => 'USD'
        ]);


        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);

        $total = $invoice->realTotal();

        $this->assertEquals('$10.00', $total);
    }

    public function test_it_can_return_its_raw_total()
    {
        $chargebeeInvoice = new ChargeBeeInvoice([
            'customerId' => 'foo',
            'total' => 1000,
            'currencyCode' => 'USD'
        ]);

        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);

        $total = $invoice->rawRealTotal();

        $this->assertEquals(1000, $total);
    }

    public function test_it_returns_a_lower_total_when_there_was_a_starting_balance()
    {
        $chargebeeInvoice = new ChargeBeeInvoice([
            'customerId' => 'foo',
            'total' => 1000,
            'currencyCode' => 'USD',
            'creditsApplied' => '450'
        ]);
        $chargebeeInvoice->customer = 'foo';
        $chargebeeInvoice->total = 1000;
        $chargebeeInvoice->currency = 'USD';
        $chargebeeInvoice->starting_balance = -450;

        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);

        $total = $invoice->total();

        $this->assertEquals('$5.50', $total);
    }

    public function test_it_can_return_its_subtotal()
    {
        $chargebeeInvoice = new ChargeBeeInvoice([
            'customerId' => 'foo',
            'subTotal' => 500,
            'currencyCode' => 'USD'
        ]);

        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);

        $subtotal = $invoice->subtotal();

        $this->assertEquals('$5.00', $subtotal);
    }

    public function test_it_can_determine_when_the_customer_has_a_starting_balance()
    {
        $chargebeeInvoice = new ChargeBeeInvoice();
        $chargebeeInvoice->customer = 'foo';
        $chargebeeInvoice->starting_balance = -450;

        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);

        $this->assertTrue($invoice->hasStartingBalance());
    }

    public function test_it_can_determine_when_the_customer_does_not_have_a_starting_balance()
    {
        $chargebeeInvoice = new ChargeBeeInvoice();
        $chargebeeInvoice->customer = 'foo';
        $chargebeeInvoice->starting_balance = 0;

        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);

        $this->assertFalse($invoice->hasStartingBalance());
    }

    public function test_it_can_return_its_starting_balance()
    {
        $chargebeeInvoice = new ChargeBeeInvoice();
        $chargebeeInvoice->customer = 'foo';
        $chargebeeInvoice->starting_balance = -450;
        $chargebeeInvoice->currency = 'USD';

        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);

        $this->assertEquals('-$4.50', $invoice->startingBalance());
        $this->assertEquals(-450, $invoice->rawStartingBalance());
    }

    public function test_it_can_return_its_ending_balance()
    {
        $chargebeeInvoice = new ChargeBeeInvoice();
        $chargebeeInvoice->customer = 'foo';
        $chargebeeInvoice->ending_balance = -450;
        $chargebeeInvoice->currency = 'USD';

        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);

        $this->assertEquals('-$4.50', $invoice->endingBalance());
        $this->assertEquals(-450, $invoice->rawEndingBalance());
    }

    public function test_it_can_return_its_applied_balance()
    {
        $chargebeeInvoice = new ChargeBeeInvoice();
        $chargebeeInvoice->customer = 'foo';
        $chargebeeInvoice->ending_balance = -350;
        $chargebeeInvoice->starting_balance = -500;
        $chargebeeInvoice->currency = 'USD';

        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);

        $this->assertTrue($invoice->hasAppliedBalance());
        $this->assertEquals('-$1.50', $invoice->appliedBalance());
        $this->assertEquals(-150, $invoice->rawAppliedBalance());
    }

    public function test_it_can_return_its_applied_balance_when_depleted()
    {
        $chargebeeInvoice = new ChargeBeeInvoice();
        $chargebeeInvoice->customer = 'foo';
        $chargebeeInvoice->ending_balance = 0;
        $chargebeeInvoice->starting_balance = -500;
        $chargebeeInvoice->currency = 'USD';

        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);

        $this->assertTrue($invoice->hasAppliedBalance());
        $this->assertEquals('-$5.00', $invoice->appliedBalance());
        $this->assertEquals(-500, $invoice->rawAppliedBalance());
    }

    public function test_it_can_determine_if_it_has_a_discount_applied()
    {
        $discountAmount = new stdClass();
        $discountAmount->amount = 50;
        $discountAmount->discount = $discount = new StripeDiscount('foo');

        $otherDiscountAmount = new stdClass();
        $otherDiscountAmount->amount = 100;
        $otherDiscountAmount->discount = $otherDiscount = new StripeDiscount('bar');

        $chargebeeInvoice = new ChargeBeeInvoice();
        $chargebeeInvoice->total_discount_amounts = [$discountAmount, $otherDiscountAmount];
        $chargebeeInvoice->customer = 'foo';
        $chargebeeInvoice->discounts = [$discount, $otherDiscount];

        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);

        $this->assertTrue($invoice->hasDiscount());
        $this->assertSame(150, $invoice->rawDiscount());
        $this->assertSame(50, $invoice->rawDiscountFor(new Discount($discount)));
        $this->assertNull($invoice->rawDiscountFor(new Discount(new StripeDiscount('baz'))));
    }

    public function test_it_can_return_its_tax()
    {
        $chargebeeInvoice = new ChargeBeeInvoice();
        $chargebeeInvoice->customer = 'foo';
        $chargebeeInvoice->tax = 50;
        $chargebeeInvoice->currency = 'USD';

        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);

        $tax = $invoice->tax();

        $this->assertEquals('$0.50', $tax);

        // No tax...
        $chargebeeInvoice = new ChargeBeeInvoice();
        $chargebeeInvoice->customer = 'foo';
        $chargebeeInvoice->tax = null;
        $chargebeeInvoice->currency = 'USD';

        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);

        $tax = $invoice->tax();

        $this->assertEquals('$0.00', $tax);
    }

    public function test_it_can_determine_if_the_customer_was_exempt_from_taxes()
    {
        $chargebeeInvoice = new ChargeBeeInvoice();
        $chargebeeInvoice->customer = 'foo';
        $chargebeeInvoice->customer_tax_exempt = StripeCustomer::TAX_EXEMPT_EXEMPT;

        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);

        $this->assertTrue($invoice->isTaxExempt());
    }

    public function test_it_can_determine_if_reverse_charge_applies()
    {
        $chargebeeInvoice = new ChargeBeeInvoice();
        $chargebeeInvoice->customer = 'foo';
        $chargebeeInvoice->customer_tax_exempt = StripeCustomer::TAX_EXEMPT_REVERSE;

        $user = new User();
        $user->chargebee_id = 'foo';

        $invoice = new Invoice($user, $chargebeeInvoice);

        $this->assertTrue($invoice->reverseChargeApplies());
    }
}
