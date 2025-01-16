<?php

namespace Laravel\CashierChargebee;

use ChargeBee\ChargeBee\Models\Customer;
use ChargeBee\ChargeBee\Environment;
use Illuminate\Database\Eloquent\SoftDeletes;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\IntlMoneyFormatter;
use Money\Money;
use NumberFormatter;

class Cashier
{
    /**
     * The Cashier for Chargebee library version.
     *
     * @var string
     */
    const VERSION = '0.1.0';

    /**
     * The Chargebee API version.
     *
     * @var string
     */
    const CHARGEBEE_API_VERSION = '2024-12-19';

    /**
     * The custom currency formatter.
     *
     * @var callable
     */
    protected static $formatCurrencyUsing;

    /**
     * Indicates if Cashier routes will be registered.
     *
     * @var bool
     */
    public static $registersRoutes = true;

    /**
     * Indicates if Cashier will mark past due subscriptions as inactive.
     *
     * @var bool
     */
    public static $deactivatePastDue = true;

    /**
     * Indicates if Cashier will mark incomplete subscriptions as inactive.
     *
     * @var bool
     */
    public static $deactivateIncomplete = true;

    /**
     * The default customer model class name.
     *
     * @var string
     */
    public static $customerModel = 'App\\Models\\User';

    /**
     * The subscription model class name.
     *
     * @var string
     */
    public static $subscriptionModel = Subscription::class;

    /**
     * The subscription item model class name.
     *
     * @var string
     */
    public static $subscriptionItemModel = SubscriptionItem::class;

    /**
     * Get the customer instance by its Chargebee ID.
     *
     * @param  \Chargebee\ChargeBee\Models\Customer|string|null  $chargebeeId
     * @return \Laravel\CashierChargebee\Billable|null
     */
    public static function findBillable($chargebeeId)
    {
        $chargebeeId = $chargebeeId instanceof Customer ? $chargebeeId->id : $chargebeeId;

        $model = static::$customerModel;

        $builder = in_array(SoftDeletes::class, class_uses_recursive($model))
            ? $model::withTrashed()
            : new $model;

        return $chargebeeId ? $builder->where('chargebee_id', $chargebeeId)->first() : null;
    }

    /**
     * Configure the Chargebee environment with the site and API key.
     *
     * @return void
     */
    public static function configureEnvironment()
    {
        $site = config('cashier.site');
        $apiKey = config('cashier.api_key');

        Environment::configure($site, $apiKey);
    }

    /**
     * Set the custom currency formatter.
     *
     * @param  callable  $callback
     * @return void
     */
    public static function formatCurrencyUsing(callable $callback)
    {
        static::$formatCurrencyUsing = $callback;
    }

    /**
     * Format the given amount into a displayable currency.
     *
     * @param  int  $amount
     * @param  string|null  $currency
     * @param  string|null  $locale
     * @param  array  $options
     * @return string
     */
    public static function formatAmount($amount, $currency = null, $locale = null, array $options = [])
    {
        if (static::$formatCurrencyUsing) {
            return call_user_func(static::$formatCurrencyUsing, $amount, $currency, $locale, $options);
        }

        $money = new Money($amount, new Currency(strtoupper($currency ?? config('cashier.currency'))));

        $locale = $locale ?? config('cashier.currency_locale');

        $numberFormatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        if (isset($options['min_fraction_digits'])) {
            $numberFormatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $options['min_fraction_digits']);
        }

        $moneyFormatter = new IntlMoneyFormatter($numberFormatter, new ISOCurrencies());

        return $moneyFormatter->format($money);
    }

    /**
     * Configure Cashier to not register its routes.
     *
     * @return static
     */
    public static function ignoreRoutes()
    {
        static::$registersRoutes = false;

        return new static;
    }

    /**
     * Configure Cashier to maintain past due subscriptions as active.
     *
     * @return static
     */
    public static function keepPastDueSubscriptionsActive()
    {
        static::$deactivatePastDue = false;

        return new static;
    }

    /**
     * Configure Cashier to maintain incomplete subscriptions as active.
     *
     * @return static
     */
    public static function keepIncompleteSubscriptionsActive()
    {
        static::$deactivateIncomplete = false;

        return new static;
    }

    /**
     * Set the customer model class name.
     *
     * @param  string  $customerModel
     * @return void
     */
    public static function useCustomerModel($customerModel)
    {
        static::$customerModel = $customerModel;
    }

    /**
     * Set the subscription model class name.
     *
     * @param  string  $subscriptionModel
     * @return void
     */
    public static function useSubscriptionModel($subscriptionModel)
    {
        static::$subscriptionModel = $subscriptionModel;
    }

    /**
     * Set the subscription item model class name.
     *
     * @param  string  $subscriptionItemModel
     * @return void
     */
    public static function useSubscriptionItemModel($subscriptionItemModel)
    {
        static::$subscriptionItemModel = $subscriptionItemModel;
    }
}
