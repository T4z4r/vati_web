@php
    $photoSize = (int) ($size ?? 72);
    $photoName = trim(collect([$member->first_name, $member->middle_name, $member->last_name])->filter()->implode(' '));
    $photoInitials = strtoupper(substr((string) $member->first_name, 0, 1).substr((string) $member->last_name, 0, 1));
@endphp

@if($member->photo_path)
    <img data-member-photo src="{{ asset('storage/'.$member->photo_path) }}" alt="{{ $photoName }} photograph"
        style="width:{{ $photoSize }}px;height:{{ $photoSize }}px;border-radius:14px;object-fit:cover;border:2px solid rgba(22,69,42,.18);flex:0 0 auto">
@else
    <div data-member-photo-fallback title="{{ $photoName }}" aria-label="{{ $photoName }} has no uploaded photograph"
        style="width:{{ $photoSize }}px;height:{{ $photoSize }}px;border-radius:14px;display:flex;align-items:center;justify-content:center;background:#e4f0e8;color:#16452a;font-size:{{ max(14, (int) round($photoSize * .28)) }}px;font-weight:800;flex:0 0 auto">
        {{ $photoInitials ?: '—' }}
    </div>
@endif
