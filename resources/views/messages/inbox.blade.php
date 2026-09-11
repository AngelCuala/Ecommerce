<x-layout title="Messages — ALVY">
<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">

    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="font-display text-2xl font-bold" style="color:#222222;">Messages</h1>
            @if ($unreadCount > 0)
                <p class="text-sm mt-0.5" style="color:#fa4e1c;">{{ $unreadCount }} unread message{{ $unreadCount !== 1 ? 's' : '' }}</p>
            @endif
        </div>

        {{-- Compose button --}}
        @if (count($composeTargets))
            <button id="compose-btn"
                    class="flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold text-white transition hover:opacity-90"
                    style="background:#002b4d;">
                ✏️ New Message
            </button>
        @endif
    </div>

    {{-- New Message Modal --}}
    @if (count($composeTargets))
        <div id="compose-modal" class="fixed inset-0 z-50 hidden items-center justify-center"
             style="background:rgba(0,0,0,.45);">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl mx-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-display text-lg font-bold" style="color:#222222;">New Message</h3>
                    <button id="compose-close" class="text-xl leading-none" style="color:#999;">✕</button>
                </div>
                <form action="{{ route('messages.new') }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="text-xs font-semibold" style="color:#6b90aa;">Send to *</label>
                        <select name="receiver_id" class="input mt-1" required>
                            <option value="">— Select recipient —</option>
                            @foreach ($composeTargets as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit"
                            class="w-full rounded-xl py-2.5 text-sm font-bold text-white transition hover:opacity-90"
                            style="background:#002b4d;">Open Conversation →</button>
                </form>
            </div>
        </div>
    @endif

    {{-- Thread list --}}
    @forelse ($threads as $thread)
        <a href="{{ $thread['route'] }}"
           class="flex items-center gap-4 rounded-2xl border bg-white p-4 mb-3 transition hover:shadow-md hover:-translate-y-0.5"
           style="border-color:{{ $thread['unread'] > 0 ? '#cfdce8' : '#EFEFEF' }};
                  background:{{ $thread['unread'] > 0 ? '#FFFBF7' : '#fff' }};">

            {{-- Avatar --}}
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full text-sm font-bold"
                 style="background:{{ $thread['type'] === 'order' ? '#fa4e1c' : '#6366F1' }};color:#fff;">
                {{ $thread['type'] === 'order' ? '📦' : strtoupper(substr($thread['label'], 0, 1)) }}
            </div>

            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    <p class="font-bold text-sm truncate" style="color:#222222;">{{ $thread['label'] }}</p>
                    @if ($thread['type'] === 'direct')
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                              style="background:#EEF2FF;color:#6366F1;">Direct</span>
                    @endif
                </div>
                <p class="text-xs truncate mt-0.5" style="color:#6b90aa;">
                    @if ($thread['last_body'])
                        <span style="color:#666;">{{ $thread['last_name'] }}:</span>
                        {{ Str::limit($thread['last_body'], 60) }}
                    @else
                        {{ $thread['sublabel'] }}
                    @endif
                </p>
            </div>

            <div class="flex flex-col items-end gap-1.5 flex-shrink-0">
                @if ($thread['last_at'])
                    <p class="text-[11px]" style="color:#BBBBBB;">{{ $thread['last_at']->diffForHumans() }}</p>
                @endif
                @if ($thread['unread'] > 0)
                    <span class="flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-bold"
                          style="background:#fa4e1c;color:#fff;">{{ $thread['unread'] }}</span>
                @endif
            </div>
        </a>
    @empty
        <div class="flex flex-col items-center gap-4 rounded-2xl border py-16 text-center"
             style="border-color:#EFEFEF;background:#fff;">
            <span class="text-5xl">💬</span>
            <div>
                <p class="font-display text-lg font-bold" style="color:#222222;">No messages yet</p>
                <p class="text-sm mt-1" style="color:#999999;">Messages about your orders will appear here.</p>
            </div>
        </div>
    @endforelse
</div>

<script>
    var btn   = document.getElementById('compose-btn');
    var modal = document.getElementById('compose-modal');
    var close = document.getElementById('compose-close');
    if (btn && modal) {
        btn.addEventListener('click', function () { modal.classList.remove('hidden'); modal.classList.add('flex'); });
        close.addEventListener('click', function () { modal.classList.add('hidden'); modal.classList.remove('flex'); });
        modal.addEventListener('click', function (e) {
            if (e.target === modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); }
        });
    }
</script>
</x-layout>
