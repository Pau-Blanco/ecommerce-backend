<?php
namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WishlistController extends Controller
{
    // Obtener todos los productos en la wishlist del usuario
    public function index(Request $request)
    {
        $wishlistItems = $request->user()
            ->wishlistProducts()
            ->with('category')
            ->get();

        return response()->json([
            'wishlist' => $wishlistItems,
            'count' => $wishlistItems->count(),
        ]);
    }

    // Agregar producto a la wishlist
    public function add(Request $request, $productId)
    {
        $user = $request->user();
        $product = Product::findOrFail($productId);

        // Verificar si ya está en la wishlist
        if ($user->wishlistProducts()->where('product_id', $productId)->exists()) {
            return response()->json([
                'message' => 'El producto ya está en tu lista de deseos'
            ], Response::HTTP_CONFLICT);
        }

        // Agregar a la wishlist
        $user->wishlistProducts()->attach($productId);

        return response()->json([
            'message' => 'Producto agregado a tu lista de deseos',
            'product' => $product->load('category'),
            'wishlist_count' => $user->wishlistProducts()->count()
        ], Response::HTTP_CREATED);
    }

    // Eliminar producto de la wishlist
    public function remove(Request $request, $productId)
    {
        $user = $request->user();

        // Verificar si existe en la wishlist
        if (!$user->wishlistProducts()->where('product_id', $productId)->exists()) {
            return response()->json([
                'message' => 'El producto no está en tu lista de deseos'
            ], Response::HTTP_NOT_FOUND);
        }

        // Eliminar de la wishlist
        $user->wishlistProducts()->detach($productId);

        return response()->json([
            'message' => 'Producto eliminado de tu lista de deseos',
            'wishlist_count' => $user->wishlistProducts()->count()
        ]);
    }

    // Mover producto de wishlist al carrito
    public function moveToCart(Request $request, $productId)
    {
        $user = $request->user();

        // Verificar si está en la wishlist
        if (!$user->wishlistProducts()->where('product_id', $productId)->exists()) {
            return response()->json([
                'message' => 'El producto no está en tu lista de deseos'
            ], Response::HTTP_NOT_FOUND);
        }

        $product = Product::findOrFail($productId);

        // Verificar stock
        if ($product->stock < 1) {
            return response()->json([
                'message' => 'Stock insuficiente para agregar al carrito'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Agregar al carrito (cantidad 1 por defecto)
        $cart = $user->cart;
        $existingCartItem = $cart->items()
            ->where('product_id', $productId)
            ->first();

        if ($existingCartItem) {
            // Si ya está en el carrito, aumentar cantidad
            $existingCartItem->increment('quantity');
        } else {
            // Si no está, crear nuevo item
            $cart->items()->create([
                'product_id' => $productId,
                'quantity' => 1,
            ]);
        }

        // Eliminar de la wishlist
        $user->wishlistProducts()->detach($productId);

        return response()->json([
            'message' => 'Producto movido al carrito exitosamente',
            'cart_count' => $user->cart->items_count,
            'wishlist_count' => $user->wishlistProducts()->count()
        ]);
    }

    // Verificar si un producto está en la wishlist
    public function check(Request $request, $productId)
    {
        $isInWishlist = $request->user()
            ->wishlistProducts()
            ->where('product_id', $productId)
            ->exists();

        return response()->json([
            'is_in_wishlist' => $isInWishlist
        ]);
    }

    // Limpiar toda la wishlist
    public function clear(Request $request)
    {
        $user = $request->user();
        $count = $user->wishlistProducts()->count();

        $user->wishlistProducts()->detach();

        return response()->json([
            'message' => 'Lista de deseos vaciada',
            'items_removed' => $count
        ]);
    }
}
