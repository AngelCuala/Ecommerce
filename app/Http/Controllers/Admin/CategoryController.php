<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount('books')->orderBy('name')->get();
        return view('admin.categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string|max:500',
        ]);

        Category::create($request->only('name', 'description'));

        return back()->with('success', '"'.$request->name.'" category added.');
    }

    public function update(Request $request, int $id)
    {
        $cat = Category::findOrFail($id);

        $request->validate([
            'name'        => 'required|string|max:255|unique:categories,name,'.$id,
            'description' => 'nullable|string|max:500',
        ]);

        $cat->update($request->only('name', 'description'));

        return back()->with('success', 'Category updated.');
    }

    public function destroy(int $id)
    {
        $cat = Category::findOrFail($id);

        if ($cat->books()->count() > 0) {
            return back()->with('error', 'Cannot delete — this category has books assigned to it.');
        }

        $cat->delete();
        return back()->with('success', 'Category deleted.');
    }
}
