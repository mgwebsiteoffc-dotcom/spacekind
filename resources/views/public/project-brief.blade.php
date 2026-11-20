@extends('layouts.public')
@section('title','Project concierge · Spacekind')
@section('content')
<section class="container concierge-page">@if(session('brief_received'))<h1>Brief received.</h1><p>Your project details are with the Spacekind team.</p><a class="button dark" href="/#studios">Explore studios</a>@else<div class="eyebrow">SPACEKIND PROJECT CONCIERGE</div><h1>{{ $s['heading'] }}</h1><x-alerts/><x-platform-brief-form/>@endif</section>
@endsection
