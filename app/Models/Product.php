<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'stock',
        'image_url',
        'category_id',

    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function approvedReviews()
    {
        return $this->hasMany(Review::class)->approved();
    }

    // Calcular rating promedio
    public function getAverageRatingAttribute()
    {
        return $this->approvedReviews()->avg('rating') ?: 0;
    }

    // Contar total de reseñas
    public function getReviewsCountAttribute()
    {
        return $this->approvedReviews()->count();
    }

    // Verificar si el usuario actual ya reseñó este producto
    public function getCurrentUserReviewAttribute()
    {
        if (!auth()->check()) {
            return null;
        }

        return $this->reviews()
            ->where('user_id', auth()->id())
            ->first();
    }
}
