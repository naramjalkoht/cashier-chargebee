<p align="center"><img width="355" height="62" src="/art/logo.svg" alt="Logo Laravel Cashier Chargebee"></p>

# Laravel Cashier (Chargebee)

- [Installation](#installation)
- [Configuration](#configuration)
    - [Billable Model](#billable-model)
    - [Chargebee API](#chargebee-api)
    - [Currency Configuration](#currency-configuration)
    - [Using Custom Models](#using-custom-models)
- [Customers](#customers)
    - [Retrieving Customers](#retrieving-customers)
    - [Creating Customers](#creating-customers)
- [Handling Chargebee Webhooks](#handling-chargebee-webhooks)
    - [Configuring Webhooks in Chargebee](#configuring-webhooks-in-chargebee)
    - [Route Configuration](#route-configuration)
    - [Configuring Basic Authentication](#configuring-basic-authentication)
    - [Handling Webhook Events](#handling-webhook-events)

<a name="installation"></a>
## Installation

First, install the Cashier package for Chargebee using the Composer package manager:

```shell
composer require laravel/cashier-chargebee
```

After installing the package, publish Cashier's migrations using the `vendor:publish` Artisan command:

```shell
php artisan vendor:publish --tag="cashier-migrations"
```

Then, migrate your database:

```shell
php artisan migrate
```

Cashier's migrations will add several columns to your `users` table. They will also create a new `subscriptions` table to hold all of your customer's subscriptions and a `subscription_items` table for subscriptions with multiple prices.

If you wish, you can also publish Cashier's configuration file using the `vendor:publish` Artisan command:

```shell
php artisan vendor:publish --tag="cashier-config"
```

<a name="configuration"></a>
## Configuration

<a name="billable-model"></a>
### Billable Model

Before using Cashier, add the `Billable` trait to your billable model definition. Typically, this will be the `App\Models\User` model. This trait provides various methods to allow you to perform common billing tasks, such as creating subscriptions, applying coupons, and updating payment method information:

```php
use Laravel\CashierChargebee\Billable;

class User extends Authenticatable
{
    use Billable;
}
```

Cashier assumes your billable model will be the `App\Models\User` class that ships with Laravel. If you wish to change this you may specify a different model via the `useCustomerModel` method. This method should typically be called in the `boot` method of your `AppServiceProvider` class:

```php
use App\Models\Cashier\User;
use Laravel\CashierChargebee\Cashier;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Cashier::useCustomerModel(User::class);
}
```

> [!WARNING]  
> If you're using a model other than Laravel's supplied `App\Models\User` model, you'll need to publish and alter the [Cashier migrations](#installation) provided to match your alternative model's table name.

<a name="chargebee-api"></a>
### Chargebee API

Next, you should configure your Chargebee API key and domain name in your application's `.env` file:

```ini
CHARGEBEE_SITE=your-chargebee-site
CHARGEBEE_API_KEY=your-api-key
```

- `CHARGEBEE_SITE`: This is the unique name of your Chargebee instance. It is used to construct the base URL for API requests. The base URL for API calls will look like this: 

    ```
        https://<CHARGEBEE_SITE>.chargebee.com/api/v2
    ```

    You can read more about configuring the domain name in the [Chargebee documentation](https://www.chargebee.com/docs/2.0/sites-intro.html#configuring-domain-name).

- `CHARGEBEE_API_KEY`: This is the API key that authenticates your API requests to Chargebee. You can learn how to generate Chargebee API keys [here](https://www.chargebee.com/docs/api_keys.html).

<a name="currency-configuration"></a>
### Currency Configuration

The default Cashier currency is United States Dollars (USD). You can change the default currency by setting the `CASHIER_CURRENCY` environment variable within your application's `.env` file:

```ini
CASHIER_CURRENCY=eur
```

In addition to configuring Cashier's currency, you may also specify a locale to be used when formatting money values for display on invoices. Internally, Cashier utilizes [PHP's `NumberFormatter` class](https://www.php.net/manual/en/class.numberformatter.php) to set the currency locale:

```ini
CASHIER_CURRENCY_LOCALE=nl_BE
```

> [!WARNING]  
> In order to use locales other than `en`, ensure the `ext-intl` PHP extension is installed and configured on your server.

<a name="using-custom-models"></a>
### Using Custom Models

You are free to extend the models used internally by Cashier by defining your own model and extending the corresponding Cashier model:

```php
use Laravel\CashierChargebee\Subscription as CashierSubscription;

class Subscription extends CashierSubscription
{
    // ...
}
```

After defining your model, you may instruct Cashier to use your custom model via the `Laravel\CashierChargebee\Cashier` class. Typically, you should inform Cashier about your custom models in the `boot` method of your application's `App\Providers\AppServiceProvider` class:

```php
use App\Models\Cashier\Subscription;
use App\Models\Cashier\SubscriptionItem;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Cashier::useSubscriptionModel(Subscription::class);
    Cashier::useSubscriptionItemModel(SubscriptionItem::class);
}
```

<a name="customers"></a>
## Customers

<a name="retrieving-customers"></a>
### Retrieving Customers

You can retrieve a customer by their Chargebee ID using the `Cashier::findBillable` method. This method will return an instance of the billable model:

    use Laravel\CashierChargebee\Cashier;

    $user = Cashier::findBillable($chargebeeId);

<a name="creating-customers"></a>
### Creating Customers

Occasionally, you may wish to create a Chargebee customer without beginning a subscription. You may accomplish this using the `createAsChargebeeCustomer` method:

```php
$chargebeeCustomer = $user->createAsChargebeeCustomer();
```

Once the customer has been created in Chargebee, you may begin a subscription at a later date. You may provide an optional `$options` array to pass in any additional [customer creation parameters that are supported by the Chargebee API](https://apidocs.eu.chargebee.com/docs/api/customers#create_a_customer):

```php
$chargebeeCustomer = $user->createAsChargebeeCustomer($options);
```

If you attempt to create a Chargebee customer for a model that already has a `chargebee_id` (indicating that the customer already exists in Chargebee), the method will throw a `CustomerAlreadyCreated` exception.

<a name="handling-chargebee-webhooks"></a>
## Handling Chargebee Webhooks

Any change that happens in Chargebee is captured as an event. Webhook configuration allows Chargebee to send event notifications to your system.

<a name="configuring-webhooks-in-chargebee"></a>
### Configuring Webhooks in Chargebee

To configure webhooks in Chargebee, follow the [Chargebee webhook documentation](https://www.chargebee.com/docs/2.0/webhook_settings.html). You should set up your webhook URL to point to your application's webhook endpoint, typically:

```
https://your-application.com/chargebee/webhook
```

<a name="route-configuration"></a>
### Route Configuration

Webhook route is registered automatically when Cashier is loaded. The `chargebee` prefix is derived from the `CASHIER_PATH` configuration variable. If you want to customize this prefix, you can update the `CASHIER_PATH` variable in your `.env` file:

```ini
CASHIER_PATH=custom-path
```

For example, setting `CASHIER_PATH=custom-path` would change the webhook URL to:

```
https://your-application.com/custom-path/webhook
```

<a name="configuring-basic-authentication"></a>
### Configuring Basic Authentication

Set up Basic Authentication by adding the following variables to your .env file:

```ini
CASHIER_WEBHOOK_USERNAME=your_webhook_username
CASHIER_WEBHOOK_PASSWORD=your_webhook_password
```

Cashier will automatically verify these credentials for incoming webhook requests.

<a name="handling-webhook-events"></a>
### Handling Webhook Events

Cashier emits a `WebhookReceived` event for every incoming webhook, allowing you to handle these events in your application. To handle webhook events, you can create a dedicated event listener class:

```php
namespace App\Listeners;

use Laravel\CashierChargebee\Events\WebhookReceived;

class HandleWebhookReceived
{
    /**
     * Handle the event.
     */
    public function handle(WebhookReceived $event): void
    {
        $payload = $event->payload;

        // Handle the webhook payload
    }
}
```