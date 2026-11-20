<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use App\Services\AttendanceSheet;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    private const DATE = ['date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:today'];

    public function index(Request $request)
    {
        $data = $request->validate(['date' => ['sometimes', 'required', ...self::DATE], 'page' => 'sometimes|integer|min:1']);
        $date = $data['date'] ?? today()->toDateString();
        $workers = Worker::orderBy('name')->orderBy('id')->paginate(20)->appends(['date' => $date]);
        $day = WorkerAttendance::where('work_date', $date);

        return view('workspace.attendance', [
            'date' => $date, 'workers' => $workers, 'projects' => Project::orderBy('name')->orderBy('id')->get(['id', 'name']),
            'earned' => (int) (clone $day)->sum('earned_paise'),
            'entries' => $day->whereIn('worker_id', $workers->getCollection()->modelKeys())->get()->keyBy('worker_id'),
        ]);
    }

    public function store(Request $request, AttendanceSheet $sheet)
    {
        $data = $request->validate([
            'date' => ['required', ...self::DATE], 'page' => 'required|integer|min:1', 'rows' => 'required|array|min:1|max:20',
            'rows.*' => 'required|array:revision,first_project_id,second_project_id,absent,note',
            'rows.*.revision' => 'required|integer|min:0', 'rows.*.absent' => 'sometimes|boolean',
            'rows.*.first_project_id' => 'nullable|integer|min:1', 'rows.*.second_project_id' => 'nullable|integer|min:1',
            'rows.*.note' => 'nullable|string|max:500',
        ]);
        $sheet->save($data['date'], $data['rows']);

        return redirect()->route('attendance', ['date' => $data['date'], 'page' => $data['page']])->with('success', 'Attendance saved.');
    }
}
