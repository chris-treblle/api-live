<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Users\LoginController;
use App\Http\Controllers\Api\V1\Users\RegisterController;
use Illuminate\Support\Facades\Route;

Route::post('login', LoginController::class)->name('login');
Route::post('register', RegisterController::class)->name('register');
