@extends('layouts.workspace')
@section('title','Worker register')
@section('workspace-label','Labour management')
@section('content')
<div class="page-heading"><div><div class="eyebrow section-kicker">LABOUR MANAGEMENT</div><h1>Worker register.</h1><p>Record each worker’s trade and agreed daily rate. A new rate starts on its effective date; earlier entries stay on file.</p></div><a class="button dark" href="{{ route('attendance') }}">Mark attendance</a></div>
<div class="detail-columns">
<section class="app-panel form-panel" id="add-worker"><h2>Add a worker</h2>
<form method="post" action="/workspace/labour">@csrf
<label class="field"><span>Worker name</span><input name="name" required maxlength="100" value="{{ old('name') }}"></label>
<label class="field"><span>Trade</span><input name="trade" required maxlength="80" value="{{ old('trade') }}"></label>
@include('workspace.worker-rate-fields',['initial'=>true])
<button class="button dark">Add worker</button></form></section>
<section class="app-panel form-panel"><div class="eyebrow section-kicker">{{ $workers->total() }} {{ Str::plural('WORKER',$workers->total()) }}</div><h2>Your team on site</h2>
@forelse($workers as $worker)
<article><h3>{{ $worker->name }}</h3><p>{{ $worker->trade }}</p>
@php($current = $worker->rates->firstWhere('effective_from', '<=', today()->toDateString()))
<p>{{ $current ? \App\Support\Money::format($current->daily_paise).' / day' : 'No rate effective yet' }}</p>
<p><a class="text-link" href="{{ route('worker.ledger', $worker) }}">Payments &amp; balance <x-icon name="right"/></a></p>
<details><summary>Rate history & new rate</summary><ul>@foreach($worker->rates as $rate)<li>{{ $rate->effective_from }} · {{ \App\Support\Money::format($rate->daily_paise) }} / day</li>@endforeach</ul>
<form action="/workspace/labour/{{ $worker->id }}/rates" method="post">@csrf
@include('workspace.worker-rate-fields',['initial'=>false])
<button class="button outline">Add dated rate</button></form></details></article><hr>
@empty<p>No workers yet. Add the first worker and their starting rate.</p>@endforelse
<x-pagination :items="$workers"/></section></div>
@endsection
