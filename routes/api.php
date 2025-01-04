<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\FilterController;
use Illuminate\Support\Facades\Route;

Route::post('/products/filter', [FilterController::class, 'filter']);
Route::post('/category/products', [FilterController::class, 'category']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{id}/subcategories', [CategoryController::class, 'subcategories']);
