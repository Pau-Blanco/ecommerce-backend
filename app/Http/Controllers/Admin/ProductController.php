<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('category:id,name');

        // Búsqueda
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhere('sku', 'like', '%' . $search . '%');
            });
        }

        // Filtro por categoría
        if ($request->has('category_id') && $request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        // Filtro por stock
        if ($request->has('stock_filter')) {
            switch ($request->stock_filter) {
                case 'low':
                    $query->where('stock', '<', 10);
                    break;
                case 'out':
                    $query->where('stock', 0);
                    break;
                case 'in_stock':
                    $query->where('stock', '>', 0);
                    break;
            }
        }

        // Filtro por visibilidad
        if ($request->has('is_visible')) {
            $query->where('is_visible', $request->boolean('is_visible'));
        }

        // Filtro por destacados
        if ($request->has('is_featured')) {
            $query->where('is_featured', $request->boolean('is_featured'));
        }

        // Ordenamiento
        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        // Validar campos de ordenamiento
        $allowedSortFields = ['name', 'price', 'stock', 'created_at', 'updated_at'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = $request->get('per_page', 15);
        $products = $query->paginate($perPage);

        // Obtener categorías para filtros (opcional)
        $categories = Category::select('id', 'name')->get();

        return response()->json([
            'products' => $products,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0|gt:price',
            'cost_per_item' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'sku' => 'nullable|string|unique:products,sku',
            'barcode' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'is_featured' => 'boolean',
            'is_visible' => 'boolean',
            'images' => 'nullable|array',
            'images.*' => 'url',
        ]);

        DB::beginTransaction();
        try {
            $product = Product::create([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'description' => $request->description,
                'price' => $request->price,
                'compare_price' => $request->compare_price,
                'cost_per_item' => $request->cost_per_item,
                'stock' => $request->stock,
                'sku' => $request->sku,
                'barcode' => $request->barcode,
                'category_id' => $request->category_id,
                'is_featured' => $request->is_featured ?? false,
                'is_visible' => $request->is_visible ?? true,
                'images' => $request->images ?? [],
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Producto creado correctamente',
                'product' => $product->load('category')
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear el producto',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id)
    {
        $product = Product::with(['category', 'reviews.user:id,name'])
            ->withCount('orderItems')
            ->findOrFail($id);

        return response()->json([
            'product' => $product
        ]);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0|gt:price',
            'cost_per_item' => 'nullable|numeric|min:0',
            'stock' => 'sometimes|required|integer|min:0',
            'sku' => 'nullable|string|unique:products,sku,' . $id,
            'barcode' => 'nullable|string',
            'category_id' => 'sometimes|required|exists:categories,id',
            'is_featured' => 'boolean',
            'is_visible' => 'boolean',
            'images' => 'nullable|array',
            'images.*' => 'url',
        ]);

        DB::beginTransaction();
        try {
            $updateData = $request->all();

            // Si cambia el nombre, actualizar el slug
            if ($request->has('name') && $request->name !== $product->name) {
                $updateData['slug'] = Str::slug($request->name);
            }

            $product->update($updateData);

            DB::commit();

            return response()->json([
                'message' => 'Producto actualizado correctamente',
                'product' => $product->load('category')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar el producto',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);

        // Verificar si hay órdenes con este producto
        if ($product->orderItems()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el producto porque tiene órdenes asociadas.'
            ], Response::HTTP_CONFLICT);
        }

        DB::beginTransaction();
        try {
            $product->delete();

            DB::commit();

            return response()->json([
                'message' => 'Producto eliminado correctamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al eliminar el producto',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
