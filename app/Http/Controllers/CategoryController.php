<?php

namespace App\Http\Controllers;

use App\Models\Category;

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

        if (!$category) {
            return response()->json([
                'error' => 'Category not found.',
            ]);
        }

        $categories = Category::select('id', 'name')->where('parent_id', $id)->get();
        $breadcrumbs = $this->getBreadcrumbs($category);

        return response()->json([
            'categories' => $categories,
            'breadcrumbs' => $breadcrumbs,
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
