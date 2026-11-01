<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\UserNotification;
use Illuminate\Http\Request;

/**
 * New products from sellers wait here until an admin approves them; only then do they
 * appear in the shop. Edits to an approved product don't come back here.
 */
class ProductApprovalController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status') === 'rejected' ? 'rejected' : 'pending';

        $products = Product::with(['category', 'seller', 'images', 'approvalReviewer'])
            ->where('approval_status', $status)
            ->where('status', '!=', 'draft')
            ->where('is_archived', false)
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q
                ->where('title', 'like', '%' . $request->search . '%')
                ->orWhere('product_code', 'like', '%' . $request->search . '%')
                ->orWhereHas('seller', fn ($s) => $s->where('name', 'like', '%' . $request->search . '%'))))
            ->oldest()
            ->paginate(15)
            ->withQueryString();

        $counts = [
            'pending'  => Product::awaitingApproval()->where('is_archived', false)->count(),
            'rejected' => Product::where('approval_status', 'rejected')->where('status', '!=', 'draft')->where('is_archived', false)->count(),
        ];

        return view('admin.product-approvals.index', compact('products', 'status', 'counts'));
    }

    public function approve(Product $product)
    {
        if (! $product->isPendingApproval()) {
            return back()->with('error', '"' . $product->title . '" is not waiting for approval.');
        }

        $product->update([
            'approval_status'      => 'approved',
            'approval_note'        => null,
            'approval_reviewed_at' => now(),
            'approval_reviewed_by' => auth()->id(),
        ]);

        $this->notifySeller($product, 'Product approved',
            '"' . $product->title . '" was approved and is now visible in the shop.', 'info');

        return back()->with('success', '"' . $product->title . '" approved — it is now in the shop.');
    }

    public function reject(Request $request, Product $product)
    {
        if (! $product->isPendingApproval()) {
            return back()->with('error', '"' . $product->title . '" is not waiting for approval.');
        }

        $data = $request->validate(['approval_note' => 'required|string|max:500'], [
            'approval_note.required' => 'Please tell the seller why the product was rejected.',
        ]);

        $product->update([
            'approval_status'      => 'rejected',
            'approval_note'        => $data['approval_note'],
            'approval_reviewed_at' => now(),
            'approval_reviewed_by' => auth()->id(),
        ]);

        $this->notifySeller($product, 'Product not approved',
            '"' . $product->title . '" was not approved. Reason: ' . $data['approval_note'] . ' Edit and save it to submit it again.',
            'warning');

        return back()->with('success', '"' . $product->title . '" rejected. The seller was notified.');
    }

    private function notifySeller(Product $product, string $title, string $body, string $type): void
    {
        if (! $product->seller_id) {
            return;
        }

        UserNotification::create([
            'user_id' => $product->seller_id,
            'title'   => $title,
            'body'    => $body,
            'type'    => $type,
            'link'    => route('seller.books.edit', $product->id),
        ]);
    }
}
