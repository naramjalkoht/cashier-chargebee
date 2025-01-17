<?php

use Illuminate\Support\Facades\Route;
use Laravel\CashierChargebee\Http\Middleware\AuthenticateWebhook;

Route::post('webhook', 'WebhookController@handleWebhook')->middleware(AuthenticateWebhook::class)->name('webhook');