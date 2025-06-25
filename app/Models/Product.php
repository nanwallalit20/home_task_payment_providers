<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'quantity',
        'user_id',
    ];

    /**
     * Get the user that owns the product.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the product is available (quantity > 0).
     */
    public function isAvailable(): bool
    {
        return $this->quantity > 0;
    }

    /**
     * Decrement the quantity by the specified amount.
     */
    public function decrementQuantity(int $amount = 1): bool
    {
        if ($this->quantity < $amount) {
            return false;
        }

        $this->decrement('quantity', $amount);
        return true;
    }
}
