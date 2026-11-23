<?php

use App\Http\Controllers\ChatbotController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes — public chatbot (red-flag engine first, then FAQ)
|--------------------------------------------------------------------------
*/
Route::post('/chatbot', ChatbotController::class)
    ->middleware('throttle:chatbot')
    ->name('api.chatbot');
