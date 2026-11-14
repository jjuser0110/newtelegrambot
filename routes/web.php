<?php

use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BotController;
use App\Http\Controllers\BotReplyController;
use App\Http\Controllers\BroadcastController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\SavedMessageController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

// Telegram webhook (public, protected by the secret token header)
Route::post('/telegram/webhook/{bot}', TelegramWebhookController::class)->name('telegram.webhook');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::get('/', [AuthController::class, 'home'])->name('home');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Superadmin only
    Route::middleware('role:superadmin')->prefix('admin')->name('admin.')->group(function () {
        Route::resource('companies', CompanyController::class)->except('show')->parameters(['companies' => 'company']);
    });

    // Only companies connect bots (they own the bot limit)
    Route::post('/bots', [BotController::class, 'store'])->middleware('role:company')->name('bots.store');

    // Companies manage their own bots; the superadmin can see and manage every company's bots.
    Route::middleware('role:company,superadmin')->group(function () {
        Route::get('/bots', [BotController::class, 'index'])->name('bots.index');
        Route::get('/bots/{bot}', [BotController::class, 'show'])->name('bots.show');
        Route::post('/bots/{bot}/webhook', [BotController::class, 'webhook'])->name('bots.webhook');
        Route::post('/bots/{bot}/toggle', [BotController::class, 'toggle'])->name('bots.toggle');
        Route::delete('/bots/{bot}', [BotController::class, 'destroy'])->name('bots.destroy');

        Route::scopeBindings()->group(function () {
            Route::resource('bots.replies', BotReplyController::class)->except(['index', 'show']);

            Route::resource('bots.saved-messages', SavedMessageController::class)
                ->only(['index', 'store', 'update', 'destroy'])
                ->parameters(['saved-messages' => 'savedMessage']);

            Route::get('/bots/{bot}/chats', [ChatController::class, 'index'])->name('chats.index');
            Route::get('/bots/{bot}/chats-latest', [ChatController::class, 'latest'])->name('chats.latest');
            Route::get('/bots/{bot}/chats/{telegramUser}', [ChatController::class, 'show'])->name('chats.show');
            Route::post('/bots/{bot}/chats/{telegramUser}', [ChatController::class, 'send'])->name('chats.send');
            Route::get('/bots/{bot}/chats/{telegramUser}/messages', [ChatController::class, 'messages'])->name('chats.messages');
            Route::post('/bots/{bot}/chats/{telegramUser}/auto-reply', [ChatController::class, 'toggleAutoReply'])->name('chats.auto-reply');

            Route::get('/bots/{bot}/broadcasts', [BroadcastController::class, 'index'])->name('broadcasts.index');
            Route::get('/bots/{bot}/broadcasts/create', [BroadcastController::class, 'create'])->name('broadcasts.create');
            Route::post('/bots/{bot}/broadcasts', [BroadcastController::class, 'store'])->name('broadcasts.store');
            Route::get('/bots/{bot}/broadcasts/{broadcast}', [BroadcastController::class, 'show'])->name('broadcasts.show');
            Route::get('/bots/{bot}/broadcasts/{broadcast}/progress', [BroadcastController::class, 'progress'])->name('broadcasts.progress');
            Route::post('/bots/{bot}/broadcasts/{broadcast}/cancel', [BroadcastController::class, 'cancel'])->name('broadcasts.cancel');
            Route::patch('/bots/{bot}/broadcasts/{broadcast}/speed', [BroadcastController::class, 'updateSpeed'])->name('broadcasts.speed');
            Route::post('/bots/{bot}/broadcasts/{broadcast}/pause', [BroadcastController::class, 'pause'])->name('broadcasts.pause');
            Route::post('/bots/{bot}/broadcasts/{broadcast}/resume', [BroadcastController::class, 'resume'])->name('broadcasts.resume');
            Route::post('/bots/{bot}/broadcasts/{broadcast}/retry', [BroadcastController::class, 'retryFailed'])->name('broadcasts.retry');
            Route::delete('/bots/{bot}/broadcasts/{broadcast}', [BroadcastController::class, 'destroy'])->name('broadcasts.destroy');
        });
    });
});
