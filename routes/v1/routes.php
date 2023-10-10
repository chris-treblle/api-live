<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Treblle\ApiResponses\Responses\MessageResponse;
use Carbon\Carbon;

Route::prefix('auth')->as('auth:')->group(base_path('routes/v1/auth.php'));

Route::prefix('logic')->as('logic:')->middleware('auth:sanctum')->group(base_path('routes/v1/logic.php'));

Route::get('ping', fn() => new MessageResponse(Carbon::now()->format('Y/m/d H:i:s')))->name('ping');
