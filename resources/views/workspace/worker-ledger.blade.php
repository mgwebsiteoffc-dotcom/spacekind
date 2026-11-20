@extends('layouts.workspace')
@section('title','Payments & balance · '.$worker->name)
@section('workspace-label','Worker ledger')
@section('content')
<div class="page-heading"><div><div class="eyebrow section-kicker">WORKER LEDGER · {{ $worker->trade }}</div><h1>{{ $worker->name }}</h1><p>Payments and attendance earnings across all projects.</p></div><a class="button outline" href="/workspace/labour">Worker register</a></div>
<section class="app-panel form-panel"><div class="eyebrow section-kicker">ALL-TIME BALANCE</div>
<h2>{{ $balance < 0 ? 'Paid ahead' : 'Outstanding' }} · {{ \App\Support\Money::format(abs($balance)) }}</h2>
<p>Attendance earnings: <strong>{{ \App\Support\Money::format($earned) }}</strong> · Advances: <strong>{{ \App\Support\Money::format($advances) }}</strong> · Wages paid: <strong>{{ \App\Support\Money::format($wages) }}</strong></p>
<p class="fine-print">Each payment is deducted once. Reversed entries are excluded. A payment ahead of earnings carries forward as credit.</p></section>
<div class="detail-columns"><section class="app-panel form-panel"><h2>Record a payment</h2><p>Enter money already paid to this worker. Saving does not transfer funds.</p>
<form method="post" action="{{ route('worker.ledger', $worker) }}">@csrf
<input type="hidden" name="entry_key" value="{{ old('entry_key', (string) Str::uuid()) }}">
<label class="field"><span>Entry type</span><select name="kind" required><option value="">Choose type</option>@foreach(\App\Models\WorkerPayment::KINDS as $value=>$label)<option value="{{ $value }}" @selected(old('kind')===$value)>{{ $label }}</option>@endforeach</select></label>
<label class="field"><span>Amount (₹)</span><input name="amount" type="number" min="0.01" max="9999999.99" step="0.01" required value="{{ old('amount') }}"></label>
<label class="field"><span>Date paid</span><input type="date" name="paid_on" max="{{ today()->toDateString() }}" required value="{{ old('paid_on', today()->toDateString()) }}"></label>
<label class="field"><span>Payment method</span><select name="method" required><option value="">Choose method</option>@foreach(\App\Models\WorkerPayment::METHODS as $value=>$label)<option value="{{ $value }}" @selected(old('method')===$value)>{{ $label }}</option>@endforeach</select></label>
<label class="field"><span>Project (optional)</span><select name="project_id"><option value="">Not assigned to a project</option>@foreach($projects as $id=>$name)<option value="{{ $id }}" @selected(old('project_id')==$id)>{{ $name }}</option>@endforeach</select></label>
<label class="field"><span>Transaction reference (optional)</span><input name="reference" maxlength="100" value="{{ old('reference') }}"></label>
<label class="field"><span>Note (optional)</span><textarea name="note" maxlength="500" rows="2">{{ old('note') }}</textarea></label>
<button class="button dark">Record payment</button></form></section>
<section class="app-panel form-panel worker-payment-history"><h2>Payment history</h2>
@forelse($payments as $entry)<article><h3>{{ \App\Models\WorkerPayment::KINDS[$entry->kind] }} · {{ \App\Support\Money::format($entry->amount_paise) }}</h3>
<p>{{ $entry->paid_on }} · {{ \App\Models\WorkerPayment::METHODS[$entry->method] }} · {{ $projects->get($entry->project_id, 'No project assigned') }}</p>
@if($entry->reference !== null)<p>Reference: {{ $entry->reference }}</p>@endif @if($entry->note !== null)<p>{{ $entry->note }}</p>@endif
@if($entry->reversed_at)<p><strong>Reversed {{ $entry->reversed_at->toDateString() }}:</strong> {{ $entry->reversal_reason }}</p>
@else<details><summary>Reverse this entry</summary><form method="post" action="{{ route('worker.ledger.reverse', [$worker,$entry->id]) }}">@csrf
<label class="field"><span>Reason for reversal</span><input name="reason" minlength="5" maxlength="500" required></label><p class="fine-print">Keeps this entry in the history but removes it from paid totals. This is not a refund.</p><button class="button outline">Confirm reversal</button></form></details>@endif</article><hr>
@empty<p>No payments recorded for this worker.</p>@endforelse<x-pagination :items="$payments"/></section></div>
@endsection
