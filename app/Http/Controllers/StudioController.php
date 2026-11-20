<?php

namespace App\Http\Controllers;

use App\Models\GalleryImage;
use App\Models\User;
use App\Support\Audit;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class StudioController extends Controller
{
    public function index()
    {
        return view('workspace.studio', ['business' => app(Tenant::class)->business(), 'gallery' => GalleryImage::orderBy('position')->orderBy('id')->get()]);
    }

    public function update(Request $r)
    {
        $year = 'integer|between:1800,'.now()->year;
        $v = $r->validate(['name' => 'required|string|max:100', 'city' => ['required', Rule::in(config('spacekind.cities'))], 'locality' => 'required|string|max:120', 'service' => ['required', Rule::in(array_keys(config('spacekind.services')))], 'budget_min' => 'required|integer|between:1,10000', 'budget_max' => 'required|integer|gte:budget_min|between:1,10000', 'bio' => 'required|string|min:30|max:2000', 'tags' => 'nullable|string|max:120', 'seo_title' => 'nullable|string|max:70', 'seo_description' => 'nullable|string|max:170',
            'established_year' => 'nullable|'.$year, 'completed_project_count' => 'nullable|integer|between:0,1000000',
            'project_highlights' => 'sometimes|array|list|max:3', 'project_highlights.*' => 'array:title,year,scope',
            'project_highlights.*.title' => 'nullable|required_with:project_highlights.*.year,project_highlights.*.scope|string|max:120',
            'project_highlights.*.scope' => 'nullable|required_with:project_highlights.*.title|string|max:500',
            'project_highlights.*.year' => 'nullable|required_with:project_highlights.*.title|'.$year,
            'awards' => 'sometimes|array|list|max:3', 'awards.*' => 'array:title,issuer,year,url',
            'awards.*.title' => 'nullable|required_with:awards.*.year,awards.*.issuer,awards.*.url|string|max:120',
            'awards.*.issuer' => 'nullable|required_with:awards.*.title|string|max:120',
            'awards.*.year' => 'nullable|required_with:awards.*.title|'.$year,
            'awards.*.url' => 'nullable|string|url:https|max:500',
        ]);
        foreach (['project_highlights', 'awards'] as $key) {
            if (isset($v[$key])) {
                $v[$key] = array_values(array_filter($v[$key], fn ($row) => filled($row['title'] ?? null)));
            }
        }
        $v['tags'] = collect(explode(',', $v['tags'] ?? ''))->map(fn ($t) => trim($t))->filter()->take(4)->values()->all();
        $business = app(Tenant::class)->business();
        DB::transaction(function () use ($business, $v) {
            $business->fill($v)->forceFill(['approval_status' => 'pending'])->save();
            Audit::record('studio.profile_submitted', $business);
        });

        return back()->with('success', 'Profile saved and sent for review. Your public listing stays hidden until an administrator approves it.');
    }

    public function upload(Request $r)
    {
        $v = $r->validate(['image' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120|dimensions:min_width=300,min_height=200,max_width=6000,max_height=6000', 'caption' => 'required|string|max:180', 'kind' => ['required', Rule::in(['completed', 'render', 'concept', 'inspiration'])], 'rights' => ['accepted']]);
        $path = $r->file('image')->store('gallery/'.app(Tenant::class)->id(), 'local');
        try {
            $image = GalleryImage::create(['path' => $path, 'caption' => $v['caption'], 'kind' => $v['kind'], 'position' => (GalleryImage::max('position') ?? 0) + 1, 'rights_confirmed_at' => now()]);
            Audit::record('gallery.image_uploaded', $image);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        return back()->with('success', $image->kind === 'inspiration' ? 'Image added to studio inspiration.' : 'Portfolio image uploaded.');
    }

    public function updateImage(Request $r, GalleryImage $image)
    {
        $v = $r->validate(['caption' => 'required|string|max:180', 'position' => 'required|integer|between:0,9999', 'is_visible' => 'nullable|boolean']);
        $v['is_visible'] = $r->boolean('is_visible');
        $image->update($v);
        Audit::record('gallery.image_updated', $image);

        return back()->with('success', 'Gallery updated.');
    }

    public function deleteImage(GalleryImage $image)
    {
        $path = $image->path;
        $disk = $image->disk;
        Audit::record('gallery.image_deleted', $image);
        $image->delete();
        if ($disk === 'local') {
            Storage::disk('local')->delete($path);
        }

        return back()->with('success', 'Gallery image removed.');
    }

    public function team()
    {
        return view('workspace.team', ['members' => app(Tenant::class)->business()->users()->orderBy('id')->get()]);
    }

    public function addMember(Request $r)
    {
        $v = $r->validate(['name' => 'required|string|max:100', 'email' => 'required|email:rfc|max:254|unique:users,email', 'password' => ['required', Password::min(10)->letters()->numbers()]]);
        if (app(Tenant::class)->business()->users()->count() >= 10) {
            throw ValidationException::withMessages(['email' => 'This pilot supports up to 10 team accounts. Contact the platform admin for changes.']);
        }
        $user = new User($v);
        $user->business_id = app(Tenant::class)->id();
        $user->role = 'staff';
        $user->save();
        Audit::record('team.member_created', $user);

        return back()->with('success', 'Staff account created. Share the initial password securely; no invitation email was sent.');
    }

    public function toggleMember(Request $r, int $member)
    {
        $user = app(Tenant::class)->business()->users()->where('role', 'staff')->findOrFail($member);
        $user->is_active = ! $user->is_active;
        $user->save();
        if (! $user->is_active) {
            $user->tokens()->delete();
        }Audit::record('team.access_changed', $user, ['active' => $user->is_active]);

        return back()->with('success', 'Staff access updated.');
    }
}
