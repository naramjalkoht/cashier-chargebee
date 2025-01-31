<?php

namespace Laravel\CashierChargebee\Concerns;

use ChargeBee\ChargeBee\Exceptions\InvalidRequestException;
use ChargeBee\ChargeBee\Models\Customer;
use ChargeBee\ChargeBee\Models\Estimate;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Laravel\CashierChargebee\Exceptions\InvalidInvoice;
use ChargeBee\ChargeBee\Models\Invoice as ChargeBeeInvoice;
use Laravel\CashierChargebee\Invoice;
use Laravel\CashierChargebee\InvoiceBuilder;
use Laravel\CashierChargebee\Payment;
use LogicException;
use Stripe\Exception\CardException as StripeCardException;
use Stripe\Exception\InvalidRequestException as StripeInvalidRequestException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait ManagesInvoices
{


    public function newInvoice()
    {
        $this->assertCustomerExists();
        return new InvoiceBuilder($this);

    }

    /**
     * Get the customer's upcoming invoice.
     *
     * @param  array  $options
     * @return \Laravel\CashierChargebee\Invoice|null
     */
    public function upcomingInvoice(array $options = [])
    {
        if (!$this->hasChargebeeId()) {
            return;
        }

        $parameters = array_merge([
            'automatic_tax' => $this->automaticTaxPayload(),
            'customerId' => $this->chargebeeId,
        ], $options);

        try {
            $stripeInvoice = Estimate::upcomingInvoicesEstimate($this->chargebeeId);

            dd($stripeInvoice);
            return new Invoice($this, $stripeInvoice, $parameters);
        } catch (InvalidRequestException $exception) {
            //
        }
    }

    /**
     * Find an invoice by ID.
     *
     * @param  string  $id
     * @return \Laravel\CashierChargebee\Invoice|null
     */
    public function findInvoice($id)
    {
        $chargebeeInvoice = null;

        try {
            $chargebeeInvoice = ChargeBeeInvoice::retrieve($id)->invoice();
        } catch (InvalidRequestException $exception) {
            //
        }

        return $chargebeeInvoice ? new Invoice($this, $chargebeeInvoice) : null;
    }

    /**
     * Find an invoice or throw a 404 or 403 error.
     *
     * @param  string  $id
     * @return \Laravel\CashierChargebee\Invoice
     *
     * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function findInvoiceOrFail($id)
    {
        try {
            $invoice = $this->findInvoice($id);
        } catch (InvalidInvoice $exception) {
            throw new AccessDeniedHttpException;
        }

        if (is_null($invoice)) {
            throw new NotFoundHttpException;
        }

        return $invoice;
    }

    /**
     * Create an invoice download Response.
     *
     * @param  string  $id
     * @param  array  $data
     * @param  string  $filename
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function downloadInvoice($id, array $data = [], $filename = null)
    {
        $invoice = $this->findInvoiceOrFail($id);

        return $filename ? $invoice->downloadAs($filename, $data) : $invoice->download($data);
    }

    /**
     * Get a collection of the customer's invoices.
     *
     * @param  bool  $includePending
     * @param  array  $parameters
     * @return \Illuminate\Support\Collection|\Laravel\Cashier\Invoice[]
     */
    public function invoices($includePending = false, $parameters = [])
    {
        if (!$this->hasChargebeeId()) {
            return new Collection();
        }

        $invoices = [];

        $parameters = array_merge(['limit' => 24], $parameters);

        $stripeInvoices = static::stripe()->invoices->all(
            ['customer' => $this->stripe_id] + $parameters
        );

        // Here we will loop through the Stripe invoices and create our own custom Invoice
        // instances that have more helper methods and are generally more convenient to
        // work with than the plain Stripe objects are. Then, we'll return the array.
        if (!is_null($stripeInvoices)) {
            foreach ($stripeInvoices->data as $invoice) {
                if ($invoice->paid || $includePending) {
                    $invoices[] = new Invoice($this, $invoice);
                }
            }
        }

        return new Collection($invoices);
    }

    /**
     * Get an array of the customer's invoices, including pending invoices.
     *
     * @param  array  $parameters
     * @return \Illuminate\Support\Collection|\Laravel\Cashier\Invoice[]
     */
    public function invoicesIncludingPending(array $parameters = [])
    {
        return $this->invoices(true, $parameters);
    }

    /**
     * Get a cursor paginator for the customer's invoices.
     *
     * @param  int|null  $perPage
     * @param  array  $parameters
     * @param  string  $cursorName
     * @param  \Illuminate\Pagination\Cursor|string|null  $cursor
     * @return \Illuminate\Contracts\Pagination\CursorPaginator
     */
    public function cursorPaginateInvoices($perPage = 24, array $parameters = [], $cursorName = 'cursor', $cursor = null)
    {
        if (!$cursor instanceof Cursor) {
            $cursor = is_string($cursor)
                ? Cursor::fromEncoded($cursor)
                : CursorPaginator::resolveCurrentCursor($cursorName, $cursor);
        }

        if (!is_null($cursor)) {
            if ($cursor->pointsToNextItems()) {
                $parameters['starting_after'] = $cursor->parameter('id');
            } else {
                $parameters['ending_before'] = $cursor->parameter('id');
            }
        }

        $invoices = $this->invoices(true, array_merge($parameters, ['limit' => $perPage + 1]));

        if (!is_null($cursor) && $cursor->pointsToPreviousItems()) {
            $invoices = $invoices->reverse();
        }

        return new CursorPaginator($invoices, $perPage, $cursor, array_merge([
            'path' => Paginator::resolveCurrentPath(),
            'cursorName' => $cursorName,
            'parameters' => ['id'],
        ]));
    }
}
