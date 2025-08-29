<?php

use Illuminate\Support\Facades\Route;
use App\V5\Modules\Client\Controllers\ClientController;

Route::middleware(['auth:sanctum', 'school.context.middleware'])
    ->prefix('clients')
    ->name('v5.clients.')
    ->group(function () {
        Route::get('/', [ClientController::class, 'index'])->middleware('role.permission.middleware:client.read')->name('index');
        Route::post('/', [ClientController::class, 'store'])->middleware('role.permission.middleware:client.create')->name('store');
        Route::get('/{client}', [ClientController::class, 'show'])->middleware('role.permission.middleware:client.read')->name('show');
        Route::patch('/{client}', [ClientController::class, 'update'])->middleware('role.permission.middleware:client.update')->name('update');
        Route::delete('/{client}', [ClientController::class, 'destroy'])->middleware('role.permission.middleware:client.delete')->name('destroy');

        Route::post('/{client}/utilizadores', [ClientController::class, 'storeUtilizador'])->name('utilizadores.store');
        Route::patch('/{client}/utilizadores/{utilizador}', [ClientController::class, 'updateUtilizador'])->name('utilizadores.update');
        Route::delete('/{client}/utilizadores/{utilizador}', [ClientController::class, 'destroyUtilizador'])->name('utilizadores.destroy');

        Route::post('/{client}/sports', [ClientController::class, 'storeSport'])->name('sports.store');
        Route::patch('/{client}/sports/{sport}', [ClientController::class, 'updateSport'])->name('sports.update');
        Route::delete('/{client}/sports/{sport}', [ClientController::class, 'destroySport'])->name('sports.destroy');
    });
