<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PickController;
use App\Http\Controllers\SeasonController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WeekController;
use Illuminate\Support\Facades\Route;

// Everything is behind a login. Guests only see login and register.
Route::middleware('auth')->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::get('seasons/{season:year}', [SeasonController::class, 'show'])->name('seasons.show');
    Route::get('seasons/{season:year}/weeks/{week:number}', [WeekController::class, 'show'])->scopeBindings()->name('weeks.show');
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');

    Route::middleware('active')->group(function () {
        Route::get('picks', [PickController::class, 'show'])->name('picks.show');
        Route::put('picks', [PickController::class, 'update'])->middleware('throttle:20,1')->name('picks.update');
    });

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', Admin\AdminHomeController::class)->name('home');

        Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
        Route::post('users', [Admin\UserController::class, 'store'])->name('users.store');
        // POST (not PATCH) so photo uploads can be sent as multipart form data.
        Route::post('users/{user}', [Admin\UserController::class, 'update'])->name('users.update');
        Route::put('users/{user}/password', [Admin\UserController::class, 'password'])->name('users.password');
        Route::delete('users/{user}', [Admin\UserController::class, 'destroy'])->name('users.destroy');

        Route::get('seasons', [Admin\SeasonController::class, 'index'])->name('seasons.index');
        Route::post('seasons', [Admin\SeasonController::class, 'store'])->name('seasons.store');
        Route::post('teams/sync', [Admin\SeasonController::class, 'syncTeams'])->name('teams.sync');
        Route::get('seasons/{season}', [Admin\SeasonController::class, 'show'])->name('seasons.show');
        Route::put('seasons/{season}/points', [Admin\SeasonController::class, 'update'])->name('seasons.points');

        Route::get('weeks/{week}', [Admin\WeekController::class, 'show'])->name('weeks.show');
        Route::post('weeks/{week}/sync', [Admin\WeekController::class, 'sync'])->name('weeks.sync');
        Route::post('weeks/{week}/open', [Admin\WeekController::class, 'open'])->name('weeks.open');
        Route::post('weeks/{week}/close', [Admin\WeekController::class, 'close'])->name('weeks.close');
        Route::post('weeks/{week}/reopen', [Admin\WeekController::class, 'reopen'])->name('weeks.reopen');

        Route::post('weeks/{week}/games', [Admin\GameController::class, 'store'])->name('games.store');
        Route::put('games/{game}', [Admin\GameController::class, 'update'])->name('games.update');
        Route::delete('games/{game}', [Admin\GameController::class, 'destroy'])->name('games.destroy');
        Route::post('games/{game}/tiebreaker', [Admin\GameController::class, 'tiebreaker'])->name('games.tiebreaker');

        Route::post('weeks/{week}/entries', [Admin\EntryController::class, 'store'])->name('entries.store');
        Route::delete('entries/{entry}', [Admin\EntryController::class, 'destroy'])->name('entries.destroy');
        Route::put('entries/{entry}/picks', [Admin\EntryController::class, 'picks'])->name('entries.picks');
    });
});

require __DIR__.'/settings.php';
