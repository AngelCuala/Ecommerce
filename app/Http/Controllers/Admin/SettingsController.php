<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\PlatformPolicy;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    // ── Main settings page ───────────────────────────────────
    public function index()
    {
        $announcements = Announcement::with('admin')->latest()->get();
        $policies      = PlatformPolicy::with('editor')->get()->keyBy('key');

        return view('admin.settings.index', compact('announcements', 'policies'));
    }

    // ── Announcements ────────────────────────────────────────
    public function storeAnnouncement(Request $request)
    {
        $data = $request->validate([
            'title'      => 'required|string|max:255',
            'body'       => 'required|string|max:5000',
            'audience'   => 'required|in:all,buyers,sellers',
            'type'       => 'required|in:info,warning,maintenance,promo',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $data['admin_id']     = auth()->id();
        $data['published_at'] = now();
        $data['is_active']    = true;

        Announcement::create($data);

        return back()->with('success', 'Announcement posted successfully.');
    }

    public function toggleAnnouncement(int $id)
    {
        $ann = Announcement::findOrFail($id);
        $ann->update(['is_active' => ! $ann->is_active]);

        return back()->with('success',
            'Announcement ' . ($ann->is_active ? 'activated' : 'deactivated') . '.');
    }

    public function destroyAnnouncement(int $id)
    {
        Announcement::findOrFail($id)->delete();
        return back()->with('success', 'Announcement deleted.');
    }

    // ── Policies ─────────────────────────────────────────────
    public function updatePolicy(Request $request, string $key)
    {
        $data = $request->validate([
            'title'   => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        PlatformPolicy::upsertPolicy($key, $data['title'], $data['content'], auth()->id());

        return back()->with('success', '"' . $data['title'] . '" policy updated.');
    }

    public function showPolicy(string $key)
    {
        $policy    = PlatformPolicy::where('key', $key)->first();
        $policyKey = $key;

        // Default title if policy doesn't exist yet
        $defaults = collect(PlatformPolicy::defaultPolicies())->keyBy('key');
        $defaultTitle = $defaults[$key]['title'] ?? ucwords(str_replace('_', ' ', $key));

        return view('admin.settings.policy', compact('policy', 'policyKey', 'defaultTitle'));
    }
}
