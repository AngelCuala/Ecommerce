<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CategoryPageController;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\HomeBanner;
use App\Support\VoucherRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Home banners: the admin adds slides for an event or sale to the home page's big banner (website and app). */
class HomeBannerController extends Controller
{
    public function index()
    {
        $banners = HomeBanner::orderBy('sort_order')->latest('id')->get();
        $categories = collect(CategoryPageController::catalog())->pluck('name', 'slug');
        $hideDefaults = HomeBanner::hideDefaults();

        return view('admin.banners.index', compact('banners', 'categories', 'hideDefaults'));
    }

    public function create()
    {
        return view('admin.banners.form', ['banner' => new HomeBanner(['is_active' => true, 'focus' => 'center', 'button_label' => 'Shop now']),
            'categories' => collect(CategoryPageController::catalog())->pluck('name', 'slug')]);
    }

    public function edit(HomeBanner $banner)
    {
        return view('admin.banners.form', ['banner' => $banner, 'categories' => collect(CategoryPageController::catalog())->pluck('name', 'slug')]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);
        $data['image'] = $request->file('image')->store('banners', 'public');
        $banner = HomeBanner::create($data);

        ActivityLog::record('home_banner_added', 'Home Banner Added', 'Added home banner "' . $banner->title . '".', $banner);

        return redirect()->route('admin.banners.index')->with('success', 'Banner "' . $banner->title . '" added.');
    }

    public function update(Request $request, HomeBanner $banner)
    {
        $data = $this->validated($request, $banner);
        if ($request->hasFile('image')) {
            $old = $banner->image;
            $data['image'] = $request->file('image')->store('banners', 'public');
            Storage::disk('public')->delete($old);
        }
        $banner->update($data);

        ActivityLog::record('home_banner_updated', 'Home Banner Updated', 'Updated home banner "' . $banner->title . '".', $banner);

        return redirect()->route('admin.banners.index')->with('success', 'Banner "' . $banner->title . '" saved.');
    }

    public function toggle(HomeBanner $banner)
    {
        $banner->update(['is_active' => ! $banner->is_active]);

        return back()->with('success', 'Banner "' . $banner->title . '" turned ' . ($banner->is_active ? 'on' : 'off') . '.');
    }

    public function destroy(HomeBanner $banner)
    {
        Storage::disk('public')->delete($banner->image);
        $banner->delete();

        ActivityLog::record('home_banner_deleted', 'Home Banner Deleted', 'Deleted home banner "' . $banner->title . '".');

        return back()->with('success', 'Banner "' . $banner->title . '" deleted.');
    }

    /** "Show only my banners" — hides ALVY's built-in slides while one of the admin's is live. */
    public function settings(Request $request)
    {
        HomeBanner::setHideDefaults($request->boolean('hide_defaults'));

        return back()->with('success', $request->boolean('hide_defaults')
            ? 'Only your banners show while one is live.'
            : 'ALVY\'s own slides show after your banners.');
    }

    private function validated(Request $request, ?HomeBanner $banner): array
    {
        $slugs = collect(CategoryPageController::catalog())->pluck('slug')->implode(',');

        $data = VoucherRules::inBusinessTime($request, ['starts_at', 'ends_at'], fn () => $request->validate([
            'image'         => [$banner ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=800,min_height=300'],
            'eyebrow'       => 'nullable|string|max:40',
            'title'         => 'required|string|max:80',
            'highlight'     => 'nullable|string|max:40',
            'subtitle'      => 'nullable|string|max:160',
            'button_label'  => 'nullable|string|max:30',
            'category_slug' => 'nullable|in:' . $slugs,
            'focus'         => 'required|in:top,center,bottom',
            'starts_at'     => 'nullable|date',
            'ends_at'       => 'nullable|date|after:starts_at',
            'sort_order'    => 'nullable|integer|min:0|max:999',
        ], [
            'image.required'   => 'Choose a photo for the banner.',
            'image.dimensions' => 'The photo is too small: use at least 800 × 300 pixels (a wide photo, about 1600 × 640, looks best).',
            'image.max'        => 'The photo must be 5 MB or smaller.',
            'ends_at.after'    => 'The end must be after the start.',
        ]));

        unset($data['image']);
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['button_label'] = $data['button_label'] ?: 'Shop now';

        return $data;
    }
}
