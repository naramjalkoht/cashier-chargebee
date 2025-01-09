<p align="center"><img width="355" height="62" src="/art/logo.svg" alt="Logo Laravel Cashier Chargebee"></p>

# Laravel Cashier (Chargebee)

- [Installation](#installation)

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
