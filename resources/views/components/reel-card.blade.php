@props(['reel'])
<article class="reel-card">
    <div class="reel-card-player">
        <video controls tabindex="0" playsinline preload="none" poster="{{ $reel->cover_url }}" aria-label="{{ $reel->title }}" data-reel-video>
            <source src="{{ $reel->video_url }}" type="{{ $reel->mime }}">
            <a href="{{ $reel->public_url }}">Open the video page</a>.
        </video>
        <span class="reel-kind">{{ $reel->kind_label }}</span>
        <button type="button" hidden class="reel-play" aria-label="Play {{ $reel->title }}"><span aria-hidden="true">▶</span> Play film <small>{{ gmdate('i:s', (int) ceil($reel->duration)) }}</small></button>
    </div>
    <div class="reel-card-copy">
        <span class="eyebrow">{{ $reel->business->name }} · {{ $reel->category }}</span>
        <h3><a href="{{ $reel->public_url }}{{ \App\Support\CampaignLabels::query(request()) }}">{{ $reel->title }} <x-icon name="arrow"/></a></h3>
    </div>
</article>
