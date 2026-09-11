<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
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
            ->withCount(['books', 'books as violations_count' => fn($q) =>
                $q->whereHas('violations')
            ])
            ->with(['sellerApplication'])
            ->latest()
            ->get();

        // Recent violations
        $recentViolations = SellerViolation::with(['seller', 'book', 'admin'])
            ->latest()
            ->take(10)
            ->get();

        // Flagged products: products whose category doesn't match seller's registered line of business
        $flagged = Book::with(['seller.sellerApplication', 'category'])
            ->whereHas('seller', fn($q) => $q->where('role', 'seller'))
            ->get()
            ->filter(fn($book) => $this->isCategoryMismatch($book));

        return view('admin.compliance.index', compact('sellers', 'recentViolations', 'flagged'));
    }

    /** Show a single seller's compliance details */
    public function show(int $sellerId)
    {
        $seller = User::where('role', 'seller')
            ->with(['sellerApplication', 'books.category'])
            ->findOrFail($sellerId);

        $violations = SellerViolation::where('seller_id', $sellerId)
            ->with(['book', 'admin'])
            ->latest()
            ->get();

        $flaggedBooks = $seller->books->filter(fn($b) => $this->isCategoryMismatch($b));

        return view('admin.compliance.show', compact('seller', 'violations', 'flaggedBooks'));
    }

    /** Issue a warning or take action on a seller */
    public function store(Request $request)
    {
        $data = $request->validate([
            'seller_id' => 'required|exists:users,id',
            'book_id'   => 'nullable|exists:books,id',
            'type'      => 'required|in:wrong_category,prohibited_product,inappropriate_content,misleading_info,other',
            'action'    => 'required|in:warning,product_removed,account_suspended,account_deactivated',
            'note'      => 'required|string|max:1000',
        ]);

        $data['admin_id'] = auth()->id();

        SellerViolation::create($data);

        $seller = User::findOrFail($data['seller_id']);

        // Execute the action
        match ($data['action']) {
            'product_removed'     => $data['book_id']
                ? Book::findOrFail($data['book_id'])->delete()
                : null,
            'account_suspended'   => $seller->update(['role' => 'suspended']),
            'account_deactivated' => $seller->update(['role' => 'deactivated']),
            default               => null, // warning — no automated action
        };

        return back()->with('success',
            ucfirst($data['action']) . ' applied to ' . $seller->name . '.'
        );
    }

    /** Check if a book's category mismatches the seller's registered line of business */
    private function isCategoryMismatch(Book $book): bool
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
