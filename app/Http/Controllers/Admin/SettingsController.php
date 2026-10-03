<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\PlatformPolicy;
use App\Models\User;
use App\Models\UserNotification;
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

        $announcement = Announcement::create($data);

        \App\Models\ActivityLog::record(
            'announcement_posted',
            'Announcement Posted',
            'Posted announcement "' . $announcement->title . '" to ' . $announcement->audience . '.',
            $announcement
        );

        // Fan the announcement out to every targeted user's notifications.
        $recipients = $this->notifyRecipients($announcement);

        return back()->with('success',
            'Announcement posted and sent to ' . $recipients . ' user' . ($recipients === 1 ? '' : 's') . '.');
    }

    /**
     * Create a notification for every user in the announcement's audience.
     * Returns the number of users notified.
     */
    private function notifyRecipients(Announcement $announcement): int
    {
        // Map the announcement audience to the relevant user roles.
        $roles = match ($announcement->audience) {
            'buyers'  => ['buyer'],
            'sellers' => ['seller'],
            default   => ['buyer', 'seller', 'sorting_center', 'courier'], // "all" (admins excluded)
        };

        $now  = now();
        $rows = [];

        User::whereIn('role', $roles)
            ->where('id', '!=', auth()->id())
            ->select('id')
            ->chunk(500, function ($users) use (&$rows, $announcement, $now) {
                foreach ($users as $u) {
                    $rows[] = [
                        'user_id'    => $u->id,
                        'title'      => $announcement->title,
                        'body'       => $announcement->body,
                        'type'       => $announcement->type,
                        'link'       => null,
                        'read_at'    => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            });

        foreach (array_chunk($rows, 500) as $batch) {
            UserNotification::insert($batch);
        }

        return count($rows);
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

        \App\Models\ActivityLog::record(
            'policy_updated',
            'Policy Updated',
            'Updated platform policy: "' . $data['title'] . '".'
        );

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
