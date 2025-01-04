<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FilterController;
use App\Http\Controllers\CategoryController;

Route::post('/products/filter', [FilterController::class, 'filter']);
Route::post('/category/products', [FilterController::class, 'category']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{id}/subcategories', [CategoryController::class, 'subcategories']);
