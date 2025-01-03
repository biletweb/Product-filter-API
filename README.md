# Product filter

To configure the project, specify the database communication settings in the `.env` configuration file.

## Project setup

```sh
composer install
```

### Creating all models and migrations

```sh
php artisan make:model Category -m

class Category extends Model
{
    public function attributes()
    {
        return $this->belongsToMany(Attribute::class, 'category_attributes');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}

Schema::create('categories', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->foreignId('parent_id')->nullable()->constrained('categories');
    $table->timestamps();
});
```
```sh
php artisan make:model Product -m

class Product extends Model
{
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function attributes()
    {
        return $this->belongsToMany(Attribute::class, 'product_attributes')->withPivot('value_id');
    }
}

Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
    $table->decimal('price', 10, 2);
    $table->timestamps();
});
```
```sh
php artisan make:model Attribute -m

class Attribute extends Model
{
    public function values()
    {
        return $this->hasMany(AttributeValue::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_attributes')->withPivot('value_id');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_attributes');
    }
}

Schema::create('attributes', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->timestamps();
});
```
```sh
php artisan make:model AttributeValue -m

class AttributeValue extends Model
{
    public function attribute()
    {
        return $this->belongsTo(Attribute::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_attributes', 'value_id', 'product_id')->withPivot('attribute_id');
    }
}

Schema::create('attribute_values', function (Blueprint $table) {
    $table->id();
    $table->foreignId('attribute_id')->constrained('attributes')->onDelete('cascade');
    $table->string('value');
    $table->timestamps();
});
```
```sh
php artisan make:migration create_product_attributes_table

Schema::create('product_attributes', function (Blueprint $table) {  
    $table->foreignId('product_id')->constrained('products')->onDelete('cascade');  
    $table->foreignId('attribute_id')->constrained('attributes')->onDelete('cascade');  
    $table->foreignId('value_id')->constrained('attribute_values')->onDelete('cascade'); 
    $table->primary(['product_id', 'attribute_id']);  
});
```
```sh
php artisan make:migration create_category_attributes_table

Schema::create('category_attributes', function (Blueprint $table) {
    $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
    $table->foreignId('attribute_id')->constrained('attributes')->onDelete('cascade');
    $table->primary(['category_id', 'attribute_id']);
});
```

### Creating a controller and methods

```sh
php artisan make:controller FilterController

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
```

### Routes

Specify in the `routes/api.php` file

```sh
Route::post('/products/filter', [FilterController::class, 'filter']);
Route::post('/category/products/', [FilterController::class, 'category']);
```

### Running migrations

```sh
php artisan migrate
```

### Start your local development server

```sh
php artisan serve
```

### Testing in Postman

Sorting products based on filters
```sh
POST http://127.0.0.1:8000/api/products/filter?category-id=3&filters[1][]=1&filters[1][]=2&offset=0&limit=5

Key: category-id, Value:3
Key: filters[1][], Value:1
Key: filters[1][], Value:2
Key: offset, Value:0
Key: limit, Value:5
```

Sorting products by category and loading corresponding filters
```sh
POST http://127.0.0.1:8000/api/category/products/?category-id=3&offset=0&limit=1

Key: category-id, Value:3
Key: offset, Value:0
Key: limit, Value:1
```
