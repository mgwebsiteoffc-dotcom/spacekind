<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use App\Models\WorkerPayment;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkerLedger
{
    public function overview(Worker $worker): array
    {
        return DB::transaction(function () use ($worker) {
            $earned = (int) WorkerAttendance::where('worker_id', $worker->id)->sum('earned_paise');
            $query = WorkerPayment::where('worker_id', $worker->id);
            $totals = (clone $query)->whereNull('reversed_at')->groupBy('kind')->selectRaw('kind, SUM(amount_paise) AS total')->pluck('total', 'kind');
            $advances = (int) ($totals['advance'] ?? 0);
            $wages = (int) ($totals['wage'] ?? 0);

            return [
                'earned' => $earned, 'advances' => $advances, 'wages' => $wages, 'balance' => $earned - $advances - $wages,
                'payments' => $query->orderByDesc('paid_on')->orderByDesc('id')->paginate(20),
            ];
        });
    }

    public function record(Worker $worker, array $data): void
    {
        DB::transaction(function () use ($worker, $data) {
            Worker::lockForUpdate()->findOrFail($worker->id);
            $project = isset($data['project_id']) ? Project::findOrFail($data['project_id'])->id : null;
            $fields = [
                'project_id' => $project, 'paid_on' => $data['paid_on'], 'kind' => $data['kind'],
                'method' => $data['method'], 'amount_paise' => Money::scaled($data['amount'], 2, 999999999),
                'reference' => $data['reference'] ?? null, 'note' => $data['note'] ?? null,
            ];
            $entry = WorkerPayment::firstOrNew(['worker_id' => $worker->id, 'entry_key' => strtolower($data['entry_key'])]);
            $entry->fill($fields);
            if ($entry->exists) {
                if ($entry->isDirty()) {
                    throw ValidationException::withMessages(['entry_key' => 'This form has already recorded a different payment. Reload the ledger to start a new entry.']);
                }

                return;
            }
            $entry->save();
            Audit::record('worker.payment_recorded', $entry, ['worker_id' => $worker->id, 'kind' => $entry->kind, 'amount_paise' => $entry->amount_paise]);
        }, 3);
    }

    public function reverse(Worker $worker, int $id, string $reason): void
    {
        DB::transaction(function () use ($worker, $id, $reason) {
            Worker::lockForUpdate()->findOrFail($worker->id);
            $entry = WorkerPayment::where('worker_id', $worker->id)->lockForUpdate()->findOrFail($id);
            if ($entry->reversed_at !== null) {
                return;
            }
            $entry->forceFill(['reversed_at' => now(), 'reversal_reason' => $reason])->save();
            Audit::record('worker.payment_reversed', $entry, ['worker_id' => $worker->id, 'reason' => $reason]);
        }, 3);
    }
}
