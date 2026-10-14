<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Review;
use Illuminate\Support\Facades\Storage;

/** Buyers' product reviews. Admins can remove one that breaks the rules (sellers can't). */
class ReviewController extends Controller
{
    public function index()
    {
        $reviews = Review::with(['user', 'product'])->latest()->paginate(30);

        return view('admin.reviews.index', compact('reviews'));
    }

    public function destroy(int $id)
    {
        $review = Review::with('product')->findOrFail($id);
        if ($review->photo_path) {
            Storage::disk('public')->delete($review->photo_path);
        }
        $review->delete();

        ActivityLog::record('review_removed', 'Review removed',
            'Removed a ' . $review->rating . '-star review on "' . ($review->product->title ?? 'a product') . '".');

        return back()->with('success', 'Review removed.');
    }
}
