<?php

use App\Http\Controllers\Api\AntrianController;
use Illuminate\Support\Facades\Route;

Route::get('/antrian', [AntrianController::class, 'index']);
