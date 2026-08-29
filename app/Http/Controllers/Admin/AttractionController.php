<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attraction;
use App\Models\State;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class AttractionController extends Controller
{
    public function index(Request $request)
    {
        $query = Attraction::with('state')->withCount('savedByUsers');
        if ($request->filled('search')) {
            $search = $request->string('search')->trim();
            $query->where(fn ($q) => $q->where('attraction_name', 'like', "%{$search}%")->orWhere('location', 'like', "%{$search}%"));
        }
        $attractions = $query->latest()->paginate(10)->withQueryString();

        return view('admin.attractions.index', compact('attractions'));
    }

    public function create()
    {
        return view('admin.attractions.form', ['attraction' => new Attraction(), 'states' => State::orderBy('state_name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['image_path'] = $this->storeImage($request);
        Attraction::create($data);

        return redirect()->route('admin.attractions.index')->with('success', 'Attraction added successfully.');
    }

    public function edit(Attraction $attraction)
    {
        return view('admin.attractions.form', ['attraction' => $attraction, 'states' => State::orderBy('state_name')->get()]);
    }

    public function update(Request $request, Attraction $attraction)
    {
        $data = $this->validated($request, $attraction);
        if ($request->hasFile('image')) {
            $oldImage = $attraction->image_path;
            $data['image_path'] = $this->storeImage($request);
            if ($oldImage && str_starts_with($oldImage, '/attraction_images/')) {
                File::delete(public_path(ltrim($oldImage, '/')));
            }
        }
        $attraction->update($data);

        return redirect()->route('admin.attractions.index')->with('success', 'Attraction updated successfully.');
    }

    public function destroy(Attraction $attraction)
    {
        $image = $attraction->image_path;
        $attraction->delete();
        if ($image && str_starts_with($image, '/attraction_images/')) {
            File::delete(public_path(ltrim($image, '/')));
        }

        return back()->with('success', 'Attraction deleted successfully.');
    }

    private function validated(Request $request, ?Attraction $attraction = null): array
    {
        return $request->validate([
            'place_id' => ['required', 'string', 'max:255', 'unique:attractions,place_id'.($attraction ? ','.$attraction->attraction_id.',attraction_id' : '')],
            'state_id' => ['required', 'integer', 'exists:states,state_id'],
            'attraction_name' => ['required', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['required', 'string', 'max:255'],
            'operating_hours' => ['nullable', 'string'],
            'entrance_fee' => ['required', 'string', 'max:100'],
            'budget_level' => ['required', 'string', 'max:20'],
            'nearby_transport' => ['nullable', 'string', 'max:100'],
            'rating' => ['nullable', 'numeric', 'between:0,5'],
            'image' => ['nullable', 'image', 'max:3072'],
        ]);
    }

    private function storeImage(Request $request): ?string
    {
        if (! $request->hasFile('image')) return null;
        $file = $request->file('image');
        $name = Str::uuid().'.'.$file->extension();
        $file->move(public_path('attraction_images'), $name);

        return '/attraction_images/'.$name;
    }
}
