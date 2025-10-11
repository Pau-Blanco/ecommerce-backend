<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CartController extends Controller
{
    // Obtener el carrito del usuario
    public function index(Request $request)
    {
        \Log::info('🛒 === GET CART DEBUG ===');

        $cart = $request->user()->cart;
        \Log::info('📦 Cart ID: ' . $cart->id);

        // Cargar relaciones explícitamente
        $cart->load([
            'items' => function ($query) {
                $query->with('product');
            }
        ]);

        $itemsCount = $cart->items()->count();
        \Log::info('🔢 Items count in database: ' . $itemsCount);

        $allItems = $cart->items()->with('product')->get();
        \Log::info('📋 Items found: ' . $allItems->count());

        foreach ($allItems as $item) {
            \Log::info('   - Item ID: ' . $item->id . ' | Product: ' . $item->product->name . ' | Qty: ' . $item->quantity);
        }

        return response()->json([
            'cart' => $cart,
            'total' => $cart->total,
            'items_count' => $cart->items_count,
            'items' => $cart->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,
                    'product_price' => $item->product->price,
                    'product_image' => $item->product->image_url,
                    'quantity' => $item->quantity,
                    'subtotal' => $item->product->price * $item->quantity,
                ];
            }),
            'debug' => [
                'database_items_count' => $itemsCount,
                'loaded_items_count' => $cart->items->count(),
            ]
        ]);
    }

    // Agregar producto al carrito
    public function add(Request $request)
    {
        \Log::info('🎯 === ADD TO CART - VERIFICACIÓN COMPLETA ===');
        \Log::info('🔍 Request method: ' . $request->method());
        \Log::info('🔍 Content-Type: ' . $request->header('Content-Type'));
        \Log::info('🔍 Full URL: ' . $request->fullUrl());
        \Log::info('🔍 All headers: ' . json_encode($request->headers->all()));

        // DIAGNÓSTICO PROFUNDO DEL REQUEST
        \Log::info('🔍 Raw input (php://input): ' . file_get_contents('php://input'));
        \Log::info('🔍 Request->all(): ' . json_encode($request->all()));
        \Log::info('🔍 Request->json(): ' . json_encode($request->json()->all()));
        \Log::info('🔍 Request getContent(): ' . $request->getContent());

        \Log::info('📝 User: ' . $request->user()->email . ' (ID: ' . $request->user()->id . ')');
        \Log::info('🔄 Specific fields check:');
        \Log::info('   - product_id: ' . ($request->input('product_id') ?? 'NULL'));
        \Log::info('   - quantity: ' . ($request->input('quantity') ?? 'NULL'));
        \Log::info('   - product_id from json: ' . ($request->json('product_id') ?? 'NULL'));
        \Log::info('   - quantity from json: ' . ($request->json('quantity') ?? 'NULL'));

        try {
            // 📝 VALIDACIÓN TEMPORALMENTE COMENTADA PARA DIAGNÓSTICO
            $request->validate([
                'product_id' => 'required|exists:products,id',
                'quantity' => 'required|integer|min:1',
            ]);

            // 🎯 INTENTAR OBTENER VALORES DE MÚLTIPLES FUENTES
            $productId = $request->json('product_id') ?? $request->input('product_id') ?? $request->get('product_id') ?? 1;
            $quantity = $request->json('quantity') ?? $request->input('quantity') ?? $request->get('quantity') ?? 1;

            \Log::info('🔄 Final values - product_id: ' . $productId . ', quantity: ' . $quantity);

            $cart = $request->user()->cart;
            \Log::info('🛒 Cart ID: ' . $cart->id);

            $product = Product::find($productId);
            if (!$product) {
                \Log::error('❌ Product not found with ID: ' . $productId);
                return response()->json([
                    'message' => 'Producto no encontrado con ID: ' . $productId
                ], Response::HTTP_BAD_REQUEST);
            }

            \Log::info('📱 Product: ' . $product->name . ' | Stock: ' . $product->stock . ' | Price: ' . $product->price);

            // Verificar stock
            if ($product->stock < $quantity) {
                \Log::warning('❌ Insufficient stock');
                return response()->json([
                    'message' => 'Stock insuficiente para ' . $product->name
                ], Response::HTTP_BAD_REQUEST);
            }
            \Log::info('✅ Stock check passed');

            // Buscar si el producto ya está en el carrito
            $existingItem = $cart->items()
                ->where('product_id', $productId)
                ->first();

            \Log::info('🔍 Existing item: ' . ($existingItem ? 'YES (ID: ' . $existingItem->id . ')' : 'NO'));

            if ($existingItem) {
                \Log::info('🔄 Updating existing item. Current quantity: ' . $existingItem->quantity);
                $newQuantity = $existingItem->quantity + $quantity;

                if ($product->stock < $newQuantity) {
                    \Log::warning('❌ Insufficient stock after update');
                    return response()->json([
                        'message' => 'Stock insuficiente. Ya tienes ' . $existingItem->quantity . ' en el carrito.'
                    ], Response::HTTP_BAD_REQUEST);
                }

                $existingItem->update(['quantity' => $newQuantity]);
                $item = $existingItem;
                \Log::info('✅ Item updated. New quantity: ' . $existingItem->quantity);
            } else {
                \Log::info('🆕 Creating new cart item...');
                try {
                    $item = $cart->items()->create([
                        'cart_id' => $cart->id,
                        'product_id' => $productId,
                        'quantity' => $quantity,
                    ]);
                    \Log::info('✅ Cart item created successfully. ID: ' . $item->id);
                } catch (\Exception $e) {
                    \Log::error('❌ Error creating cart item: ' . $e->getMessage());
                    \Log::error('🔧 Stack trace: ' . $e->getTraceAsString());
                    return response()->json([
                        'message' => 'Error al agregar al carrito: ' . $e->getMessage()
                    ], 500);
                }
            }

            // Verificar que el item se guardó
            $itemCount = $cart->items()->count();
            \Log::info('📊 Total items in cart after operation: ' . $itemCount);

            // Recargar relaciones
            $cart->load('items.product');
            \Log::info('🔄 Cart reloaded with items');

            \Log::info('🎯 === ADD TO CART DEBUG END - SUCCESS ===');

            return response()->json([
                'message' => 'Producto agregado al carrito',
                'cart' => $cart,
                'total' => $cart->total,
                'items_count' => $cart->items_count,
                'debug' => [
                    'cart_id' => $cart->id,
                    'items_count' => $itemCount,
                    'item_created_id' => $item->id ?? null,
                    'request_data_received' => $request->all(),
                    'json_data_received' => $request->json()->all(),
                    'final_product_id_used' => $productId,
                    'final_quantity_used' => $quantity
                ]
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            \Log::error('💥 UNEXPECTED ERROR: ' . $e->getMessage());
            \Log::error('🔧 Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'Error inesperado: ' . $e->getMessage()
            ], 500);
        }
    }

    // Actualizar cantidad de un item
    public function update(Request $request, $itemId)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = $request->user()->cart;
        $item = $cart->items()->findOrFail($itemId);
        $product = $item->product;

        // Verificar stock
        if ($product->stock < $request->quantity) {
            return response()->json([
                'message' => 'Stock insuficiente para ' . $product->name
            ], Response::HTTP_BAD_REQUEST);
        }

        $item->update(['quantity' => $request->quantity]);

        $cart->load('items.product');

        return response()->json([
            'message' => 'Carrito actualizado',
            'cart' => $cart,
            'total' => $cart->total,
            'items_count' => $cart->items_count,
        ]);
    }

    // Eliminar item del carrito
    public function remove($itemId, Request $request)
    {
        $cart = $request->user()->cart;
        $item = $cart->items()->findOrFail($itemId);

        $item->delete();

        $cart->load('items.product');

        return response()->json([
            'message' => 'Producto eliminado del carrito',
            'cart' => $cart,
            'total' => $cart->total,
            'items_count' => $cart->items_count,
        ]);
    }

    // Vaciar carrito
    public function clear(Request $request)
    {
        $cart = $request->user()->cart;
        $cart->items()->delete();

        return response()->json([
            'message' => 'Carrito vaciado',
            'cart' => $cart->fresh(),
            'total' => 0,
            'items_count' => 0,
        ]);
    }
}
