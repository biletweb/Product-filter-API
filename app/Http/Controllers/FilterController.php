<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class FilterController extends Controller
{
    public function filter(Request $request)
    {
        // Получаем идентификатор категории из запроса
        $categoryId = $request->input('category-id');
        if (!$categoryId) {
            return response()->json(['error' => 'Category ID is required.'], 422);
        }
        // Получаем фильтры, если они есть в запросе, иначе используем пустой массив
        $filters = $request->input('filters', []);
        // Получаем смещение (offset) для пагинации, по умолчанию 0
        $offset = $request->input('offset', 0);
        // Получаем лимит (limit) для пагинации, по умолчанию 10
        $limit = $request->input('limit', 10);

        // Строим основной запрос для модели Product, выбираем только необходимые поля
        $query = Product::query()
            ->select('id', 'name', 'price', 'category_id') // Указываем поля, которые хотим получить
            ->where('category_id', $categoryId); // Фильтруем продукты по идентификатору категории

        // Применяем фильтры, если они заданы
        foreach ($filters as $attribute => $valueIds) {
            // Если массив значений пуст, пропускаем этот фильтр
            if (empty($valueIds)) {
                continue;
            }
            // Для каждого фильтра (атрибута) добавляем условие на связь с таблицей attributes
            $query->whereHas('attributes', function ($query) use ($attribute, $valueIds) {
                // Фильтруем по идентификатору атрибута
                $query->where('attribute_id', $attribute);
                // Если значение фильтра - массив, ищем все значения в массиве
                if (is_array($valueIds)) {
                    $query->whereIn('value_id', $valueIds);
                } else {
                    // Если значение фильтра одно, фильтруем по этому значению
                    $query->where('value_id', $valueIds);
                }
            });
        }

        // Применяем пагинацию: пропускаем записи в соответствии с offset и ограничиваем количество записей limit
        $filteredProducts = $query
            ->skip($offset) // Пропускаем заданное количество записей (offset)
            ->take($limit) // Ограничиваем количество записей (limit)
            ->get(); // Выполняем запрос и получаем результат

        // Возвращаем отфильтрованные продукты в формате JSON
        return response()->json($filteredProducts);
    }

    public function category(Request $request)
    {
        // Получаем идентификатор категории из запроса
        $categoryId = $request->input('category-id');

        // Получаем продукты, принадлежащие указанной категории
        $filteredProducts = Product::where('category_id', $categoryId) // Фильтруем продукты по идентификатору категории
            ->select('id', 'name', 'price', 'category_id') // Выбираем только необходимые поля
            ->skip($request->input('offset', 0)) // Пропускаем записи для пагинации (offset, по умолчанию 0)
            ->take($request->input('limit', 10)) // Ограничиваем количество записей (limit, по умолчанию 10)
            ->get(); // Выполняем запрос и получаем результат

        // Получаем фильтры, применимые для данной категории (предполагается, что метод getFiltersByCategory возвращает их)
        $filters = $this->getFiltersByCategory($categoryId);

        // Возвращаем JSON с продуктами и доступными фильтрами
        return response()->json([
            'products' => $filteredProducts, // Отфильтрованные продукты
            'filters' => $filters, // Доступные фильтры для категории
        ]);
    }

    private function getFiltersByCategory($categoryId)
    {
        // Находим категорию по ID вместе с её продуктами, атрибутами и значениями
        $category = Category::with(['products.attributes.values'])->findOrFail($categoryId);

        // Формируем фильтры на основе атрибутов продуктов категории
        $filters = $category->products
            ->flatMap(fn ($product) => $product->attributes) // Собираем все атрибуты продуктов в плоский массив
            ->groupBy('id') // Группируем атрибуты по их ID
            // Преобразуем группы атрибутов в массивы фильтров
            ->map(fn ($attributes) => [
                'id' => $attributes->first()->id, // ID атрибута
                'name' => $attributes->first()->name, // Имя атрибута
                'values' => $attributes
                // Собираем все значения атрибута, которые связаны с продуктом через pivot
                    ->flatMap(fn ($attribute) => $attribute->values->filter(
                        // Проверяем, связано ли значение с продуктом (через pivot таблицу)
                        fn ($value) => $value->products->where('id', $attribute->pivot->product_id)->isNotEmpty()
                    ))
                    ->unique('id') // Убираем дублирующиеся значения по их ID
                    ->map(fn ($value) => [
                        'id' => $value->id, // ID значения
                        'value' => $value->value, // Само значение
                    ])
                    ->values(), // Преобразуем коллекцию в массив индексов
            ])
            ->filter(fn ($filter) => $filter['values']->isNotEmpty()) // Оставляем только те фильтры, у которых есть значения
            ->values(); // Сбрасываем ключи коллекции для удобства

        return $filters; // Возвращаем итоговый массив фильтров
    }
}
