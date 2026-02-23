<?php

use App\Http\Controllers\Agent\AgentSessionController;
use App\Http\Controllers\Agent\CallController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('agent')->name('agent.')->group(function (): void {
    Route::get('campaigns', [AgentSessionController::class, 'index'])->name('campaigns');
    Route::post('session', [AgentSessionController::class, 'store'])->name('session.store');
    Route::delete('session', [AgentSessionController::class, 'destroy'])->name('session.destroy');
    Route::put('status', [AgentSessionController::class, 'updateStatus'])->name('status.update');
    Route::get('workspace', [CallController::class, 'workspace'])->name('workspace');
    Route::post('call/hangup', [CallController::class, 'hangup'])->name('call.hangup');
    Route::post('call/disposition', [CallController::class, 'disposition'])->name('call.disposition');
    Route::post('call/dial', [CallController::class, 'dial'])->name('call.dial');
});
