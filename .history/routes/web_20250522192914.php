<?php

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AppController;

// Or use Sanctum's built-in routes
Route::sanctum();
Route::get('/{any}', [AppController::class, 'index'])->where('any', '.*');

// require __DIR__.'/auth.php';




// Route::get('/', function () {
//     return ['Laravel' => app()->version()];
// });
