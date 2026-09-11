<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class ReviewController extends Controller
{
    public function index()
    {
        $reviews = collect([
            (object)['id'=>1,'rating'=>5,'title'=>'Fantastic read',   'comment'=>'Could not put it down.', 'user'=>(object)['name'=>'Ava Reader'],'product'=>(object)['title'=>'The Silent Meridian'],'created_at'=>now()->subDays(3)],
            (object)['id'=>2,'rating'=>4,'title'=>'Very good',        'comment'=>'Well written and engaging.','user'=>(object)['name'=>'James T.'],  'product'=>(object)['title'=>'Midnight Sessions'],   'created_at'=>now()->subDays(5)],
            (object)['id'=>3,'rating'=>5,'title'=>'Perfect gift',     'comment'=>'The recipient loved it.',   'user'=>(object)['name'=>'Sofia M.'],  'product'=>(object)['title'=>'Salt & Stone'],         'created_at'=>now()->subDays(7)],
            (object)['id'=>4,'rating'=>3,'title'=>'Decent',           'comment'=>'Not bad, not amazing.',     'user'=>(object)['name'=>'Liam K.'],   'product'=>(object)['title'=>'Nightfall Protocol'],   'created_at'=>now()->subDays(10)],
            (object)['id'=>5,'rating'=>5,'title'=>'Must have',        'comment'=>'Exceptional quality.',      'user'=>(object)['name'=>'Nina C.'],   'product'=>(object)['title'=>'Elements of Astronomy'],'created_at'=>now()->subDays(12)],
        ]);

        return view('admin.reviews.index', compact('reviews'));
    }

    public function destroy(int $id)
    {
        return back()->with('success', 'Review removed.');
    }
}
