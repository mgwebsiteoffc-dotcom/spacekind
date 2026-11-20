<?php

namespace App\Services;

use App\Models\Worker;
use App\Models\WorkerAttendance;
use App\Models\WorkerPayment;
use Illuminate\Support\Facades\DB;

/** Current corrected records by work/payment date, not a historical audit snapshot. */
class WorkerStatement
{
    public const UNASSIGNED = 0;

    public function build(Worker $worker, string $from, string $through, ?int $projectId): array
    {
        return DB::transaction(function () use ($worker, $from, $through, $projectId) {
            $work = WorkerAttendance::where('worker_id', $worker->id)->where('earned_paise', '>', 0)->select('work_date');
            $paid = WorkerPayment::where('worker_id', $worker->id)->whereNull('reversed_at');
            if ($projectId === self::UNASSIGNED) {
                // Worked halves always have a site. Unassigned payments belong to no site.
                $work->whereRaw('1 = 0')->addSelect('earned_paise as allocated');
                $paid->whereNull('project_id');
            } elseif ($projectId !== null) {
                $paid->where('project_id', $projectId);
                $work->where(fn ($q) => $q->where('first_project_id', $projectId)->orWhere('second_project_id', $projectId));
                // A single-site day (including a half-day) gets its entire frozen earnings.
                // Only split a two-site day. The first site gets the odd paise, never both.
                $work->selectRaw('CASE WHEN first_project_id <> second_project_id THEN
                    CASE WHEN first_project_id = ? THEN ROUND(earned_paise / 2.0, 0)
                         ELSE earned_paise - ROUND(earned_paise / 2.0, 0) END
                    ELSE earned_paise END AS allocated', [$projectId]);
            } else {
                $work->addSelect('earned_paise as allocated');
            }

            $earnings = DB::query()->fromSub($work, 'worker_earnings');
            $opening = (int) (clone $earnings)->where('work_date', '<', $from)->sum('allocated')
                - (int) (clone $paid)->where('paid_on', '<', $from)->sum('amount_paise');
            $zero = ['earned' => 0, 'advance' => 0, 'wage' => 0];
            $days = [];
            foreach ($earnings->whereBetween('work_date', [$from, $through])->get() as $row) {
                // Unique worker/work_date in the attendance register.
                $days[$row->work_date] = ['earned' => (int) $row->allocated] + $zero;
            }
            foreach ($paid->whereBetween('paid_on', [$from, $through])->groupBy('paid_on', 'kind')
                ->selectRaw('paid_on, kind, SUM(amount_paise) AS total')->get() as $row) {
                $days[$row->paid_on] ??= $zero;
                $days[$row->paid_on][$row->kind] = (int) $row->total;
            }
            ksort($days);
            $balance = $opening;
            $totals = $zero;
            foreach ($days as $date => $amounts) {
                foreach ($amounts as $kind => $amount) {
                    $totals[$kind] += $amount;
                }
                $balance += $amounts['earned'] - $amounts['advance'] - $amounts['wage'];
                $days[$date]['balance'] = $balance;
            }

            return ['opening' => $opening, 'days' => $days, 'totals' => $totals, 'closing' => $balance];
        });
    }
}
