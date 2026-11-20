@extends('layouts.workspace')
@section('title','Client testimonials')
@section('workspace-label','Testimonials')
@section('content')
<div class="page-heading"><div><div class="eyebrow section-kicker">STUDIO PROFILE</div><h1>Client testimonials.</h1><p>Share feedback in your client’s own words. Only publish the name and quote they have approved.</p></div><a href="/workspace/studio#gallery" class="button outline">Works &amp; inspiration</a></div>
<div class="detail-columns"><section class="app-panel form-panel"><h2>Add feedback</h2><form method="post" action="/workspace/testimonials">@csrf @include('workspace.testimonial-fields',['entry'=>null])<button class="button dark">Add testimonial</button></form></section>
<section class="app-panel form-panel"><h2>Your testimonials</h2>@forelse($quotes as $entry)<details><summary>{{ $entry->attribution }} · {{ $entry->is_visible?'Visible on approved profile':'Hidden' }}</summary>
<form method="post" action="/workspace/testimonials/{{ $entry->id }}">@csrf @method('PATCH') @include('workspace.testimonial-fields')<button class="button dark">Save changes</button></form>
<form method="post" action="/workspace/testimonials/{{ $entry->id }}" data-confirm="Remove this testimonial?"><br>@csrf @method('DELETE')<button class="button outline">Delete testimonial</button></form></details><hr>@empty<p>No client feedback added yet.</p>@endforelse<x-pagination :items="$quotes"/></section></div>
@endsection
