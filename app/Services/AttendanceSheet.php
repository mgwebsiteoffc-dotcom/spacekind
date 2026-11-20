<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceSheet
{
    public function save(string $date, array $rows): void
    {
        DB::transaction(function () use ($date, $rows) {
            // Rate changes also lock the worker. Always acquire locks in the same order.
            $ids = array_keys($rows);
            foreach ($ids as $id) {
                abort_unless(is_int($id) && $id > 0, 422);
            }
            $workers = Worker::whereKey($ids)->orderBy('id')->lockForUpdate()->get();
            abort_unless($workers->count() === count($ids), 404);
            $sites = collect($rows)->flatMap(fn ($row) => [$row['first_project_id'] ?? null, $row['second_project_id'] ?? null])->filter()->unique();
            abort_unless(Project::whereKey($sites->values()->all())->count() === $sites->count(), 404);

            foreach ($workers as $worker) {
                $this->saveRow($worker, $date, $rows[$worker->id]);
            }
        }, 3);
    }

    private function saveRow(Worker $worker, string $date, array $row): void
    {
        $entry = WorkerAttendance::firstOrNew(['worker_id' => $worker->id, 'work_date' => $date]);
        $fields = ['first_project_id', 'second_project_id', 'absent', 'note', 'daily_paise', 'earned_paise'];
        $before = $entry->exists ? $entry->only($fields) : null;
        $first = (int) ($row['first_project_id'] ?? 0) ?: null;
        $second = (int) ($row['second_project_id'] ?? 0) ?: null;
        $absent = (bool) ($row['absent'] ?? false);
        $note = $row['note'] ?? null;
        $halves = (int) ($first !== null) + (int) ($second !== null);
        $error = fn (string $message) => ValidationException::withMessages(["rows.{$worker->id}" => "{$worker->name}: {$message}"]);
        if ($absent && $halves) {
            throw $error('clear both projects before marking an absence.');
        }
        if (! $absent && ! $halves && $note !== null) {
            throw $error('mark attendance before adding a work note.');
        }
        $entry->fill(['first_project_id' => $first, 'second_project_id' => $second, 'absent' => $absent, 'note' => $note]);
        // Identical retries are harmless, including a never-marked row on a bulk sheet.
        if (($entry->exists && ! $entry->isDirty()) || (! $entry->exists && ! $absent && ! $halves)) {
            return;
        }
        if ((int) $row['revision'] !== (int) $entry->revision) {
            throw $error('this entry changed since you opened it. Reload the saved sheet before correcting it.');
        }
        if ($halves && $entry->daily_paise === null) {
            $entry->daily_paise = $worker->rates()->where('effective_from', '<=', $date)->value('daily_paise');
            if ($entry->daily_paise === null) {
                throw $error('no daily rate is effective on this date.');
            }
        }
        // Round once per worker/day. Splitting a full day across projects never adds a second wage.
        $entry->earned_paise = intdiv((int) $entry->daily_paise * $halves + 1, 2);
        $entry->revision = (int) $entry->revision + 1;
        $entry->save();
        Audit::record('attendance.saved', $entry, ['date' => $date, 'revision' => $entry->revision, 'before' => $before, 'after' => $entry->only($fields)]);
    }
}
