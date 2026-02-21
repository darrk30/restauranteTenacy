<?php

namespace App\Observers;

use App\Models\Promotion;
use Illuminate\Support\Facades\Storage;

class PromotionObserver
{
    /**
     * Handle the Promotion "created" event.
     */
    public function created(Promotion $promotion): void
    {
        //
    }

    public function updated(Promotion $promotion): void
    {
        if ($promotion->isDirty('image_path')) {
            $oldImage = $promotion->getOriginal('image_path');

            if ($oldImage && $oldImage !== $promotion->image_path) {
                Storage::disk('public')->delete($oldImage);
            }
        }
    }

    public function deleted(Promotion $promotion): void
    {
        if ($promotion->image_path) {
            Storage::disk('public')->delete($promotion->image_path);
        }
    }

    /**
     * Handle the Promotion "restored" event.
     */
    public function restored(Promotion $promotion): void
    {
        //
    }

    /**
     * Handle the Promotion "force deleted" event.
     */
    public function forceDeleted(Promotion $promotion): void
    {
        //
    }
}
