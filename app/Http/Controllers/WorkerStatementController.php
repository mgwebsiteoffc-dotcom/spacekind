<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Worker;
use App\Services\WorkerStatement;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class WorkerStatementController extends Controller
{
    public function __invoke(Request $request, Worker $worker, WorkerStatement $statement)
    {
        $today = today();
        $input = $request->only(['from', 'through', 'project_id']) + [
            'from' => $today->copy()->startOfMonth()->toDateString(), 'through' => $today->toDateString(),
        ];
        $date = ['required', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:today'];
        $filters = Validator::make($input, [
            'from' => $date, 'through' => [...$date, 'after_or_equal:from'], 'project_id' => 'nullable|integer|min:0',
        ])->validate();
        if (Carbon::parse($filters['from'])->diffInDays(Carbon::parse($filters['through'])) > 365) {
            throw ValidationException::withMessages(['through' => 'Choose a range of up to 366 days, including the first and last days.']);
        }
        $projectId = isset($filters['project_id']) ? (int) $filters['project_id'] : null;
        $scope = match ($projectId) {
            null => 'All projects', WorkerStatement::UNASSIGNED => 'Unassigned payments', default => Project::findOrFail($projectId)->name,
        };
        $report = $statement->build($worker, $filters['from'], $filters['through'], $projectId);
        if ($request->routeIs('worker.statement.csv')) {
            Audit::record('worker.statement_exported', $worker, $filters);

            return $this->csv($worker, $scope, $filters, $report);
        }

        return view('workspace.worker-statement', $report + compact('worker', 'filters', 'scope', 'projectId') + [
            'projects' => Project::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    private function csv(Worker $worker, string $scope, array $filters, array $report)
    {
        // Resolve the report inside the authorised request, before middleware releases the tenant.
        $text = static fn (string $s) => preg_match('/^[\p{Z}\p{C}\s]*[=+@-]/u', $s) ? "'".$s : $s;
        $context = [$text($worker->name), $text($scope), $filters['from'], $filters['through']];

        return response()->streamDownload(function () use ($context, $filters, $report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $write = static fn (array $row) => fputcsv($out, $row, ',', '"', '');
            $write(['Worker', 'Project filter', 'From', 'Through', 'Date', 'Row', 'Earned INR', 'Advances INR', 'Wages paid INR', 'Balance INR']);
            $movements = static fn (array $row) => [Money::decimal($row['earned']), Money::decimal($row['advance']), Money::decimal($row['wage'])];
            $write([...$context, $filters['from'], 'Opening', '', '', '', Money::decimal($report['opening'])]);
            foreach ($report['days'] as $date => $row) {
                $write([...$context, $date, 'Activity', ...$movements($row), Money::decimal($row['balance'])]);
            }
            $write([...$context, $filters['through'], 'Closing / period totals', ...$movements($report['totals']), Money::decimal($report['closing'])]);
            fclose($out);
        }, "worker-{$worker->id}-{$filters['from']}-{$filters['through']}.csv", ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private']);
    }
}
