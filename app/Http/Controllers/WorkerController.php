<?php

namespace App\Http\Controllers;

use App\Models\Worker;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkerController extends Controller
{
    private const RATE_RULES = [
        'daily_rate' => ['required', 'regex:/^\d{1,6}(?:\.\d{1,2})?$/D', 'numeric', 'gt:0'],
        'effective_from' => ['required', 'date_format:Y-m-d', 'after_or_equal:1000-01-01'],
    ];

    public function index()
    {
        return view('workspace.labour', ['workers' => Worker::with('rates')->orderBy('name')->orderBy('id')->paginate(20)]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'trade' => 'required|string|max:80'] + self::RATE_RULES);
        DB::transaction(function () use ($data) {
            $worker = Worker::create(['name' => $data['name'], 'trade' => $data['trade']]);
            $this->appendRate($worker, $data);
            Audit::record('worker.created', $worker);
        });

        return redirect('/workspace/labour')->with('success', 'Worker added to the register.');
    }

    public function rate(Request $request, Worker $worker)
    {
        $data = $request->validate(self::RATE_RULES);
        DB::transaction(function () use ($worker, $data) {
            $locked = Worker::lockForUpdate()->findOrFail($worker->id);
            if ($locked->rates()->where('effective_from', '>=', $data['effective_from'])->exists()) {
                throw ValidationException::withMessages(['effective_from' => 'Choose a date after the last recorded rate.']);
            }
            $this->appendRate($locked, $data);
        });

        return back()->with('success', 'Daily rate added. Earlier rates have been kept.');
    }

    private function appendRate(Worker $worker, array $data): void
    {
        $rate = $worker->rates()->create(['effective_from' => $data['effective_from'], 'daily_paise' => Money::scaled($data['daily_rate'])]);
        Audit::record('worker.rate_added', $rate);
    }
}
