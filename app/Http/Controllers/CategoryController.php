<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

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

    public function subcategories(Request $request, int $id)
    {
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);

        $category = Category::find($id);

        if (! $category) {
            return response()->json([
                'error' => 'Category not found.',
            ]);
        }

        $categories = Category::select('id', 'name')->where('parent_id', $id)->get();
        $totalProducts = Product::where('category_id', $id)->count();
        $products = Product::where('category_id', $id)
            ->select('id', 'name', 'price')
            ->skip($offset)
            ->take($limit)
            ->get();
        $breadcrumbs = $this->getBreadcrumbs($category);

        return response()->json([
            'breadcrumbs' => $breadcrumbs,
            'categories' => $categories,
            'products' => $products,
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
