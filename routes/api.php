<?php

use Illuminate\Support\Facades\Route;
use LucaCermenati\NovaSettings\Http\Controllers\FieldsController;
use LucaCermenati\NovaSettings\Http\Controllers\FileController;

Route::get('/fields', [FieldsController::class, 'index']);
Route::post('/fields', [FieldsController::class, 'store']);
Route::delete('/fields/{key}/file', [FileController::class, 'destroy']);
