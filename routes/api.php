<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\FilterController;
use Illuminate\Support\Facades\Route;

Route::get('/products/filter', [FilterController::class, 'filter']);
Route::get('/category/products', [FilterController::class, 'category']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{id}/subcategories', [CategoryController::class, 'subcategories']);
