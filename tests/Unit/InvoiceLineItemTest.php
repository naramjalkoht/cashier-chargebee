<?php

declare(strict_types=1);

namespace Laravel\CashierChargebee\Tests\Unit;

use Laravel\CashierChargebee\Invoice;
use Laravel\CashierChargebee\InvoiceLineItem;
use PHPUnit\Framework\TestCase;
use ChargeBee\ChargeBee\Models\Invoice as ChargeBeeInvoice;
use Laravel\CashierChargebee\Tests\Fixtures\User;
use ChargeBee\ChargeBee\Models\InvoiceLineItem as ChargeBeeInvoiceLineItem;

class InvoiceLineItemTest extends TestCase
{
    public function test_we_can_calculate_the_inclusive_tax_percentage()
    {

        $chargebeeInvoice = new ChargeBeeInvoice([
            'customerId' => 'foo',
            'date' => 1560541724,
            'priceType' => 'tax_inclusive'
        ]);


        $user = new User();
        $user->chargebee_id = 'foo';


        $invoice = new Invoice($user, $chargebeeInvoice);

        $chargebeeInvoiceLineItem = new ChargeBeeInvoiceLineItem([
            'taxRate' => 20.0
        ]);


        $item = new InvoiceLineItem($invoice, $chargebeeInvoiceLineItem);
        $result = $item->inclusiveTaxPercentage();
        $this->assertSame(20.0, $result);
    }

    public function test_we_can_calculate_the_exclusive_tax_percentage()
    {

        $chargebeeInvoice = new ChargeBeeInvoice([
            'customerId' => 'foo',
            'date' => 1560541724,
            'priceType' => 'tax_exclusive'
        ]);


        $user = new User();
        $user->chargebee_id = 'foo';


        $invoice = new Invoice($user, $chargebeeInvoice);

        $chargebeeInvoiceLineItem = new ChargeBeeInvoiceLineItem([
            'taxRate' => 20.0
        ]);


        $item = new InvoiceLineItem($invoice, $chargebeeInvoiceLineItem);
        $result = $item->exclusiveTaxPercentage();
        $this->assertSame(20.0, $result);
    }
}
