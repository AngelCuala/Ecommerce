<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SellerViolation;
use App\Models\User;
use Illuminate\Http\Request;

class ComplianceController extends Controller
{
    /** Compliance dashboard — show all sellers and their products for review */
    public function index(Request $request)
    {
        // All sellers with their products
        $sellers = User::where('role', 'seller')
            ->withCount(['products as books_count', 'products as violations_count' => fn($q) =>
                $q->whereHas('violations')
            ])
            ->with(['sellerApplication'])
            ->latest()
            ->get();

        // Recent violations
        $recentViolations = SellerViolation::with(['seller', 'product', 'admin'])
            ->latest()
            ->take(10)
            ->get();

        // Flagged products: products whose category doesn't match seller's registered line of business
        $flagged = Product::with(['seller.sellerApplication', 'category', 'images'])
            ->whereHas('seller', fn($q) => $q->where('role', 'seller'))
            ->get()
            ->filter(fn($book) => $this->isCategoryMismatch($book));

        return view('admin.compliance.index', compact('sellers', 'recentViolations', 'flagged'));
    }

    /** Show a single seller's compliance details */
    public function show(int $sellerId)
    {
        $seller = User::where('role', 'seller')
            ->with(['sellerApplication', 'products.category', 'products.images'])
            ->findOrFail($sellerId);

        $violations = SellerViolation::where('seller_id', $sellerId)
            ->with(['product', 'admin'])
            ->latest()
            ->get();

        $flaggedBooks = $seller->products->filter(fn($b) => $this->isCategoryMismatch($b));

        return view('admin.compliance.show', compact('seller', 'violations', 'flaggedBooks'));
    }

    /** Issue a warning or take action on a seller */
    public function store(Request $request)
    {
        $data = $request->validate([
            'seller_id' => 'required|exists:users,id',
            'product_id'   => 'nullable|exists:products,id',
            'type'      => 'required|in:wrong_category,prohibited_product,inappropriate_content,misleading_info,other',
            'action'    => 'required|in:warning,account_suspended,account_deactivated',   // admins can't remove products
            'note'      => 'required|string|max:1000',
        ]);

        $data['admin_id'] = auth()->id();

        $violation = SellerViolation::create($data);

        $seller = User::findOrFail($data['seller_id']);

        // The seller is told right away (notification) and sees it on their dashboard until they acknowledge it.
        $what = SellerViolation::typeLabels()[$data['type']] ?? 'Policy violation';
        $product = $violation->product?->title;
        \App\Models\UserNotification::create([
            'user_id' => $seller->id, 'type' => 'warning',
            'title'   => $data['action'] === 'warning' ? 'Warning from ALVY: ' . $what : 'ALVY took action on your account: ' . $what,
            'body'    => ($product ? '"' . $product . '": ' : '') . $data['note']
                . match ($data['action']) {
                    'account_suspended'   => ' Your account is suspended.',
                    'account_deactivated' => ' Your account is deactivated.',
                    default               => ' Please fix this to avoid further action.',
                },
            'link'    => route('seller.dashboard', [], false),
        ]);

        // Execute the action
        match ($data['action']) {
            'account_suspended'   => $seller->update(['role' => 'suspended']),
            'account_deactivated' => $seller->update(['role' => 'deactivated']),
            default               => null, // warning — no automated action
        };

        return back()->with('success',
            (SellerViolation::actionLabels()[$data['action']] ?? ucfirst($data['action'])) . ' sent to ' . $seller->name . '. They were notified and see it on their dashboard.'
        );
    }

    /** Check if a book's category mismatches the seller's registered line of business */
    private function isCategoryMismatch(Product $book): bool
    {
        $lineOfBusiness = $book->seller?->sellerApplication?->line_of_business;
        if (! $lineOfBusiness || ! $book->category) return false;

        $categoryName = strtolower($book->category->name);
        $line         = strtolower($lineOfBusiness);

        // Consider it a match if any word from the line of business appears in the category
        $words = array_filter(explode(' ', preg_replace('/[^a-z0-9 ]/', '', $line)));
        foreach ($words as $word) {
            if (strlen($word) > 3 && str_contains($categoryName, $word)) return false;
        }

        // Also passes if category name appears in line of business
        if (str_contains($line, $categoryName)) return false;

        return true; // mismatch detected
    }
}
