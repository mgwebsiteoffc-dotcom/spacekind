<?php

namespace App\Http\Controllers;

use App\Models\Testimonial;
use App\Support\Audit;
use Illuminate\Http\Request;

class TestimonialController extends Controller
{
    public function index()
    {
        return view('workspace.testimonials', ['quotes' => Testimonial::latest('id')->paginate(10)]);
    }

    public function store(Request $request)
    {
        return $this->save($request, new Testimonial);
    }

    public function update(Request $request, Testimonial $testimonial)
    {
        return $this->save($request, $testimonial);
    }

    private function save(Request $request, Testimonial $testimonial)
    {
        $data = $request->validate(['attribution' => 'required|string|max:100', 'quote' => 'required|string|max:1000', 'permission' => 'accepted', 'is_visible' => 'nullable|boolean', 'show_on_home' => 'nullable|boolean']);
        $testimonial->fill(['attribution' => $data['attribution'], 'quote' => $data['quote'], 'is_visible' => $request->boolean('is_visible'), 'show_on_home' => $request->boolean('is_visible') && $request->boolean('show_on_home'), 'permission_confirmed_at' => now()])->save();
        Audit::record('testimonial.saved', $testimonial, ['visible' => $testimonial->is_visible]);

        return back()->with('success', 'Testimonial saved.');
    }

    public function destroy(Testimonial $testimonial)
    {
        Audit::record('testimonial.removed', $testimonial);
        $testimonial->delete();

        return back()->with('success', 'Testimonial removed.');
    }
}
