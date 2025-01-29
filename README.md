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
    - [Updating Customers](#updating-customers)
    - [Syncing Customers](#syncing-customers)
    - [Tax exemption](#tax-exemption)
    - [Billing Portal](#billing-portal)
    - [Balances](#balances)
- [Handling Chargebee Webhooks](#handling-chargebee-webhooks)
    - [Configuring Webhooks in Chargebee](#configuring-webhooks-in-chargebee)
    - [Route Configuration](#route-configuration)
    - [Configuring Basic Authentication](#configuring-basic-authentication)
    - [Handling Webhook Events](#handling-webhook-events)
- [Manage Payment Methods](#manage-payment-methods)
    - [Create SetupIntent](#payment-methods-create-setupintent)
    - [Find SetupIntent](#payment-methods-find-setupintent)
    - [Retrieving Payment Methods](#payment-methods-list)
    - [Create Payment Method](#payment-methods-create)
    - [Delete Payment Method](#payment-methods-delete)

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

```php
use Laravel\CashierChargebee\Cashier;

$user = Cashier::findBillable($chargebeeId);
```

You may use the `asChargebeeCustomer` method if you want to return the Chargebee customer object for a billable model:

```php
$chargebeeCustomer = $user->asChargebeeCustomer();
```

If `chargebee_id` on your model is missing or invalid, the method will throw a `CustomerNotFound` exception.

<a name="creating-customers"></a>
### Creating Customers

Occasionally, you may wish to create a Chargebee customer without beginning a subscription. You may accomplish this using the `createAsChargebeeCustomer` method:

```php
$chargebeeCustomer = $user->createAsChargebeeCustomer();
```

Once the customer has been created in Chargebee, you may begin a subscription at a later date.

This method uses helper methods like `chargebeeFirstName`, `chargebeeLastName`, `chargebeeEmail`, `chargebeePhone`, `chargebeeBillingAddress`, `chargebeeLocale`, and `chargebeeMetaData` to populate default values for the customer. You can override these methods in your model to customize which fields are used. For example:

```php
/**
 * Get the default first name.
 */
public function chargebeeFirstName(): string|null
{
    return $this->custom_first_name;
}
```

You may provide an optional `$options` array to pass in any additional [customer creation parameters that are supported by the Chargebee API](https://apidocs.eu.chargebee.com/docs/api/customers#create_a_customer):

```php
$chargebeeCustomer = $user->createAsChargebeeCustomer($options);
```

If you attempt to create a Chargebee customer for a model that already has a `chargebee_id` (indicating that the customer already exists in Chargebee), the method will throw a `CustomerAlreadyCreated` exception.

You can also use the `createOrGetChargebeeCustomer` method to retrieve an existing Chargebee customer or create a new one if it does not exist:

```php
$chargebeeCustomer = $user->createOrGetChargebeeCustomer($options);
```

<a name="updating-customers"></a>
### Updating Customers

Occasionally, you may wish to update the Chargebee customer directly with additional information. You may accomplish this using the `updateChargebeeCustomer` method. This method accepts an array of [customer](https://apidocs.chargebee.com/docs/api/customers?lang=php#update_a_customer) and [billing information](https://apidocs.chargebee.com/docs/api/customers#update_billing_info_for_a_customer) update parameters supported by the Chargebee API:

```php
$options = [
    'firstName' => 'John',
    'lastName' => 'Doe',
    'phone' => '123456789',
    'billingAddress' => [
        'firstName' => 'John',
        'lastName' => 'Doe',
        'line1' => '221B Baker Street',
        'city' => 'London',
        'state' => 'England',
        'zip' => 'NW1 6XE',
        'country' => 'GB',
    ],
];

$customer = $user->updateChargebeeCustomer($options);
```

> [!NOTE]
> The `billingAddress` key is required for the `updateChargebeeCustomer` method and it must contain a non-empty array of address details (e.g., `line1`, `city`, `zip`, etc.). You can provide it directly in the `options` input array or override the `chargebeeBillingAddress()` method in your model to provide default values. For example:

```php
/**
 * Provide a default billing address.
 */
public function chargebeeBillingAddress(): array
{
    return [
        'line1' => $this->address_line_1,
        'city' => $this->address_city,
        'state' => $this->address_state,
        'zip' => $this->address_zip,
        'country' => $this->address_country,
    ];
}
```

If `chargebee_id` on your model is missing or invalid, the method will throw a `CustomerNotFound` exception.

You can also use the `updateOrCreateChargebeeCustomer` method to update an existing Chargebee customer or create a new one if it does not exist:

```php
$chargebeeCustomer = $user->updateOrCreateChargebeeCustomer($options);
```

<a name="syncing-customers"></a>
### Syncing Customers

To sync the customer's information to Chargebee, you can use the `syncChargebeeCustomerDetails` method. This method will update the Chargebee customer with the latest information from your model:

```php
$customer = $user->syncChargebeeCustomerDetails();
```

This method uses helper methods like `chargebeeFirstName`, `chargebeeLastName`, `chargebeeEmail`, `chargebeePhone`, `chargebeeBillingAddress`, `chargebeeLocale`, and `chargebeeMetaData` to determine the values to sync. You can override these methods in your model to customize the sync process.

If you want to sync the customer's information or create a new Chargebee customer if one does not exist, you can use the `syncOrCreateChargebeeCustomer` method:

```php
$customer = $user->syncOrCreateChargebeeCustomer($options);
```

<a name="tax-exemption"></a>
### Tax Exemption

Cashier offers the `isNotTaxExempt` and `isTaxExempt` methods to determine if the customer is tax exempt. These methods will call the Chargebee API to determine a customer's taxability status:

```php
use App\Models\User;

$user = User::find(1);

$user->isTaxExempt();
$user->isNotTaxExempt();
```

<a name="billing-portal"></a>
### Billing Portal

Chargebee offers [an easy way to set up a billing portal](https://www.chargebee.com/docs/2.0/self-serve-portal.html) so that your customer can manage their subscription, payment methods, and view their billing history. You can redirect your users to the billing portal by invoking the `redirectToBillingPortal` method on the billable model from a controller or route:

```php
use Illuminate\Http\Request;

Route::get('/billing-portal', function (Request $request) {
    return $request->user()->redirectToBillingPortal();
});
```

By default, when the user is finished managing their subscription, they will return to the `home` route of your application upon logout from the portal UI. You may provide a custom URL that the user should return to by passing the URL as an argument to the `redirectToBillingPortal` method:

```php
use Illuminate\Http\Request;

Route::get('/billing-portal', function (Request $request) {
    return $request->user()->redirectToBillingPortal(route('billing'));
});
```

If you would like to generate the URL to the billing portal without generating an HTTP redirect response, you may invoke the `billingPortalUrl` method:

```php
$url = $request->user()->billingPortalUrl(route('billing'));
```

<a name="balances"></a>
### Balances

Chargebee allows you to credit or debit a customer's "balance". Later, this balance will be credited or debited on new invoices. To check the customer's total balance in a formatted string representation of their currency, you may use the `balance` method:

```php
$balance = $user->balance();
```

If you need the raw, unformatted total balance (e.g., for calculations), you can use the `rawBalance` method:

```php
$rawBalance = $user->rawBalance();
```

To credit a customer's balance, you may use the `creditBalance method`. You can provide the amount to be credited and an optional description:

```php
$user->creditBalance(500, 'Add promotional credits.');
```

Similarly, to debit a customer's balance, use the `debitBalance` method. You can specify the amount to be debited and an optional description:

```php
$user->debitBalance(300, 'Deduct promotional credits.');
```

Both `creditBalance` and `debitBalance` methods accept an optional `options` array. This array allows you to include additional parameters supported by Chargebee's [Promotional Credits API](https://apidocs.eu.chargebee.com/docs/api/promotional_credits). For example, you can describe why promotional credits were provided in a `reference` parameter:

```php
$user->creditBalance(500, 'Add promotional credits.', [
    'reference' => 'referral_bonus_2023',
]);
```

The `applyBalance` method will automatically determine whether to credit or debit the customer's balance based on the sign of the amount:

```php
$user->applyBalance(500, 'Add credits.'); // Credits 500
$user->applyBalance(-300, 'Deduct credits.'); // Debits 300
```

Like the `creditBalance` and `debitBalance` methods, `applyBalance` also accepts an `options` array for additional API parameters:

```php
$user->applyBalance(1000, 'Promotional credits applied.', [
    'reference' => 'promo123',
]);
```

> [!NOTE]
> The `amount` parameter in `creditBalance`, `debitBalance`, and `applyBalance` is specified in the smallest currency unit (e.g., cents for USD or euro cents for EUR). For example, passing `500` with a currency of EUR will represent 5 euros.

To retrieve the transaction history for a customer's balance, use the balanceTransactions method:

```php
$transactions = $user->balanceTransactions();
```

This method allows you to specify a `limit` (default is 10) and an `options` array for additional filtering. For example, to retrieve 20 transactions filtered by a specific type:

```php
$transactions = $user->balanceTransactions(20, [
    'type[is]' => 'increment',
]);
```

The returned transactions are a collection of promotional credit objects, which can be iterated over or manipulated as needed.

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

<a name="manage-payment-methods"></a>
## Manage Payment Methods

**Please remember to enable 3DS in Chargebee settings for your account to be able to use PaymentIntents**

<a name="payment-methods-create-setupintent"></a>
### Create SetupIntent

You can create SetupIntent (PaymentIntent with amount hardcoded to 0). This will create new payment source.

**Available options**
  * currency_code 
    * required
    * default set to Cashier config option
  * gateway_account_id
    * optional, string, max chars=50
    * The gateway account used for performing the 3DS flow.
  * reference_id 
    * optional, string, max chars=200
    * Reference for payment method at gateway. Only applicable when the PaymentIntent is created for cards stored in the gateway.
  * payment_method_type
    * optional, enumerated string, 
    * default=card
    * possible values
      * card
      * ideal
      * sofort
      * bancontact
  * success_url
    * optional, string, max chars=250
    * The URL the customer will be directed to once 3DS verification is successful.
  * failure_url
    * optional, string, max chars=250
    * The URL the customer will be directed to when 3DS verification fails.

```php
$currency = 'EUR';
$user = $this->createCustomer();
$user->createAsChargebeeCustomer();

$paymentIntent = $user->createSetupIntent(['currency_code' => $currencyCode]);
```

<a name="payment-methods-find-setupintent"></a>
### Find SetupIntent

Retrieves the PaymentIntent resource.

```php
$user = $this->createCustomer();
$user->createAsChargebeeCustomer();

$paymentIntent = $user->findSetupIntent($id);
```

You can find more information about PaymentIntent API [here](https://apidocs.eu.chargebee.com/docs/api/payment_intents#create_a_payment_intent?target=_blank)

<a name="payment-methods-list"></a>
### Get Payment Methods

Cashier allows you to pull list of all payment methods available to customer. This list is a Laravel Collection of PaymentSources for selected customer.

**When customer is not defined, empty Laravel Collection is returned instead.**

You may provide an optional `$type` string and `$parameters` array to pass in any additional [filters that are supported by Chargebee API](https://apidocs.chargebee.com/docs/api/payment_sources?lang=curl#list_payment_sources)

#### Examples:

##### All available payment methods
```php
$user = $this->createCustomer();
$user->createAsChargebeeCustomer();

$paymentMethods = $user->paymentMethods();
```

##### Only card available payment methods
```php
$user = $this->createCustomer();
$user->createAsChargebeeCustomer();

$paymentMethods = $user->paymentMethods('card');
```
<a name="payment-methods-create"></a>
### Create Payment Method

If you want to add new payment method to customer, you can invoke `addPaymentMethod` method. This method allows you to pass Chargebee PaymentSource instance.

```php
$user = $this->createCustomer();
$user->createAsChargebeeCustomer();

$paymentMethod = $user->addPaymentMethod(`$chargeBeePaymentSource`)
```

If `chargebee_id` on your model is missing or invalid, the method will throw a `CustomerNotFound` exception.

When payload sent to Chargebee API is invalid `PaymentException` will be thrown.

<a name="payment-methods-delete"></a>
### Delete Payment Methods

If you want to delete one of the customer's payment methods, you should use `deletePaymentMethod`. It takes `$id` string param of the payment method.

```php
$user = $this->createCustomer();
$user->createAsChargebeeCustomer();

$paymentMethod = $user->deletePaymentMethod(`$chargeBeePaymentSource`)
```

If `chargebee_id` on your model is missing or invalid, the method will throw a `CustomerNotFound` exception.

When payload sent to Chargebee API is invalid `InvalidRequestException` will be thrown.


