<x-admin-layout title="Platform Settings" active="settings">

@if (session('success'))
    <div class="mb-5 rounded-xl border p-4 text-sm" style="background:rgba(250,78,28,.10);border-color:rgba(250,78,28,.35);color:#d93d0e;">
        ✓ {{ session('success') }}
    </div>
@endif
@if ($errors->any())
    <div class="mb-5 rounded-xl border p-4 text-sm" style="background:#FEF2F2;border-color:rgba(220,38,38,.2);color:#DC2626;">
        <ul class="list-inside list-disc space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

{{-- Tabs --}}
<div class="mb-6 flex gap-1 border-b" style="border-color:#cfdce8;" id="settingsTabs">
    <button type="button" class="settings-tab -mb-px border-b-2 px-5 py-3 text-sm font-bold transition"
            data-tab="announcements"
            style="border-color:#fa4e1c;color:#fa4e1c;">
        📢 Announcements
    </button>
    <button type="button" class="settings-tab -mb-px border-b-2 px-5 py-3 text-sm font-bold transition"
            data-tab="policies"
            style="border-color:transparent;color:#6b90aa;">
        📄 Policies
    </button>
</div>

{{-- ═══════════════════════════════
     ANNOUNCEMENTS TAB
═══════════════════════════════ --}}
<div id="tab-announcements" class="tab-content">
    <div class="grid gap-6 lg:grid-cols-[1fr_380px]">

        {{-- Existing announcements --}}
        <div class="space-y-4">
            <h2 class="font-display text-base font-bold" style="color:#222222;">
                Active & Past Announcements ({{ $announcements->count() }})
            </h2>
            @forelse ($announcements as $ann)
                @php $tc = $ann->typeColor(); @endphp
                <div class="card overflow-hidden">
                    <div class="flex items-start gap-3 px-5 py-4"
                         style="background:{{ $tc['bg'] }};border-left:4px solid {{ $tc['border'] }};">
                        <span class="text-xl flex-shrink-0 mt-0.5">{{ $tc['icon'] }}</span>
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-bold text-sm" style="color:{{ $tc['text'] }};">{{ $ann->title }}</p>
                                @if (! $ann->is_active)
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                                          style="background:#F3F4F6;color:#6B7280;">Inactive</span>
                                @endif
                                @if ($ann->isExpired())
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                                          style="background:#FEF2F2;color:#DC2626;">Expired</span>
                                @endif
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                      style="background:rgba(0,0,0,.06);color:#555;">
                                    {{ ucfirst($ann->audience) }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm leading-relaxed" style="color:#555555;">{{ $ann->body }}</p>
                            <p class="mt-2 text-[11px]" style="color:#6b90aa;">
                                Posted by {{ $ann->admin->name ?? 'Admin' }} · {{ $ann->published_at?->format('M d, Y H:i') ?? $ann->created_at->format('M d, Y H:i') }}
                                @if ($ann->expires_at)
                                    · Expires {{ $ann->expires_at->format('M d, Y') }}
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 px-5 py-2.5" style="background:#FAFAFA;border-top:1px solid {{ $tc['border'] }}50;">
                        <form action="{{ route('admin.settings.announcements.toggle', $ann->id) }}" method="POST" class="inline">
                            @csrf @method('PATCH')
                            <button class="text-xs font-semibold transition"
                                    style="color:{{ $ann->is_active ? '#D97706' : '#059669' }};">
                                {{ $ann->is_active ? '⏸ Deactivate' : '▶ Activate' }}
                            </button>
                        </form>
                        <form action="{{ route('admin.settings.announcements.destroy', $ann->id) }}" method="POST" class="inline"
                              onsubmit="return confirm('Delete this announcement?')">
                            @csrf @method('DELETE')
                            <button class="text-xs font-semibold" style="color:#DC2626;">🗑 Delete</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="card flex flex-col items-center gap-3 py-12 text-center">
                    <span class="text-4xl">📢</span>
                    <p class="text-sm" style="color:#6b90aa;">No announcements posted yet.</p>
                </div>
            @endforelse
        </div>

        {{-- Post new announcement --}}
        <div class="card h-fit p-6">
            <h2 class="font-display text-base font-bold mb-4" style="color:#222222;">Post New Announcement</h2>
            <form action="{{ route('admin.settings.announcements.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Title *</label>
                    <input type="text" name="title" value="{{ old('title') }}" class="input mt-1" required
                           placeholder="e.g. Platform Maintenance Notice">
                </div>
                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Message *</label>
                    <textarea name="body" rows="4" class="input mt-1" required maxlength="5000"
                              placeholder="Write your announcement here…">{{ old('body') }}</textarea>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-semibold" style="color:#6b90aa;">Audience *</label>
                        <select name="audience" class="input mt-1 text-sm" required>
                            <option value="all"     @selected(old('audience')===''||old('audience')==='all')>All Users</option>
                            <option value="buyers"  @selected(old('audience')==='buyers')>Buyers Only</option>
                            <option value="sellers" @selected(old('audience')==='sellers')>Sellers Only</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold" style="color:#6b90aa;">Type *</label>
                        <select name="type" class="input mt-1 text-sm" required>
                            <option value="info"        @selected(old('type')==='info'||!old('type'))>ℹ️ Info</option>
                            <option value="warning"     @selected(old('type')==='warning')>⚠️ Warning</option>
                            <option value="maintenance" @selected(old('type')==='maintenance')>🔧 Maintenance</option>
                            <option value="promo"       @selected(old('type')==='promo')>🎉 Promotion</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Expires On (optional)</label>
                    <input type="datetime-local" name="expires_at" value="{{ old('expires_at') }}" class="input mt-1">
                    <p class="text-[11px] mt-1" style="color:#B0B0B0;">Leave blank for no expiry.</p>
                </div>
                <button type="submit" class="w-full rounded-xl py-3 text-sm font-bold text-white transition hover:opacity-90"
                        style="background:#002b4d;">
                    📢 Post Announcement
                </button>
            </form>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════
     POLICIES TAB
═══════════════════════════════ --}}
<div id="tab-policies" class="tab-content hidden">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach (\App\Models\PlatformPolicy::defaultPolicies() as $p)
            @php $existing = $policies[$p['key']] ?? null; @endphp
            <a href="{{ route('admin.settings.policies.show', $p['key']) }}"
               class="card flex flex-col gap-3 p-6 transition hover:-translate-y-0.5">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl text-lg"
                          style="background:#e8f0f6;">📄</span>
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-sm" style="color:#222222;">{{ $p['title'] }}</p>
                        @if ($existing)
                            <p class="text-xs mt-0.5" style="color:#6b90aa;">
                                Updated {{ $existing->updated_at->diffForHumans() }}
                            </p>
                        @else
                            <p class="text-xs mt-0.5" style="color:#DC2626;">Not yet written</p>
                        @endif
                    </div>
                </div>
                <div class="flex items-center justify-between">
                    @if ($existing)
                        <span class="rounded-full px-2.5 py-0.5 text-[10px] font-bold"
                              style="background:#ECFDF5;color:#059669;">Published</span>
                    @else
                        <span class="rounded-full px-2.5 py-0.5 text-[10px] font-bold"
                              style="background:#FEF2F2;color:#DC2626;">Draft</span>
                    @endif
                    <span class="text-xs font-semibold" style="color:#fa4e1c;">Edit →</span>
                </div>
            </a>
        @endforeach
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tabs    = document.querySelectorAll('.settings-tab');
    const contents = document.querySelectorAll('.tab-content');

    function switchTab(name) {
        tabs.forEach(t => {
            const active = t.dataset.tab === name;
            t.style.borderColor = active ? '#fa4e1c' : 'transparent';
            t.style.color       = active ? '#fa4e1c' : '#6b90aa';
        });
        contents.forEach(c => c.classList.toggle('hidden', c.id !== 'tab-' + name));
    }

    tabs.forEach(tab => tab.addEventListener('click', () => switchTab(tab.dataset.tab)));

    // Open the correct tab on page load (e.g. after saving)
    const hash = window.location.hash.replace('#', '') || 'announcements';
    switchTab(hash);
    tabs.forEach(t => t.addEventListener('click', () => {
        history.replaceState(null, '', '#' + t.dataset.tab);
    }));
});
</script>

</x-admin-layout>
