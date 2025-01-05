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

    public function subcategories(Request $request, $id)
    {
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $category = Category::find($id);
        $categoryName = $category->name;

        if (! $category) {
            return response()->json([
                'error' => 'Category not found.',
            ]);
        }

        $breadcrumbs = $this->getBreadcrumbs($category);
        $categories = Category::select('id', 'name')->where('parent_id', $id)->get();
        $categoryFilters = $this->getCategoryFilters($category);
        $products = Product::where('category_id', $id)
            ->select('id', 'name', 'price')
            ->skip($offset)
            ->take($limit)
            ->get();

        return response()->json([
            'breadcrumbs' => $breadcrumbs,
            'categories' => $categories,
            'categoryName' => $categoryName,
            'categoryFilters' => $categoryFilters,
            'products' => $products,
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

    private function getCategoryFilters($category)
    {
        $filters = $category->products
            ->flatMap(fn ($product) => $product->attributes)
            ->groupBy('id')
            ->map(fn ($attributes) => [
                'id' => $attributes->first()->id,
                'name' => $attributes->first()->name,
                'values' => $attributes
                    ->flatMap(fn ($attribute) => $attribute->values->filter(
                        fn ($value) => $value->products->where('id', $attribute->pivot->product_id)->isNotEmpty()
                    ))
                    ->unique('id')
                    ->map(fn ($value) => [
                        'id' => $value->id,
                        'value' => $value->value,
                    ])
                    ->values(),
            ])
            ->filter(fn ($filter) => $filter['values']->isNotEmpty())
            ->values();

        return $filters;
    }

    public function createCategory(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $category = new Category;
        $category->name = $request->input('name');
        if ($request->has('parent_id')) {
            $category->parent_id = $request->input('parent_id');
        }
        $category->save();

        return response()->json([
            'message' => 'Category created successfully.',
            'category' => $category->only(['id', 'name', 'parent_id']),
        ]);
    }
}
