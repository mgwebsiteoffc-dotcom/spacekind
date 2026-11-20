@extends('layouts.workspace')
@section('title','A little more room to grow')
@section('workspace-label','Studio tools')
@section('content')
<div class="notice subtle"><x-icon name="wallet"/><span><a href="/workspace/finance"><strong>Quotations, invoices & receipts</strong></a> · <a href="/workspace/finance/payments">Payments & advances</a> · <a href="/workspace/finance/catalogue">Catalogue</a> · <a href="/workspace/finance/settings">Billing setup</a> · <a href="/workspace/billing">Your Spacekind bills</a></span></div><div class="page-heading"><div><div class="eyebrow section-kicker">YOUR STUDIO / A MORE CONNECTED PRESENCE</div><h1>A little more<br> room to grow.</h1><p>Useful tools for the way your studio is discovered, remembered and followed up.</p></div></div><div class="studio-tools-grid">@foreach([
['/workspace/studio','image','Profile & gallery','Your introduction, project range and authorised portfolio images.'],
['/workspace/labour','people','Worker register','Record workers, dated rates and daily attendance across projects.'],
['/workspace/reels','image','Spaces in motion','Upload a short video, choose a cover and add an associated URL.'],
['/workspace/blog','file','Studio journal','Write, preview and submit a useful, clearly attributed article.'],
['/workspace/campaign-links','chart','Campaign links','Build an owned-destination UTM link and see matching-label outcomes.'],
['/workspace/team','people','Team & access','Owner-managed accounts. Public content tools remain owner-only in this milestone.'],
['/workspace/modules','spark','Your plan & roadmap','All five standard modules. ₹4,999/year, delivered in phases.'],
] as $tool)<a class="app-panel studio-tool" href="{{ $tool[0] }}"><x-icon :name="$tool[1]"/><h2>{{ $tool[2] }}</h2><p>{{ $tool[3] }}</p><span class="text-link">Open tool <x-icon name="arrow"/></span></a>@endforeach</div>@endsection
