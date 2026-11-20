@extends('layouts.workspace')
@section('title','Site attendance')
@section('workspace-label','Site attendance')
@section('content')
<div class="page-heading"><div><div class="eyebrow section-kicker">LABOUR MANAGEMENT</div><h1>Site attendance.</h1><p>Choose a project for each half worked. Two halves make one full day, even when the worker changes sites.</p></div>
<a class="button outline" href="/workspace/labour">Worker register</a></div>
<section class="app-panel form-panel attendance-summary"><form method="get" action="{{ route('attendance') }}">
<label class="field"><span>Work date</span><input type="date" name="date" value="{{ $date }}" max="{{ today()->toDateString() }}" required></label><button class="button outline">Open date</button></form>
<p><strong>{{ \App\Support\Money::format($earned) }}</strong> earned across all workers on {{ $date }}. This is not a payment record.</p></section>
@if($workers->isNotEmpty())
@if($projects->isEmpty())<div class="notice subtle">No projects yet. Add a project through the client workflow before recording time worked. Absences can still be recorded.</div>@endif
<form method="post" action="{{ route('attendance') }}">@csrf
<input type="hidden" name="date" value="{{ $date }}"><input type="hidden" name="page" value="{{ $workers->currentPage() }}">
@foreach($workers as $worker)
@php
    $entry = $entries->get($worker->id);
    $key = 'rows.'.$worker->id;
    $retry = old('date') === $date && is_array(old($key));
@endphp
<fieldset class="app-panel form-panel attendance-row"><legend>{{ $worker->name }} · {{ $worker->trade }}</legend>
<p>Saved: <strong>{{ $entry?->label() ?? 'Not marked' }}</strong> · {{ \App\Support\Money::format($entry?->earned_paise ?? 0) }} earned</p>
<input type="hidden" name="rows[{{ $worker->id }}][revision]" value="{{ $retry ? old($key.'.revision') : ($entry?->revision ?? 0) }}">
<div class="attendance-halves">
@foreach(['first_project_id'=>'First half','second_project_id'=>'Second half'] as $column=>$label)
<label class="field"><span>{{ $label }}</span><select name="rows[{{ $worker->id }}][{{ $column }}]"><option value="">Not worked</option>
@foreach($projects as $project)<option value="{{ $project->id }}" @selected(($retry ? old($key.'.'.$column) : $entry?->$column) == $project->id)>{{ $project->name }}</option>@endforeach</select></label>
@endforeach
</div>
<label class="attendance-absence"><input type="checkbox" name="rows[{{ $worker->id }}][absent]" value="1" @checked($retry ? old($key.'.absent', false) : $entry?->absent)> Absent all day</label>
<label class="field"><span>Site work note (optional)</span><textarea name="rows[{{ $worker->id }}][note]" rows="2" maxlength="500">{{ $retry ? old($key.'.note') : $entry?->note }}</textarea></label></fieldset>
@endforeach
<p class="fine-print">To clear an entry, leave both halves unworked, untick absence and remove the note. Save before changing dates or pages.</p><button class="button dark">Save attendance · {{ $date }}</button>
<a class="text-link" href="{{ route('attendance',['date'=>$date,'page'=>$workers->currentPage()]) }}">Reload saved sheet</a></form>
@else<div class="app-panel form-panel"><p>No workers on this page.</p><a class="text-link" href="/workspace/labour">Open worker register</a></div>@endif
<x-pagination :items="$workers"/>
@endsection
