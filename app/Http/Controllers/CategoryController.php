<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::select('id', 'name')->where('parent_id', null)->get();

        return response()->json([
            'categories' => $categories
        ]);
    }

    public function subcategories($id)
    {
        $categories = Category::select('id', 'name')->where('parent_id', $id)->get();

        if ($categories->isEmpty()) {
            return response()->json([
                'error' => 'Categories not found.'
            ]);
        }

        return response()->json([
            'categories' => $categories
        ]);
    }
}
