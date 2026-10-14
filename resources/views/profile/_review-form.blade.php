{{-- Star rating + comment + optional photo. Used for a new review and for its one edit. --}}
<form action="{{ $action }}" method="POST" enctype="multipart/form-data" class="mt-2 space-y-2 text-sm">
    @csrf
    @if ($method !== 'POST') @method($method) @endif
    <div class="flex flex-row-reverse justify-end gap-1" role="radiogroup" aria-label="Rating">
        @for ($star = 5; $star >= 1; $star--)
            <input type="radio" name="rating" value="{{ $star }}" id="r{{ $action }}-{{ $star }}" class="peer sr-only" @checked(($review->rating ?? null) == $star)>
            <label for="r{{ $action }}-{{ $star }}" class="cursor-pointer text-2xl leading-none text-stone-300 hover:text-amber-400 peer-checked:text-amber-400 [&:hover~label]:text-amber-400 peer-checked:[&~label]:text-amber-400" title="{{ $star }} star{{ $star > 1 ? 's' : '' }}">★</label>
        @endfor
    </div>
    <textarea name="comment" rows="2" maxlength="1000" class="pf-input" placeholder="How was the item? e.g. Matibay at maganda!">{{ $review->comment ?? '' }}</textarea>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <input type="file" name="photo" accept="image/jpeg,image/png" class="text-xs" aria-label="Photo (JPG or PNG, up to 5 MB)">
        <button class="pf-btn !py-1.5 px-4 text-sm">{{ $review ? 'Save changes' : 'Submit review' }}</button>
    </div>
</form>
