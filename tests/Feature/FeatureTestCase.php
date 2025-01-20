<?php

namespace Laravel\CashierChargebee\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\CashierChargebee\Cashier;
use Laravel\CashierChargebee\Tests\Fixtures\User;
use Laravel\CashierChargebee\Tests\TestCase;
use Orchestra\Testbench\Concerns\WithLaravelMigrations;

abstract class FeatureTestCase extends TestCase
{
    use RefreshDatabase, WithLaravelMigrations;

    protected function setUp(): void
    {
        if (! getenv('CHARGEBEE_SITE') || ! getenv('CHARGEBEE_API_KEY')) {
            $this->markTestSkipped('Chargebee site or API key not set.');
        }

        parent::setUp();

        Cashier::configureEnvironment();
    }

    protected function createCustomer($description = 'testuser', array $options = []): User
    {
        return User::create(array_merge([
            'email' => "{$description}@cashier-chargebee.com",
            'name' => 'Test User',
            'password' => '$2y$10$kJd93qWbF8VX4EPlRxGvBOipmKz6W5Q1yTUapXaR3YUgT76Z.jU.e',
        ], $options));
    }
}
