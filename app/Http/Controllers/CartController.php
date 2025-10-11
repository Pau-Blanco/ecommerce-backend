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
        $cart = $request->user()->cart->load('items.product');

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
                    'subtotal' => $item->subtotal,
                ];
            })
        ]);
    }

    // Agregar producto al carrito
    public function add(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = $request->user()->cart;
        $product = Product::findOrFail($request->product_id);

        // Verificar stock
        if ($product->stock < $request->quantity) {
            return response()->json([
                'message' => 'Stock insuficiente para ' . $product->name
            ], Response::HTTP_BAD_REQUEST);
        }

        // Buscar si el producto ya está en el carrito
        $existingItem = $cart->items()
            ->where('product_id', $request->product_id)
            ->first();

        if ($existingItem) {
            // Actualizar cantidad si ya existe
            $newQuantity = $existingItem->quantity + $request->quantity;

            if ($product->stock < $newQuantity) {
                return response()->json([
                    'message' => 'Stock insuficiente. Ya tienes ' . $existingItem->quantity . ' en el carrito.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $existingItem->update(['quantity' => $newQuantity]);
            $item = $existingItem;
        } else {
            // Crear nuevo item
            $item = $cart->items()->create([
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
            ]);
        }

        $cart->load('items.product');

        return response()->json([
            'message' => 'Producto agregado al carrito',
            'cart' => $cart,
            'total' => $cart->total,
            'items_count' => $cart->items_count,
        ], Response::HTTP_CREATED);
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
