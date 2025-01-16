<p align="center"><img width="355" height="62" src="/art/logo.svg" alt="Logo Laravel Cashier Chargebee"></p>

# Laravel Cashier (Chargebee)

- [Installation](#installation)
- [Configuration](#configuration)
    - [Billable Model](#billable-model)
    - [Chargebee API](#chargebee-api)
    - [Currency Configuration](#currency-configuration)
    - [Using Custom Models](#using-custom-models)

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