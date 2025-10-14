<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::withCount('products');

        // Búsqueda
        if ($request->has('search') && $request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Ordenamiento
        $sortField = $request->get('sort_field', 'name');
        $sortDirection = $request->get('sort_direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        $categories = $query->paginate($request->get('per_page', 15));

        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $category = Category::create([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'description' => $request->description,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Categoría creada correctamente',
                'category' => $category
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear la categoría',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id)
    {
        $category = Category::withCount('products')->findOrFail($id);

        // Productos de esta categoría
        $products = Product::where('category_id', $id)
            ->select('id', 'name', 'price', 'stock', 'image_url')
            ->latest()
            ->take(10)
            ->get();

        return response()->json([
            'category' => $category,
            'recent_products' => $products
        ]);
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:categories,name,' . $id,
            'description' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $updateData = $request->all();

            // Si cambia el nombre, actualizar el slug
            if ($request->has('name') && $request->name !== $category->name) {
                $updateData['slug'] = Str::slug($request->name);
            }

            $category->update($updateData);

            DB::commit();

            return response()->json([
                'message' => 'Categoría actualizada correctamente',
                'category' => $category
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar la categoría',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);

        // Verificar si hay productos en esta categoría
        if ($category->products()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar la categoría porque tiene productos asociados.'
            ], Response::HTTP_CONFLICT);
        }

        DB::beginTransaction();
        try {
            $category->delete();

            DB::commit();

            return response()->json([
                'message' => 'Categoría eliminada correctamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al eliminar la categoría',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
