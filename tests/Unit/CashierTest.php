<?php

namespace Laravel\CashierChargebee\Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\CashierChargebee\Cashier;
use Laravel\CashierChargebee\Tests\Fixtures\User;
use Laravel\CashierChargebee\Tests\TestCase;

class CashierTest extends TestCase
{
    public function test_it_can_format_an_amount() : void
    {
        $this->assertSame('$10.00', Cashier::formatAmount(1000));
    }

    public function test_it_can_format_an_amount_without_digits() : void
    {
        $this->assertSame('$10', Cashier::formatAmount(1000, null, null, ['min_fraction_digits' => 0]));
    }

    public function test_it_can_format_an_amount_with_locale_and_currency() : void
    {
        $formatted = Cashier::formatAmount(1000, 'EUR', 'fr_FR');
        $this->assertStringContainsString('10,00', $formatted);
        $this->assertStringContainsString('€', $formatted);
    }
}