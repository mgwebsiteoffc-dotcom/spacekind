@extends('layouts.workspace')
@section('title','Labour statement · '.$worker->name)
@section('workspace-label','Worker statement')
@section('content')
<div class="page-heading"><div><div class="eyebrow section-kicker">LABOUR STATEMENT</div><h1>{{ $worker->name }}</h1><p>{{ $worker->trade }} · Attendance earnings and recorded payments.</p></div><a class="button outline" href="{{ route('worker.ledger', $worker) }}">Back to ledger</a></div>
<section class="app-panel form-panel"><h2>Statement period</h2>
<form class="worker-statement-filters" method="get" action="{{ route('worker.statement', $worker) }}">
<label class="field"><span>From</span><input name="from" type="date" value="{{ $filters['from'] }}" max="{{ today()->toDateString() }}" required></label>
<label class="field"><span>Through (inclusive)</span><input name="through" type="date" value="{{ $filters['through'] }}" max="{{ today()->toDateString() }}" required></label>
<label class="field"><span>Project</span><select name="project_id"><option value="">All projects</option><option value="0" @selected($projectId === 0)>Unassigned payments</option>@foreach($projects as $id=>$name)<option value="{{ $id }}" @selected($projectId === (int)$id)>{{ $name }}</option>@endforeach</select></label>
<button class="button dark">Apply filters</button></form></section>
<section class="app-panel form-panel"><div class="eyebrow section-kicker">{{ $filters['from'] }} — {{ $filters['through'] }} · {{ $scope }}</div>
<h2>{{ $closing < 0 ? 'Paid ahead' : 'Outstanding' }} · {{ \App\Support\Money::format(abs($closing)) }}</h2>
<p>Opening balance: <strong>{{ \App\Support\Money::format($opening) }}</strong> · Earned: <strong>{{ \App\Support\Money::format($totals['earned']) }}</strong> · Advances: <strong>{{ \App\Support\Money::format($totals['advance']) }}</strong> · Wages paid: <strong>{{ \App\Support\Money::format($totals['wage']) }}</strong></p>
<a class="button outline" href="{{ route('worker.statement.csv', ['worker'=>$worker] + $filters) }}">Download this statement (CSV)</a>
<h3>Daily activity</h3>
@if($days)<div class="data-table-wrap" tabindex="0" role="region" aria-label="Daily worker balances"><table class="data-table"><thead><tr><th>Date</th><th>Earned</th><th>Advances</th><th>Wages paid</th><th>Balance</th></tr></thead><tbody>
@foreach($days as $day=>$amounts)<tr><th scope="row">{{ $day }}</th>@foreach(['earned','advance','wage','balance'] as $column)<td>{{ \App\Support\Money::format($amounts[$column]) }}</td>@endforeach</tr>@endforeach
</tbody></table></div>@else<p>No earnings or payments in this period. The opening balance carries forward.</p>@endif
<p class="fine-print">Balances use the latest attendance corrections and exclude reversed payments. A negative balance means the worker was paid ahead. On split-site days the first site receives any odd paise. Unassigned payments count toward the worker total, not an individual project.</p></section>
@endsection
