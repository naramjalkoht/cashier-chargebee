<?php

namespace Laravel\CashierChargebee\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Laravel\CashierChargebee\Events\WebhookReceived;
use Symfony\Component\HttpFoundation\Response;

class WebhookTest extends FeatureTestCase
{
    protected string $webhookUrl = 'chargebee/webhook';

    public function setUp(): void
    {
        parent::setUp();

        config(['cashier.webhook.username' => 'webhook_username']);
        config(['cashier.webhook.password' => 'webhook_password']);
    }

    public function test_valid_webhooks_are_authenticated_successfully(): void
    {
        $this->withValidCredentials();

        $response = $this->postJson($this->webhookUrl, ['event' => 'valid_event']);

        $response->assertStatus(Response::HTTP_OK)
            ->assertSee('Webhook Received');
    }

    public function test_invalid_credentials_result_in_http_401_response(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Basic '.base64_encode('invalid_username:invalid_password'),
        ])->postJson($this->webhookUrl, ['event' => 'invalid_event']);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED)
            ->assertSee('Unauthorized');
    }

    public function test_valid_webhook_events_trigger_appropriate_handlers(): void
    {
        $this->withValidCredentials();
        Event::fake();

        $payload = ['event' => 'valid_event'];

        $response = $this->postJson($this->webhookUrl, $payload);

        $response->assertStatus(Response::HTTP_OK);
        Event::assertDispatched(WebhookReceived::class, function ($event) use ($payload) {
            return $event->payload === $payload;
        });
    }

    public function test_handle_customer_deleted(): void
    {
        $this->withValidCredentials();

        $user = $this->createCustomer('customer_deleted', [
            'chargebee_id' => 'customer_id_123',
            'trial_ends_at' => now(),
            'pm_type' => 'visa',
            'pm_last_four' => '1234',
        ]);

        $payload = [
            'event_type' => 'customer_deleted',
            'content' => [
                'customer' => ['id' => 'customer_id_123'],
            ],
        ];

        $this->postJson($this->webhookUrl, $payload)
            ->assertStatus(200);

        $user->refresh();

        $this->assertNull($user->chargebee_id);
        $this->assertNull($user->trial_ends_at);
        $this->assertNull($user->pm_type);
        $this->assertNull($user->pm_last_four);
    }

    public function test_no_handler_found_logs_info_message(): void
    {
        $this->withValidCredentials();

        Log::swap(\Mockery::mock(\Illuminate\Log\LogManager::class)->shouldIgnoreMissing());

        Log::shouldReceive('info')
            ->once()
            ->with('WebhookReceived: No handler found for event_type: unknown_event', \Mockery::any());

        $payload = [
            'event_type' => 'unknown_event',
            'content' => [],
        ];

        $this->postJson($this->webhookUrl, $payload)
            ->assertStatus(200);
    }

    public function test_customer_deletion_logs_no_matching_user_found(): void
    {
        $this->withValidCredentials();

        Log::swap(\Mockery::mock(\Illuminate\Log\LogManager::class)->shouldIgnoreMissing());

        Log::shouldReceive('info')
            ->once()
            ->with('Customer deletion attempted, but no matching user found.', [
                'customer_id' => 'non_existent_customer_id',
            ]);

        $payload = [
            'event_type' => 'customer_deleted',
            'content' => [
                'customer' => ['id' => 'non_existent_customer_id'],
            ],
        ];

        $this->postJson($this->webhookUrl, $payload)
            ->assertStatus(200);
    }

    protected function withValidCredentials(): void
    {
        $username = config('cashier.webhook.username');
        $password = config('cashier.webhook.password');

        $this->withHeaders([
            'Authorization' => 'Basic '.base64_encode("$username:$password"),
        ]);
    }
}
