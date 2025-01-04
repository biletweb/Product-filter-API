<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::where('parent_id', null)->select('id', 'name')->get();

        if ($categories->isEmpty()) {
            return response()->json([
                'error' => 'Categories not found.',
            ]);
        }

        return response()->json([
            'categories' => $categories,
        ]);
    }

    public function subcategories($id)
    {
        $category = Category::find($id);

        if (! $category) {
            return response()->json([
                'error' => 'Category not found.',
            ]);
        }

        $categories = Category::select('id', 'name')->where('parent_id', $id)->get();
        $totalProducts = Product::where('category_id', $id)->count();
        $breadcrumbs = $this->getBreadcrumbs($category);

        return response()->json([
            'categories' => $categories,
            'breadcrumbs' => $breadcrumbs,
            'totalProducts' => $totalProducts,
        ]);
    }

    protected function getBreadcrumbs($category)
    {
        $breadcrumbs = [];

        $breadcrumbs[] = [
            'name' => $category->name,
            'id' => $category->id,
        ];

        while ($category->parent) {
            $category = $category->parent;
            $breadcrumbs[] = [
                'name' => $category->name,
                'id' => $category->id,
            ];
        }

        return array_reverse($breadcrumbs);
    }
}
