<?php

namespace App\Observers;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;

class ProductObserver
{
    /**
     * Handle the Product "created" event.
     */
    public function created(Product $product): void
    {
        //
    }

    public function updated(Product $product): void
    {
        // Si el campo 'image_path' cambió
        if ($product->isDirty('image_path')) {
            $oldImage = $product->getOriginal('image_path');
            if ($oldImage && $oldImage !== $product->image_path) {
                Storage::disk('public')->delete($oldImage);
            }
        }
    }

    public function deleted(Product $product): void
    {
        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }
    }

    /**
     * Handle the Product "restored" event.
     */
    public function restored(Product $product): void
    {
        //
    }

    /**
     * Handle the Product "force deleted" event.
     */
    public function forceDeleted(Product $product): void
    {
        //
    }
}
