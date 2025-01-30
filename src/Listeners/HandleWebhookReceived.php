<?php

namespace Laravel\CashierChargebee\Listeners;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\CashierChargebee\Cashier;
use Laravel\CashierChargebee\Events\WebhookReceived;

class HandleWebhookReceived
{
    /**
     * Handle the event.
     */
    public function handle(WebhookReceived $event): void
    {
        $eventType = $event->payload['event_type'] ?? null;

        if (! $eventType) {
            Log::warning('WebhookReceived: Missing event_type in payload.', $event->payload);

            return;
        }

        $handlerMethod = $this->getHandlerMethod($eventType);

        if (method_exists($this, $handlerMethod)) {
            $this->{$handlerMethod}($event->payload);
        } else {
            Log::info("WebhookReceived: No handler found for event_type: {$eventType}", $event->payload);
        }
    }

    /**
     * Get the handler method name for a given event type.
     */
    protected function getHandlerMethod(string $eventType): string
    {
        return 'handle'.Str::studly(str_replace('_', '', $eventType));
    }

    /**
     * Handle the customer_deleted event.
     */
    protected function handleCustomerDeleted(array $payload): void
    {
        if ($user = Cashier::findBillable($payload['content']['customer']['id'])) {
            $user->forceFill([
                'chargebee_id' => null,
                'trial_ends_at' => null,
                'pm_type' => null,
                'pm_last_four' => null,
            ])->save();

            Log::info('Customer deleted successfully.', [
                'customer_id' => $payload['content']['customer']['id'],
                'user_id' => $user->id,
            ]);
        } else {
            Log::info('Customer deletion attempted, but no matching user found.', [
                'customer_id' => $payload['content']['customer']['id'],
            ]);
        }
    }
}
