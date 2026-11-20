@extends('layouts.public')
@section('title', $guide['title'].' | Spacekind planning guide')
@section('description', $guide['description'])
@section('og-image', url('/assets/interiors/'.$guide['image'].'.webp'))
@section('content')
<article class="container sk-guide">
<nav class="breadcrumbs" aria-label="Breadcrumb"><a href="/#guides">Project guides</a><x-icon name="right"/><span>{{ $guide['label'] }}</span></nav>
<header class="guide-hero"><div><div class="eyebrow section-kicker">SPACEKIND EDITORIAL · PLANNING GUIDE</div><h1>{{ $guide['title'] }}.</h1><p>{{ $guide['intro'] }}</p><a class="text-link" href="#checklist">Go to the checklist <x-icon name="right"/></a></div><img src="/assets/interiors/{{ $guide['image'] }}.webp" alt="{{ $guide['alt'] }}" fetchpriority="high" width="800" height="560"></header>
<section class="guide-example"><div class="eyebrow section-kicker">AT YOUR NEXT MEETING</div><h2>{{ $guide['example_title'] }}</h2><p>{{ $guide['example'] }}</p><p><strong>{{ $guide['takeaway'] }}</strong></p></section>
<div class="guide-columns"><section id="questions" class="guide-answers"><div class="eyebrow section-kicker">FIELD NOTES</div><h2>Questions to ask</h2>@foreach($guide['answers'] as $question => $answer)<h3>{{ $question }}</h3><p>{{ $answer }}</p>@endforeach</section>
<aside class="guide-check" id="checklist"><div class="eyebrow section-kicker">TAKE TO YOUR MEETING</div><h2>Your checklist</h2><p>Tick what you’ve checked. Download your list to keep the open questions for your meeting.</p>@foreach($guide['checklist'] as $item)<label><input type="checkbox"> <span>{{ $item }}</span></label>@endforeach<button type="button" hidden data-download-guide class="button outline">Download my checklist (.txt)</button> <a href="/#studios" class="button dark">Find a studio <x-icon name="arrow"/></a></aside></div>
<nav class="guide-next" aria-label="Related planning guides"><strong>Read next</strong>@foreach($guides as $key=>$next)@if($key!==$slug)<a class="text-link" href="/guides/{{ $key }}">{{ $next['title'] }} <x-icon name="right"/></a>@endif @endforeach</nav></article>
<script src="/assets/guide-checklist.js" defer></script>
@endsection
