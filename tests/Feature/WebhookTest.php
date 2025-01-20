<?php

namespace Laravel\CashierChargebee\Tests\Feature;

use Illuminate\Support\Facades\Event;
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

    public function test_valid_webhooks_are_authenticated_successfully()
    {
        $this->withValidCredentials();

        $response = $this->postJson($this->webhookUrl, ['event' => 'valid_event']);

        $response->assertStatus(Response::HTTP_OK)
            ->assertSee('Webhook Received');
    }

    public function test_invalid_credentials_result_in_http_401_response()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Basic '.base64_encode('invalid_username:invalid_password'),
        ])->postJson($this->webhookUrl, ['event' => 'invalid_event']);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED)
            ->assertSee('Unauthorized');
    }

    public function test_valid_webhook_events_trigger_appropriate_handlers()
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

    protected function withValidCredentials()
    {
        $username = config('cashier.webhook.username');
        $password = config('cashier.webhook.password');

        $this->withHeaders([
            'Authorization' => 'Basic '.base64_encode("$username:$password"),
        ]);
    }
}
