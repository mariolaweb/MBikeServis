<?php

use App\Livewire\WorkOrders\Board;
use App\Livewire\WorkOrders\Intake;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ErpReturnController;
use App\Http\Controllers\PublicWorkOrderController;
use App\Http\Controllers\Admin\WorkOrderPrintController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/track/{token}', [PublicWorkOrderController::class, 'show'])
    ->name('workorders.track');

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


    // ERP završio “session” i vraća korisnika nazad u app
    Route::get('/intakes/{intake}/estimate/return', [ErpReturnController::class, 'handle'])
        ->name('erp.estimate.return');


    Route::get('/admin/{workOrder}/print-label', [WorkOrderPrintController::class, 'label'])
    ->name('admin.print.label');


});
