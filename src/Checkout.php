<?php

namespace Laravel\CashierChargebee;

use ChargeBee\ChargeBee\Models\HostedPage;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Facades\Redirect;
use JsonSerializable;

class Checkout implements Arrayable, Jsonable, JsonSerializable, Responsable
{
    /**
     * The Chargebee model instance.
     *
     * @var \Illuminate\Database\Eloquent\Model|null
     */
    protected $owner;

    /**
     * The Chargebee checkout session instance.
     *
     * @var Session
     */
    protected $session;

    /**
     * Create a new checkout session instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model|null  $owner
     * @param  Session  $session
     * @return void
     */
    public function __construct($owner, Session $session)
    {
        $this->owner = $owner;
        $this->session = $session;
    }

    /**
     * Begin a new guest checkout session.
     *
     * @return \Laravel\CashierChargebee\CheckoutBuilder
     */
    public static function guest()
    {
        return new CheckoutBuilder();
    }

    /**
     * Begin a new customer checkout session.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @param  object|null  $parentInstance
     * @return \Laravel\CashierChargebee\CheckoutBuilder
     */
    public static function customer($owner, $parentInstance = null)
    {
        return new CheckoutBuilder($owner, $parentInstance);
    }

    /**
     * Begin a new checkout session.
     *
     * @param  \Illuminate\Database\Eloquent\Model|null  $owner
     * @param  array  $sessionOptions
     * @param  array  $customerOptions
     * @return \Laravel\CashierChargebee\Checkout
     */
    public static function create($owner, array $sessionOptions = [], array $customerOptions = [])
    {
        $data = array_merge([
            'mode' => Session::MODE_PAYMENT,
        ], $sessionOptions);

        if ($owner) {
            $data['customer']["id"] = $owner->createOrGetChargebeeCustomer($customerOptions)->id;
        }

        $data['redirectUrl'] = $sessionOptions['success_url'] ?? route('home') . '?checkout=success';
        $data['cancelUrl'] = $sessionOptions['cancel_url'] ?? route('home') . '?checkout=cancelled';
        $data['currencyCode'] = $sessionOptions['currency_code'] ?? $owner ? $owner->preferredCurrency() : '';

        if ($data['mode'] == Session::MODE_SUBSCRIPTION) {
            $result = HostedPage::checkoutNewForItems($data);
        } else if ($data['mode'] == Session::MODE_SETUP) {
            $result = HostedPage::managePaymentSources($data);
        } else {
            $result = HostedPage::checkoutOneTimeForItems($data);

        }

        return new static($owner, new Session(
            $result->hostedPage()->getValues(),
            $data['mode']
        ));
    }

    /**
     * Redirect to the checkout session.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirect()
    {
        return Redirect::to($this->session->url, 303);
    }

    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        return $this->redirect();
    }

    /**
     * Get the Checkout Session as a Chargebee Checkout Session object.
     *
     * @return Session
     */
    public function asChargebeeCheckoutSession()
    {
        return $this->session;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->asChargebeeCheckoutSession()->getValues();
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Dynamically get values from the Chargebee object.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->session->{$key};
    }
}
