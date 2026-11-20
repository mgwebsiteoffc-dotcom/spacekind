<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Worker;
use App\Models\WorkerPayment;
use App\Services\WorkerLedger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkerLedgerController extends Controller
{
    public function show(Worker $worker, WorkerLedger $ledger)
    {
        return view('workspace.worker-ledger', $ledger->overview($worker) + [
            'worker' => $worker, 'projects' => Project::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request, Worker $worker, WorkerLedger $ledger)
    {
        $data = $request->validate([
            'entry_key' => 'required|uuid', 'kind' => ['required', Rule::in(array_keys(WorkerPayment::KINDS))],
            'amount' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,7}(?:\.\d{1,2})?$/D'],
            'paid_on' => 'required|date_format:Y-m-d|after_or_equal:1000-01-01|before_or_equal:today',
            'method' => ['required', Rule::in(array_keys(WorkerPayment::METHODS))], 'project_id' => 'nullable|integer|min:1',
            'reference' => 'nullable|string|max:100', 'note' => 'nullable|string|max:500',
        ]);
        $ledger->record($worker, $data);

        return redirect()->route('worker.ledger', $worker)->with('success', 'Payment entry recorded.');
    }

    public function reverse(Request $request, Worker $worker, int $payment, WorkerLedger $ledger)
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:500']);
        $ledger->reverse($worker, $payment, $data['reason']);

        return redirect()->route('worker.ledger', $worker)->with('success', 'Entry reversed. No money has been moved.');
    }
}
