<?php

use Illuminate\Support\Facades\Route;
use ticketeradigital\bsale\Controllers\WebHookController;

Route::match(['post', 'put'], '/webhooks', WebHookController::class);
