<x-admin-layout title="Edit Policy" active="settings">
@include('partials.panel.plain')

@if (session('success'))
    <div class="mb-5 rounded-md border px-4 py-3 text-sm" style="background:#ECFDF5;border-color:#A7F3D0;color:#047857;">
        {{ session('success') }}
    </div>
@endif

<div class="mb-4 flex flex-wrap items-center justify-between gap-2">
    <a href="{{ route('admin.settings.index') }}#policies" class="text-sm hover:underline pl-muted">&larr; Settings</a>
    @if ($policy)
        <p class="text-xs pl-muted">
            Last updated {{ $policy->updated_at->format('M d, Y H:i') }}
            by {{ $policy->editor->name ?? 'Admin' }}
        </p>
    @endif
</div>

<div class="pl-card">
    <form action="{{ route('admin.settings.policies.update', $policyKey) }}" method="POST" class="pl-body">
        @csrf @method('PUT')

        <div class="mb-5">
            <label class="pl-label">Policy title *</label>
            <input type="text" name="title"
                   value="{{ old('title', $policy?->title ?? $defaultTitle) }}"
                   class="input font-semibold" required>
        </div>

        <div class="mb-5">
            <label class="pl-label">Content *</label>
            <p class="mb-2 text-xs pl-muted">
                You can use plain text or basic HTML (headings, paragraphs, lists).
            </p>
            <textarea id="policy-editor" name="content" rows="24"
                      class="input font-mono text-sm leading-relaxed"
                      required
                      placeholder="Write the policy content here…">{{ old('content', $policy?->content ?? '') }}</textarea>
        </div>

        {{-- Preview --}}
        <div class="mb-6">
            <button type="button" id="toggle-preview"
                    class="pl-link"
                    onclick="togglePreview()">
                Preview rendered output
            </button>
            <div id="preview-box" class="hidden mt-3 rounded-md border p-5 text-sm leading-relaxed"
                 style="border-color:#ECE7E3;background:#FCFBFA;color:#44403C;">
            </div>
        </div>

        <div class="flex justify-end gap-2 border-t pt-4" style="border-color:#F2EEEA;">
            <a href="{{ route('admin.settings.index') }}#policies" class="pl-btn-line">Cancel</a>
            <button type="submit" class="pl-btn">Save policy</button>
        </div>
    </form>
</div>

<script src="{{ asset('js/admin-policy.js') }}"></script>

</x-admin-layout>
