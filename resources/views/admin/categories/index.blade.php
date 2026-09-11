<x-admin-layout title="Category Management" active="categories">

@if (session('success'))
    <div class="mb-5 rounded-xl border p-3 text-sm" style="background:rgba(250,78,28,.10);border-color:rgba(250,78,28,.35);color:#d93d0e;">
        ✓ {{ session('success') }}
    </div>
@endif
@if (session('error'))
    <div class="mb-5 rounded-xl border p-3 text-sm" style="background:#FEF2F2;border-color:rgba(220,38,38,.2);color:#DC2626;">
        ✕ {{ session('error') }}
    </div>
@endif

<div class="grid gap-6 lg:grid-cols-[1fr_340px]">

    {{-- Category list --}}
    <div class="card overflow-hidden">
        <table class="w-full text-sm">
            <thead style="background:#e8f0f6;">
                <tr>
                    <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Category</th>
                    <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Description</th>
                    <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Books</th>
                    <th class="px-5 py-3 text-right text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $cat)
                    <tr style="border-top:1px solid #dce8f0;"
                        onmouseover="this.style.background='#e8f0f6';"
                        onmouseout="this.style.background='';">
                        <td class="px-5 py-3 font-semibold" style="color:#222222;">{{ $cat->name }}</td>
                        <td class="px-3 py-3 text-xs max-w-xs truncate" style="color:#6b90aa;">{{ $cat->description ?? '—' }}</td>
                        <td class="px-3 py-3">
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                  style="background:rgba(250,78,28,.12);color:#fa4e1c;">
                                {{ $cat->books_count }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right">
                            {{-- Inline edit trigger --}}
                            <button type="button"
                                    onclick="document.getElementById('edit-{{ $cat->id }}').classList.toggle('hidden')"
                                    class="text-xs font-semibold mr-3 transition" style="color:#fa4e1c;"
                                    onmouseover="this.style.color='#d93d0e';" onmouseout="this.style.color='#fa4e1c';">
                                Edit
                            </button>
                            <form action="{{ route('admin.categories.destroy', $cat->id) }}" method="POST" class="inline"
                                  onsubmit="return confirm('Delete \'{{ addslashes($cat->name) }}\'? This cannot be undone.')">
                                @csrf @method('DELETE')
                                <button class="text-xs font-semibold transition" style="color:#DC2626;"
                                        onmouseover="this.style.color='#B91C1C';" onmouseout="this.style.color='#DC2626';">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                    {{-- Inline edit row --}}
                    <tr id="edit-{{ $cat->id }}" class="hidden" style="background:#e8f0f6;">
                        <td colspan="4" class="px-5 py-4">
                            <form action="{{ route('admin.categories.update', $cat->id) }}" method="POST"
                                  class="flex flex-wrap items-end gap-3">
                                @csrf @method('PUT')
                                <div class="flex-1 min-w-40">
                                    <label class="text-[11px] font-semibold block mb-1" style="color:#6b90aa;">Name</label>
                                    <input type="text" name="name" value="{{ $cat->name }}" class="input py-1.5 text-sm" required>
                                </div>
                                <div class="flex-1 min-w-40">
                                    <label class="text-[11px] font-semibold block mb-1" style="color:#6b90aa;">Description</label>
                                    <input type="text" name="description" value="{{ $cat->description }}" class="input py-1.5 text-sm" placeholder="Optional">
                                </div>
                                <button type="submit" class="btn-gold !py-1.5 !px-4 text-sm" style="background:#fa4e1c;border-color:#fa4e1c;color:#FFFFFF;">Save</button>
                                <button type="button"
                                        onclick="document.getElementById('edit-{{ $cat->id }}').classList.add('hidden')"
                                        class="btn-outline !py-1.5 !px-4 text-sm" style="border-color:#fa4e1c;color:#fa4e1c;">Cancel</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-10 text-center text-sm" style="color:#6b90aa;">No categories yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Add category --}}
    <div class="card h-fit p-6">
        <h2 class="font-display text-lg font-semibold mb-4" style="color:#222222;">Add Category</h2>
        <form action="{{ route('admin.categories.store') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="text-xs font-semibold" style="color:#6b90aa;">Name *</label>
                <input type="text" name="name" value="{{ old('name') }}" class="input mt-1" required placeholder="e.g. Fantasy" style="border-color:#FFDCC2;">
                @error('name')
                    <p class="mt-1 text-xs" style="color:#DC2626;">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="text-xs font-semibold" style="color:#6b90aa;">Description</label>
                <textarea name="description" rows="2" class="input mt-1" placeholder="Optional" style="border-color:#FFDCC2;">{{ old('description') }}</textarea>
            </div>
            <button type="submit" class="btn-gold w-full" style="background:#fa4e1c;border-color:#fa4e1c;color:#FFFFFF;">Add Category</button>
        </form>
    </div>
</div>

</x-admin-layout>