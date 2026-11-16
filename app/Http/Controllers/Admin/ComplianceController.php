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

        // Off-platform contact: sellers with strikes in the last 90 days (3 or more first: they need a review).
        $since = now()->subDays(\App\Services\ContactStrikes::WINDOW_DAYS);
        $strikeSellers = \App\Models\ContactStrike::where('created_at', '>=', $since)->counted()
            ->selectRaw('user_id, COUNT(*) as strikes, MAX(created_at) as last_at, SUM(CASE WHEN reviewed_at IS NULL THEN 1 ELSE 0 END) as unreviewed')
            ->groupBy('user_id')->orderByDesc('strikes')->orderByDesc('last_at')->get()
            ->map(fn ($row) => (object) ['seller' => User::with('sellerApplication')->find($row->user_id), 'strikes' => (int) $row->strikes,
                'unreviewed' => (int) $row->unreviewed, 'last_at' => \Illuminate\Support\Carbon::parse($row->last_at),
                'recent' => \App\Models\ContactStrike::where('user_id', $row->user_id)->where('created_at', '>=', $since)->with('message')->latest()->take(3)->get()])
            ->filter(fn ($r) => $r->seller)->values();
        // Appeals waiting for a decision.
        $appeals = \App\Models\ContactStrike::where('appeal_status', 'pending')->with('user', 'message')->oldest('appealed_at')->get();

        return view('admin.compliance.index', compact('sellers', 'recentViolations', 'flagged', 'strikeSellers', 'appeals'));
    }

    /** Accept (the strike no longer counts) or reject a seller's appeal, with a note the seller sees. */
    public function decideAppeal(Request $request, \App\Models\ContactStrike $strike)
    {
        $data = $request->validate(['decision' => 'required|in:accept,reject', 'note' => 'required|string|min:5|max:500'],
            ['note.required' => 'Write a short note for the seller (5 to 500 characters).']);
        try {
            app(\App\Services\ContactStrikes::class)->decide($strike, auth()->user(), $data['decision'] === 'accept', trim($data['note']));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $data['decision'] === 'accept' ? 'Appeal accepted: the strike no longer counts.' : 'Appeal rejected: the strike still counts.');
    }

    /** The administrator looked at a seller's contact strikes (they stay counted; the next action is a violation). */
    public function reviewStrikes(int $sellerId)
    {
        $n = \App\Models\ContactStrike::where('user_id', $sellerId)->whereNull('reviewed_at')
            ->update(['reviewed_at' => now(), 'reviewed_by' => auth()->id()]);

        return back()->with('success', $n ? "Marked {$n} " . \Illuminate\Support\Str::plural('strike', $n) . ' as reviewed.' : 'Nothing new to review.');
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
