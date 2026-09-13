<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attraction;
use App\Models\AttractionImage;
use App\Models\State;
use App\Services\CloudinaryImageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\UploadedFile;
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
        unset($data['images']);
        DB::transaction(function () use ($data, $request): void {
            $attraction = Attraction::create($data);
            $this->storeImages($request, $attraction);
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('messages.admin_attraction_added'),
                'redirect' => route('admin.attractions.index'),
            ]);
        }

        return redirect()->route('admin.attractions.index')->with('success', __('messages.admin_attraction_added'));
    }

    public function edit(Attraction $attraction)
    {
        $attraction->load('images');

        return view('admin.attractions.form', ['attraction' => $attraction, 'states' => State::orderBy('state_name')->get()]);
    }

    public function update(Request $request, Attraction $attraction)
    {
        $data = $this->validated($request, $attraction);
        unset($data['images']);
        DB::transaction(function () use ($data, $request, $attraction): void {
            $attraction->update($data);
            $this->storeImages($request, $attraction);
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('messages.admin_attraction_updated'),
                'redirect' => route('admin.attractions.index'),
            ]);
        }

        return redirect()->route('admin.attractions.index')->with('success', __('messages.admin_attraction_updated'));
    }

    public function destroy(Request $request, Attraction $attraction)
    {
        $images = $attraction->images()->pluck('image_path')->push($attraction->image_path)->filter()->unique();
        $attraction->delete();
        $images->each(fn (string $image) => $this->deleteLocalImage($image));

        if ($request->expectsJson()) {
            return response()->json(['message' => __('messages.admin_attraction_deleted')]);
        }

        return back()->with('success', __('messages.admin_attraction_deleted'));
    }

    public function destroyImage(Attraction $attraction, AttractionImage $image)
    {
        abort_unless($image->attraction_id === $attraction->attraction_id, 404);

        $path = $image->image_path;
        $image->delete();
        $this->deleteLocalImage($path);

        if ($attraction->image_path === $path) {
            $attraction->update(['image_path' => $attraction->images()->value('image_path')]);
        }

        if (request()->expectsJson()) {
            return response()->json(['message' => __('admin_images.deleted')]);
        }

        return back()->with('success', __('admin_images.deleted'));
    }

    private function validated(Request $request, ?Attraction $attraction = null): array
    {
        return $request->validate([
            'place_id' => ['required', 'string', 'max:255', 'unique:attractions,place_id'.($attraction ? ','.$attraction->attraction_id.',attraction_id' : '')],
            'state_id' => ['required', 'integer', 'exists:states,state_id'],
            'attraction_name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'location' => ['required', 'string', 'max:255'],
            'operating_hours' => ['nullable', 'string'],
            'entrance_fee' => ['required', 'string', 'max:100'],
            'budget_level' => ['required', 'string', 'max:20'],
            'nearby_transport' => ['nullable', 'string', 'max:100'],
            'rating' => ['nullable', 'numeric', 'between:0,5'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'max:3072'],
        ]);
    }

    private function storeImages(Request $request, Attraction $attraction): void
    {
        foreach ($request->file('images', []) as $file) {
            $path = $this->storeImage($file);
            $attraction->images()->create(['image_path' => $path]);

            if (! $attraction->image_path) {
                $attraction->update(['image_path' => $path]);
            }
        }
    }

    private function storeImage(UploadedFile $file): string
    {
        if (app()->environment('production') || config('services.cloudinary.cloud_name')) {
            try {
                return app(CloudinaryImageService::class)->upload($file->getPathname());
            } catch (\RuntimeException $exception) {
                report($exception);
                throw ValidationException::withMessages([
                    'images' => 'Photo upload failed. Please try again later. Your place changes were not saved.',
                ]);
            }
        }

        $name = Str::uuid().'.'.$file->extension();
        $file->move(public_path('attraction_images'), $name);

        return '/attraction_images/'.$name;
    }

    private function deleteLocalImage(string $path): void
    {
        if (str_starts_with($path, '/attraction_images/')) {
            File::delete(public_path(ltrim($path, '/')));
        }
    }
}
