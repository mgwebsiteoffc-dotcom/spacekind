<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Reel;
use App\Rules\PublicHttpsUrl;
use App\Services\VideoInspector;
use App\Support\Audit;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReelController extends Controller
{
    public const CATEGORIES = ['Living', 'Kitchen', 'Bedroom', 'Workspace', 'Other'];

    public function index()
    {
        return view('workspace.reels', ['reels' => Reel::orderBy('position')->orderByDesc('id')->paginate(8), 'editing' => null, 'categories' => self::CATEGORIES]);
    }

    public function edit(Reel $reel)
    {
        return view('workspace.reels', ['reels' => Reel::orderBy('position')->orderByDesc('id')->paginate(8), 'editing' => $reel, 'categories' => self::CATEGORIES]);
    }

    public function store(Request $r, VideoInspector $inspector)
    {
        $this->save($r, $inspector);

        return redirect('/workspace/reels')->with('success', 'Video uploaded to Spacekind. Only published videos on an approved studio listing are public.');
    }

    public function update(Request $r, Reel $reel, VideoInspector $inspector)
    {
        $this->save($r, $inspector, $reel);

        return redirect('/workspace/reels')->with('success', 'Video details saved. No Instagram account or publishing service is involved.');
    }

    private function save(Request $r, VideoInspector $inspector, ?Reel $reel = null): void
    {
        $v = $r->validate([
            'title' => 'required|string|max:120', 'caption' => 'required|string|min:10|max:1200',
            'kind' => ['required', Rule::in(['completed', 'render', 'concept'])], 'category' => ['required', Rule::in(self::CATEGORIES)],
            'associated_url' => ['required', 'string', 'max:1000', new PublicHttpsUrl],
            'video' => [$reel ? 'nullable' : 'required', 'file', 'mimes:mp4,webm', 'mimetypes:video/mp4,video/webm', 'max:'.config('spacekind.video_max_kb', 20480)],
            'cover' => [$reel ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072', 'dimensions:min_width=240,min_height=200,max_width=6000,max_height=6000'],
            'rights' => 'accepted', 'status' => ['required', Rule::in(['draft', 'published'])], 'position' => 'nullable|integer|between:0,9999',
        ]);
        $metadata = $r->file('video') ? $inspector->inspect($r->file('video')) : [];
        $files = [];
        $old = $reel?->only(['video_path', 'cover_path', 'disk']);
        try {
            $paths = [];
            foreach (['video' => 'video_path', 'cover' => 'cover_path'] as $field => $column) {
                if ($r->file($field)) {
                    $paths[$column] = $files[] = $r->file($field)->store('reels/'.app(Tenant::class)->id(), 'local');
                } elseif ($reel && $reel->disk === 'public-assets' && ($r->file('video') || $r->file('cover'))) {
                    $path = 'reels/'.app(Tenant::class)->id().'/'.Str::uuid().'.'.pathinfo($reel->$column, PATHINFO_EXTENSION);
                    Storage::disk('local')->put($path, file_get_contents(public_path($reel->$column)));
                    $paths[$column] = $files[] = $path;
                }
            }
            DB::transaction(function () use ($v, $metadata, $paths, $reel) {
                Business::whereKey(app(Tenant::class)->id())->lockForUpdate()->firstOrFail();
                $size = (int) ($metadata['bytes'] ?? $reel?->bytes ?? 0);
                if (! $reel && Reel::count() >= config('spacekind.video_count_limit', 10)) {
                    throw ValidationException::withMessages(['video' => 'The local pilot guardrail is 10 videos per studio. This is not a final commercial allowance.']);
                }
                if (Reel::sum('bytes') - (int) ($reel?->bytes ?? 0) + $size > config('spacekind.video_bytes_limit', 104857600)) {
                    throw ValidationException::withMessages(['video' => 'This pilot is limited to 100 MB of uploaded video per studio. Remove an older video first.']);
                }
                $record = $reel ?? new Reel;
                $record->fill(Arr::only($v, ['title', 'caption', 'kind', 'category', 'associated_url', 'status', 'position']));
                if (! $reel && ! isset($v['position'])) {
                    $record->position = (Reel::max('position') ?? 0) + 1;
                }
                $record->forceFill($paths + $metadata + ['rights_confirmed_at' => now()]);
                if ($paths) {
                    $record->forceFill(['disk' => 'local']);
                }
                $record->save();
                Audit::record($reel ? 'reel.updated' : 'reel.uploaded', $record, ['status' => $record->status]);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($files);
            throw $e;
        }
        if (($old['disk'] ?? null) === 'local') {
            foreach (['video_path', 'cover_path'] as $column) {
                if (isset($paths[$column]) && $paths[$column] !== $old[$column]) {
                    Storage::disk('local')->delete($old[$column]);
                }
            }
        }
    }

    public function delete(Reel $reel)
    {
        $paths = [$reel->video_path, $reel->cover_path];
        $disk = $reel->disk;
        DB::transaction(function () use ($reel) {
            Audit::record('reel.deleted', $reel);
            $reel->delete();
        });
        if ($disk === 'local') {
            Storage::disk('local')->delete($paths);
        }

return redirect('/workspace/reels')->with('success', 'Video and its uploaded cover removed. Existing copies downloaded by visitors cannot be recalled.');
    }

    public function adminIndex()
    {
        return view('admin.reels', ['reels' => Reel::withoutGlobalScope('business')->with('business')->latest('id')->paginate(12)]);
    }

    public function moderate(Request $r, int $video)
    {
        $v = $r->validate(['action' => ['required', Rule::in(['block', 'restore'])], 'reason' => 'required|string|min:5|max:500']);
        $reel = Reel::withoutGlobalScope('business')->findOrFail($video);
        // Admin moderation is explicit and does not change tenant ownership or the owner's draft/public selection.
        DB::table('reels')->where('id', $reel->id)->update(['blocked_at' => $v['action'] === 'block' ? now() : null, 'moderation_note' => $v['reason'], 'updated_at' => now()]);
        Audit::record('admin.reel_'.$v['action'], $reel, ['reason' => $v['reason']]);

        return back()->with('success','Video moderation saved. The studio listing and video publication rules still apply.');
    }
}
