@props(['business'])
@if($business->project_highlights)
<section class="profile-content-section studio-track-record" aria-label="Completed project highlights"><div class="eyebrow section-kicker">SELECTED WORK</div><h2>Completed projects.</h2><p class="fine-print">Project summaries supplied by the studio.</p>
@foreach($business->project_highlights as $project)<article><small>Completed {{ $project['year'] }}</small><h3>{{ $project['title'] }}</h3><p>{{ $project['scope'] }}</p></article>@endforeach</section>
@endif
@if($business->awards)
<section class="profile-content-section studio-track-record" aria-label="Awards and recognition"><div class="eyebrow section-kicker">RECOGNITION</div><h2>Awards &amp; recognition.</h2>
@foreach($business->awards as $award)<article><small>{{ $award['year'] }} · {{ $award['issuer'] }}</small><h3>{{ $award['title'] }}</h3>@if(!empty($award['url']))<a class="text-link" href="{{ $award['url'] }}" target="_blank" rel="noopener noreferrer nofollow">Award announcement <x-icon name="arrow"/></a>@endif</article>@endforeach</section>
@endif
