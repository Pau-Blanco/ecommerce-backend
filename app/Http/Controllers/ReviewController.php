<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    // Obtener reseñas de un producto (público)
    public function index($productId)
    {
        $product = Product::findOrFail($productId);

        $reviews = $product->approvedReviews()
            ->with('user:id,name')
            ->latest()
            ->paginate(10);

        return response()->json([
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'average_rating' => $product->average_rating,
                'reviews_count' => $product->reviews_count,
            ],
            'reviews' => $reviews,
        ]);
    }

    // Crear una nueva reseña
    public function store(Request $request, $productId)
    {
        $user = $request->user();
        $product = Product::findOrFail($productId);

        // Verificar si el usuario ya reseñó este producto
        if ($user->reviews()->where('product_id', $productId)->exists()) {
            return response()->json([
                'message' => 'Ya has reseñado este producto'
            ], Response::HTTP_CONFLICT);
        }

        // Opcional: Verificar que el usuario compró el producto
        // if (!$user->hasPurchasedProduct($productId)) {
        //     return response()->json([
        //         'message' => 'Solo puedes reseñar productos que has comprado'
        //     ], Response::HTTP_FORBIDDEN);
        // }

        $request->validate([
            'rating' => 'required|integer|between:1,5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $review = $user->reviews()->create([
            'product_id' => $productId,
            'rating' => $request->rating,
            'comment' => $request->comment,
            'is_approved' => true, // Auto-aprobar, o false para moderación
        ]);

        $review->load('user:id,name');

        return response()->json([
            'message' => 'Reseña creada exitosamente',
            'review' => $review,
            'product_stats' => [
                'average_rating' => $product->fresh()->average_rating,
                'reviews_count' => $product->fresh()->reviews_count,
            ]
        ], Response::HTTP_CREATED);
    }

    // Actualizar reseña del usuario
    public function update(Request $request, $reviewId)
    {
        $review = Review::where('user_id', $request->user()->id)
            ->findOrFail($reviewId);

        $request->validate([
            'rating' => 'sometimes|integer|between:1,5',
            'comment' => 'sometimes|nullable|string|max:1000',
        ]);

        $review->update($request->only(['rating', 'comment']));

        return response()->json([
            'message' => 'Reseña actualizada exitosamente',
            'review' => $review->load('user:id,name'),
            'product_stats' => [
                'average_rating' => $review->product->fresh()->average_rating,
                'reviews_count' => $review->product->fresh()->reviews_count,
            ]
        ]);
    }

    // Eliminar reseña del usuario
    public function destroy(Request $request, $reviewId)
    {
        $review = Review::where('user_id', $request->user()->id)
            ->findOrFail($reviewId);

        $product = $review->product;
        $review->delete();

        return response()->json([
            'message' => 'Reseña eliminada exitosamente',
            'product_stats' => [
                'average_rating' => $product->fresh()->average_rating,
                'reviews_count' => $product->fresh()->reviews_count,
            ]
        ]);
    }

    // Obtener reseña del usuario actual para un producto
    public function myReview(Request $request, $productId)
    {
        $review = $request->user()
            ->reviews()
            ->where('product_id', $productId)
            ->first();

        return response()->json([
            'review' => $review,
        ]);
    }

    // Obtener todas las reseñas del usuario actual
    public function myReviews(Request $request)
    {
        $reviews = $request->user()
            ->reviews()
            ->with('product:id,name,image_url')
            ->latest()
            ->paginate(10);

        return response()->json($reviews);
    }

    // Estadísticas de reseñas para admin
    public function stats($productId)
    {
        $product = Product::findOrFail($productId);

        $ratingDistribution = Review::where('product_id', $productId)
            ->approved()
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->orderBy('rating', 'desc')
            ->get();

        return response()->json([
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'average_rating' => $product->average_rating,
                'reviews_count' => $product->reviews_count,
            ],
            'rating_distribution' => $ratingDistribution,
        ]);
    }
}
