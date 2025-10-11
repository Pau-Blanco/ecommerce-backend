<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::with('orderItems.product')
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($orders);
    }

    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($request) {
            $totalAmount = 0;
            $orderItems = [];

            foreach ($request->items as $item) {
                $product = \App\Models\Product::findOrFail($item['product_id']);

                if ($product->stock < $item['quantity']) {
                    return response()->json([
                        'message' => "Insufficient stock for product: {$product->name}"
                    ], Response::HTTP_BAD_REQUEST);
                }

                $unitPrice = $product->price;
                $subtotal = $unitPrice * $item['quantity'];
                $totalAmount += $subtotal;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                ];

                // Actualizar stock
                $product->decrement('stock', $item['quantity']);
            }

            $order = Order::create([
                'user_id' => $request->user()->id,
                'total_amount' => $totalAmount,
                'status' => 'pending',
            ]);

            foreach ($orderItems as &$item) {
                $item['order_id'] = $order->id;
            }

            OrderItem::insert($orderItems);

            return response()->json($order->load('orderItems.product'), Response::HTTP_CREATED);
        });
    }

    /**
     * Crear una orden desde el carrito actual del usuario
     */
    public function createFromCart(Request $request)
    {
        \Log::info('🎯 === CREATE ORDER FROM CART START ===');

        try {
            $cart = $request->user()->cart->load('items.product');

            \Log::info('📦 Cart items count: ' . $cart->items_count);
            \Log::info('📋 Cart items: ' . $cart->items->count());

            // Verificar que el carrito no esté vacío
            if ($cart->items_count === 0) {
                \Log::warning('❌ Cart is empty');
                return response()->json([
                    'message' => 'El carrito está vacío'
                ], Response::HTTP_BAD_REQUEST);
            }

            return DB::transaction(function () use ($cart, $request) {
                $totalAmount = 0;
                $orderItems = [];

                \Log::info('🔄 Processing cart items...');

                foreach ($cart->items as $cartItem) {
                    $product = $cartItem->product;

                    \Log::info('📱 Processing: ' . $product->name . ' | Quantity: ' . $cartItem->quantity . ' | Stock: ' . $product->stock);

                    // Verificar stock para cada producto
                    if ($product->stock < $cartItem->quantity) {
                        \Log::error('❌ Insufficient stock for: ' . $product->name);
                        return response()->json([
                            'message' => 'Stock insuficiente para: ' . $product->name . '. Stock disponible: ' . $product->stock
                        ], Response::HTTP_BAD_REQUEST);
                    }

                    $unitPrice = $product->price;
                    $subtotal = $unitPrice * $cartItem->quantity;
                    $totalAmount += $subtotal;

                    $orderItems[] = [
                        'product_id' => $product->id,
                        'quantity' => $cartItem->quantity,
                        'unit_price' => $unitPrice,
                    ];

                    // Actualizar stock del producto
                    $product->decrement('stock', $cartItem->quantity);
                    \Log::info('✅ Stock updated for ' . $product->name . ': ' . $product->stock);
                }

                \Log::info('💰 Total amount: ' . $totalAmount);

                // Crear la orden
                $order = Order::create([
                    'user_id' => $request->user()->id,
                    'total_amount' => $totalAmount,
                    'status' => 'pending',
                ]);

                \Log::info('✅ Order created with ID: ' . $order->id);

                // Agregar order_id a cada item y timestamps
                foreach ($orderItems as &$item) {
                    $item['order_id'] = $order->id;
                    $item['created_at'] = now();
                    $item['updated_at'] = now();
                }

                // Insertar todos los items de la orden
                OrderItem::insert($orderItems);
                \Log::info('✅ Order items inserted: ' . count($orderItems));

                // Vaciar el carrito
                $cart->items()->delete();
                \Log::info('🛒 Cart cleared');

                // Cargar relaciones para la respuesta
                $order->load('orderItems.product');

                \Log::info('🎯 === CREATE ORDER FROM CART END - SUCCESS ===');

                return response()->json([
                    'message' => 'Orden creada exitosamente desde el carrito',
                    'order' => $order,
                    'cart_cleared' => true,
                    'items_processed' => count($orderItems)
                ], Response::HTTP_CREATED);
            });

        } catch (\Exception $e) {
            \Log::error('💥 ERROR creating order from cart: ' . $e->getMessage());
            \Log::error('🔧 Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'message' => 'Error al crear la orden desde el carrito: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
