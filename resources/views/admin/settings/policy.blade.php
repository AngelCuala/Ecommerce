<x-admin-layout title="Edit Policy" active="settings">

@if (session('success'))
    <div class="mb-5 rounded-xl border p-4 text-sm" style="background:rgba(250,78,28,.10);border-color:rgba(250,78,28,.35);color:#d93d0e;">
        ✓ {{ session('success') }}
    </div>
@endif

<div class="mb-5 flex items-center justify-between">
    <a href="{{ route('admin.settings.index') }}#policies"
       class="text-sm font-semibold transition" style="color:#fa4e1c;"
       onmouseover="this.style.color='#d93d0e';" onmouseout="this.style.color='#fa4e1c';">
        ← Back to Settings
    </a>
    @if ($policy)
        <div class="text-xs" style="color:#6b90aa;">
            Last updated {{ $policy->updated_at->format('M d, Y H:i') }}
            by {{ $policy->editor->name ?? 'Admin' }}
        </div>
    @endif
</div>

<div class="card p-6">
    <form action="{{ route('admin.settings.policies.update', $policyKey) }}" method="POST">
        @csrf @method('PUT')

        <div class="mb-5">
            <label class="text-xs font-semibold" style="color:#6b90aa;">Policy Title *</label>
            <input type="text" name="title"
                   value="{{ old('title', $policy?->title ?? $defaultTitle) }}"
                   class="input mt-1 text-lg font-bold" required>
        </div>

        <div class="mb-5">
            <label class="text-xs font-semibold" style="color:#6b90aa;">Content *</label>
            <p class="text-[11px] mt-0.5 mb-2" style="color:#B0B0B0;">
                You can use plain text or basic HTML (headings, paragraphs, lists).
            </p>
            <textarea id="policy-editor" name="content" rows="24"
                      class="input mt-1 font-mono text-sm leading-relaxed"
                      required
                      placeholder="Write the policy content here…">{{ old('content', $policy?->content ?? '') }}</textarea>
        </div>

        {{-- Preview --}}
        <div class="mb-6">
            <button type="button" id="toggle-preview"
                    class="text-xs font-semibold transition" style="color:#fa4e1c;"
                    onclick="togglePreview()">
                👁 Preview Rendered Output
            </button>
            <div id="preview-box" class="hidden mt-3 rounded-xl border p-6 text-sm leading-relaxed"
                 style="border-color:#cfdce8;background:#FFFBF7;color:#333;">
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit"
                    class="flex-1 rounded-xl py-3 text-sm font-bold text-white transition hover:opacity-90"
                    style="background:#002b4d;">
                💾 Save Policy
            </button>
            <a href="{{ route('admin.settings.index') }}#policies"
               class="rounded-xl border px-6 py-3 text-sm font-semibold transition"
               style="border-color:#cfdce8;color:#6b90aa;">Cancel</a>
        </div>
    </form>
</div>

<script>
function togglePreview() {
    const box    = document.getElementById('preview-box');
    const editor = document.getElementById('policy-editor');
    const btn    = document.getElementById('toggle-preview');
    if (box.classList.toggle('hidden')) {
        btn.textContent = '👁 Preview Rendered Output';
    } else {
        box.innerHTML = editor.value;
        btn.textContent = '✕ Hide Preview';
    }
}
</script>

</x-admin-layout>
