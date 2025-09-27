<?php

use App\Livewire\WorkOrders\Board;
use App\Livewire\WorkOrders\Intake;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {

    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');


    Route::get('/radni-nalozi', Board::class)->name('workorders-board');
    Route::get('/prijem-bicikla', Intake::class)->name('workorders-create');
    Route::get('/radni-nalozi/{workorder}/uredi', Intake::class)->whereNumber('workorder')->name('workorders-edit');

});
