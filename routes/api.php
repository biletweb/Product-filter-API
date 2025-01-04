<?php

use App\Http\Controllers\FilterController;
use Illuminate\Support\Facades\Route;

Route::post('/products/filter', [FilterController::class, 'filter']);
Route::post('/category/products/', [FilterController::class, 'category']);
