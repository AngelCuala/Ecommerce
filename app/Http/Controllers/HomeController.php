<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Category;

class HomeController extends Controller
{
    public function index()
    {
        // Sorting center staff should never see the buyer homepage
        if (auth()->check() && auth()->user()->role === 'sorting_center') {
            return redirect()->route('sc.dashboard');
        }

        $categories  = Category::withCount('books')->orderBy('name')->get();
        $featured    = Book::with('category')->latest()->take(8)->get();
        $bestSellers = Book::with('category')->inRandomOrder()->take(8)->get();
        $newReleases = Book::with('category')->latest()->skip(4)->take(8)->get();

        // Static reviews (no reviews table yet)
        $reviews = collect([
            (object)['rating'=>5,'comment'=>'One of the best books I have read this year. Highly recommended.','user'=>(object)['name'=>'Ava R.'],   'product'=>(object)['slug'=>'','title'=>'']],
            (object)['rating'=>4,'comment'=>'Arrived quickly and exactly as described. Will order again.',      'user'=>(object)['name'=>'James T.'], 'product'=>(object)['slug'=>'','title'=>'']],
            (object)['rating'=>5,'comment'=>'Bought this as a gift and the recipient was thrilled.',            'user'=>(object)['name'=>'Sofia M.'], 'product'=>(object)['slug'=>'','title'=>'']],
        ]);

        return view('home.index', compact(
            'categories', 'featured', 'bestSellers', 'newReleases', 'reviews'
        ));
    }
}
