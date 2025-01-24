<?php

namespace Laravel\CashierChargebee;

use ChargeBee\ChargeBee\Models\HostedPage;


class Session extends HostedPage
{
    const MODE_PAYMENT = 'payment';
    const MODE_SETUP = 'setup';
    const MODE_SUBSCRIPTION = 'subscription';
}
