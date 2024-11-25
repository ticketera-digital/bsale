<?php

use Illuminate\Support\Facades\Route;

Route::match(['post', 'put'], '/webhooks', \ticketeradigital\bsale\Controllers\WebHookController::class);
