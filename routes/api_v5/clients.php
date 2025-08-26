<?php

use Illuminate\Support\Facades\Route;
use App\V5\Modules\Client\Controllers\ClientController;

Route::middleware('auth:sanctum')
    ->prefix('clients')
    ->name('v5.clients.')
    ->group(function () {
        Route::get('/', [ClientController::class, 'index'])->name('index');
        Route::post('/', [ClientController::class, 'store'])->name('store');
        Route::get('/{client}', [ClientController::class, 'show'])->name('show');
        Route::patch('/{client}', [ClientController::class, 'update'])->name('update');
        Route::delete('/{client}', [ClientController::class, 'destroy'])->name('destroy');

        Route::post('/{client}/utilizadores', [ClientController::class, 'storeUtilizador'])->name('utilizadores.store');
        Route::patch('/{client}/utilizadores/{utilizador}', [ClientController::class, 'updateUtilizador'])->name('utilizadores.update');
        Route::delete('/{client}/utilizadores/{utilizador}', [ClientController::class, 'destroyUtilizador'])->name('utilizadores.destroy');

        Route::post('/{client}/sports', [ClientController::class, 'storeSport'])->name('sports.store');
        Route::patch('/{client}/sports/{sport}', [ClientController::class, 'updateSport'])->name('sports.update');
        Route::delete('/{client}/sports/{sport}', [ClientController::class, 'destroySport'])->name('sports.destroy');
    });
