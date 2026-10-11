<x-admin-layout title="Platform Settings" active="settings">
@include('partials.panel.plain')
<style>
    .settings-tab { margin-right: 1.25rem; padding: .6rem .25rem; font-size: .875rem; font-weight: 600; white-space: nowrap; transition: color .15s; }
    /* admin-settings.js sets the active tab's colour inline; the orange underline marks it */
    .settings-tab[style*="transparent"]:hover { color: #222 !important; }
</style>
@php
    $typeLabels = ['info' => 'Info', 'warning' => 'Warning', 'maintenance' => 'Maintenance', 'promo' => 'Promotion'];
@endphp

@if (session('success'))
    <div class="mb-5 rounded-md border px-4 py-3 text-sm" style="background:#ECFDF5;border-color:#A7F3D0;color:#047857;">
        {{ session('success') }}
    </div>
@endif
@if ($errors->any())
    <div class="mb-5 rounded-md border px-4 py-3 text-sm" style="background:#FEF2F2;border-color:#FECACA;color:#B91C1C;">
        <ul class="list-inside list-disc space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

{{-- Tabs --}}
<div class="mb-5 flex border-b" style="border-color:#ECE7E3;" id="settingsTabs">
    <button type="button" class="settings-tab -mb-px border-b-2"
            data-tab="announcements"
            style="border-color:#fa4e1c;color:#222;">
        Announcements
    </button>
    <button type="button" class="settings-tab -mb-px border-b-2"
            data-tab="policies"
            style="border-color:transparent;color:#8a7f78;">
        Policies
    </button>
</div>

{{-- Announcements tab --}}
<div id="tab-announcements" class="tab-content">
    <div class="grid gap-5 lg:grid-cols-[1fr_22rem]">

        {{-- Existing announcements --}}
        <div class="space-y-3">
            <h2 class="text-sm font-semibold" style="color:#222;">
                Active &amp; past announcements <span class="pl-muted font-normal">({{ $announcements->count() }})</span>
            </h2>
            @forelse ($announcements as $ann)
                <div class="pl-card">
                    <div class="pl-body">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-semibold" style="color:#222;">{{ $ann->title }}</p>
                            @if ($ann->isExpired())
                                <span class="pl-tag cancelled">Expired</span>
                            @elseif ($ann->is_active)
                                <span class="pl-tag delivered">Active</span>
                            @endif
                            @if (! $ann->is_active)
                                <span class="pl-tag neutral">Inactive</span>
                            @endif
                            <span class="pl-tag neutral">{{ ucfirst($ann->audience) }}</span>
                            <span class="pl-tag neutral">{{ $typeLabels[$ann->type] ?? ucfirst($ann->type) }}</span>
                        </div>
                        <p class="mt-1.5 text-sm leading-relaxed" style="color:#44403C;">{{ $ann->body }}</p>
                        <p class="mt-2 text-xs pl-muted">
                            Posted by {{ $ann->admin->name ?? 'Admin' }} · {{ $ann->published_at?->format('M d, Y H:i') ?? $ann->created_at->format('M d, Y H:i') }}
                            @if ($ann->expires_at)
                                · Expires {{ $ann->expires_at->format('M d, Y') }}
                            @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-4 border-t px-5 py-2.5" style="border-color:#F2EEEA;">
                        <form action="{{ route('admin.settings.announcements.toggle', $ann->id) }}" method="POST" class="inline">
                            @csrf @method('PATCH')
                            <button class="text-sm font-medium hover:underline" style="color:#44403C;">
                                {{ $ann->is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                        </form>
                        <form action="{{ route('admin.settings.announcements.destroy', $ann->id) }}" method="POST" class="inline"
                              onsubmit="return confirm('Delete this announcement?')">
                            @csrf @method('DELETE')
                            <button class="text-sm font-medium hover:underline" style="color:#B91C1C;">Delete</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="pl-card px-5 py-10 text-center">
                    <p class="text-sm pl-muted">No announcements posted yet.</p>
                </div>
            @endforelse
        </div>

        {{-- Post new announcement --}}
        <section class="pl-card h-fit">
            <div class="pl-head"><h2>Post new announcement</h2></div>
            <form action="{{ route('admin.settings.announcements.store') }}" method="POST" class="pl-body space-y-4">
                @csrf
                <div>
                    <label class="pl-label">Title *</label>
                    <input type="text" name="title" value="{{ old('title') }}" class="input" required
                           placeholder="e.g. Platform Maintenance Notice">
                </div>
                <div>
                    <label class="pl-label">Message *</label>
                    <textarea name="body" rows="4" class="input" required maxlength="5000"
                              placeholder="Write your announcement here…">{{ old('body') }}</textarea>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="pl-label">Audience *</label>
                        <select name="audience" class="input text-sm" required>
                            <option value="all"     @selected(old('audience')===''||old('audience')==='all')>All Users</option>
                            <option value="buyers"  @selected(old('audience')==='buyers')>Buyers Only</option>
                            <option value="sellers" @selected(old('audience')==='sellers')>Sellers Only</option>
                        </select>
                    </div>
                    <div>
                        <label class="pl-label">Type *</label>
                        <select name="type" class="input text-sm" required>
                            <option value="info"        @selected(old('type')==='info'||!old('type'))>Info</option>
                            <option value="warning"     @selected(old('type')==='warning')>Warning</option>
                            <option value="maintenance" @selected(old('type')==='maintenance')>Maintenance</option>
                            <option value="promo"       @selected(old('type')==='promo')>Promotion</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="pl-label">Expires on (optional)</label>
                    <input type="datetime-local" name="expires_at" value="{{ old('expires_at') }}" class="input">
                    <p class="pl-note">Leave blank for no expiry.</p>
                </div>
                <button type="submit" class="pl-btn w-full">Post announcement</button>
            </form>
        </section>
    </div>
</div>

{{-- Policies tab --}}
<div id="tab-policies" class="tab-content hidden">
    <div class="pl-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="pl-table">
                <thead>
                    <tr><th>Policy</th><th>Last updated</th><th>Status</th><th class="!text-right">Action</th></tr>
                </thead>
                <tbody>
                    @foreach (\App\Models\PlatformPolicy::defaultPolicies() as $p)
                        @php $existing = $policies[$p['key']] ?? null; @endphp
                        <tr>
                            <td class="font-medium" style="color:#222;">{{ $p['title'] }}</td>
                            <td>
                                @if ($existing)
                                    <span class="pl-muted">Updated {{ $existing->updated_at->diffForHumans() }}</span>
                                @else
                                    <span style="color:#B91C1C;">Not yet written</span>
                                @endif
                            </td>
                            <td>
                                @if ($existing)
                                    <span class="pl-tag delivered">Published</span>
                                @else
                                    <span class="pl-tag neutral">Draft</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('admin.settings.policies.show', $p['key']) }}" class="pl-link">Edit</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="{{ asset('js/admin-settings.js') }}"></script>

</x-admin-layout>
