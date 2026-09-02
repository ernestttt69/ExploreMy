<?php

namespace App\Http\Controllers;

use App\Models\ItineraryItem;
use App\Models\ItineraryShare;
use App\Models\Trip;
use App\Services\EcoAlternativeService;
use App\Services\ItineraryCalendarExporter;
use App\Services\ItineraryPdfExporter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ItineraryController extends Controller
{
    public function __construct(
        private EcoAlternativeService $ecoAlternatives,
        private ItineraryPdfExporter $pdfExporter,
        private ItineraryCalendarExporter $calendarExporter,
    ) {
    }

    /**
     * Display the signed-in user's trip collection.
     */
    public function index(): View
    {
        $trips = Trip::ownedBy((int) auth()->id())
            ->with('items')
            ->latest()
            ->get();

        return view('itineraries.index', compact('trips'));
    }

    /**
     * Create an itinerary shell that can be populated from the trip view.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'destination' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'start_date' => ['nullable', 'date', 'after_or_equal:today'],
            'end_date' => ['nullable', 'date', 'after_or_equal:today', 'after_or_equal:start_date'],
        ]);

        $startDate = isset($data['start_date']) ? Carbon::parse($data['start_date']) : today();
        $endDate = isset($data['end_date']) ? Carbon::parse($data['end_date']) : $startDate->copy();

        $trip = Trip::create(array_merge($data, [
            'user_id' => (int) auth()->id(),
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'days' => $startDate->diffInDays($endDate) + 1,
            'co2_kg' => 0,
            'map_center' => [3.1390, 101.6869],
        ]));

        return redirect()->route('itineraries.show', $trip)
            ->with('status', 'Your new itinerary is ready. Add the first activity to start planning.');
    }

    /**
     * Display a private itinerary dashboard.
     */
    public function show(Trip $trip): View
    {
        $trip = $this->ownedTrip($trip);

        return view('itineraries.show', [
            'trip' => $trip,
            'itinerary' => $this->tripPayload($trip),
            'canEdit' => true,
            'shareToken' => null,
            'sharedPermission' => null,
            'clientConfig' => $this->clientConfig($trip, true),
        ]);
    }

    /**
     * Display an itinerary exposed through a secure share token.
     */
    public function shared(string $token): View
    {
        $share = $this->activeShare($token);
        $share->forceFill(['last_accessed_at' => now()])->save();
        $trip = $share->trip->load('items');

        return view('itineraries.show', [
            'trip' => $trip,
            'itinerary' => $this->tripPayload($trip),
            'canEdit' => false,
            'shareToken' => $token,
            'sharedPermission' => $share->permission,
            'clientConfig' => $this->clientConfig($trip, false, $token),
        ]);
    }

    /**
     * Add an item to an itinerary owned by the signed-in user.
     */
    public function storeItem(Request $request, Trip $trip): JsonResponse
    {
        return $this->storeItemForTrip($request, $this->ownedTrip($trip));
    }

    /**
     * Update an itinerary item owned by the signed-in user.
     */
    public function updateItem(Request $request, Trip $trip, ItineraryItem $item): JsonResponse
    {
        return $this->updateItemForTrip($request, $this->ownedTrip($trip), $item);
    }

    /**
     * Remove an itinerary item owned by the signed-in user.
     */
    public function destroyItem(Trip $trip, ItineraryItem $item): JsonResponse
    {
        return $this->destroyItemForTrip($this->ownedTrip($trip), $item);
    }

    /**
     * Persist a drag-and-drop ordering supplied by the private itinerary UI.
     */
    public function reorder(Request $request, Trip $trip): JsonResponse
    {
        return $this->reorderForTrip($request, $this->ownedTrip($trip));
    }

    /**
     * Replace a high-emission transport choice with its suggested alternative.
     */
    public function applyEcoAlternative(Trip $trip, ItineraryItem $item): JsonResponse
    {
        return $this->applyEcoAlternativeForTrip($this->ownedTrip($trip), $item);
    }

    /**
     * Confirm that online changes have been saved and return a current snapshot.
     */
    public function save(Trip $trip): JsonResponse
    {
        return response()->json([
            'message' => 'Itinerary saved to cloud storage.',
            'itinerary' => $this->tripPayload($this->ownedTrip($trip)->fresh('items')),
        ]);
    }

    /**
     * Reconcile an offline browser snapshot when connectivity returns.
     */
    public function sync(Request $request, Trip $trip): JsonResponse
    {
        $trip = $this->ownedTrip($trip);
        $data = $request->validate([
            'items' => ['required', 'array'],
            'deleted_ids' => ['nullable', 'array'],
            'deleted_ids.*' => ['integer', 'min:1'],
        ]);
        $conflicts = [];

        DB::transaction(function () use ($data, $trip, &$conflicts): void {
            foreach ($data['items'] as $incoming) {
                $itemId = $incoming['item_id'] ?? null;
                $item = is_numeric($itemId) && (int) $itemId > 0
                    ? $trip->items()->whereKey((int) $itemId)->first()
                    : null;

                if ($item && ! empty($incoming['updated_at']) && Carbon::parse($incoming['updated_at'])->lt($item->updated_at)) {
                    $conflicts[] = [
                        'item_id' => $item->item_id,
                        'title' => $item->title,
                        'resolution' => 'The newer cloud version was kept.',
                    ];
                    continue;
                }

                $payload = $this->preparedItemData($incoming, $trip);

                if ($item) {
                    $item->update($payload);
                } else {
                    $trip->items()->create($payload);
                }
            }

            foreach ($data['deleted_ids'] ?? [] as $itemId) {
                $trip->items()->whereKey($itemId)->delete();
            }
        });

        $trip->load('items');

        return response()->json([
            'message' => 'Offline edits were synchronized.',
            'conflicts' => $conflicts,
            'itinerary' => $this->tripPayload($trip),
        ]);
    }

    /**
     * Generate a hashed, permission-controlled link for an itinerary.
     */
    public function share(Request $request, Trip $trip): JsonResponse
    {
        $trip = $this->ownedTrip($trip);
        $data = $request->validate([
            'permission' => ['required', Rule::in(['view'])],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);
        $token = Str::random(64);

        ItineraryShare::create([
            'trip_id' => $trip->getKey(),
            'created_by' => (int) auth()->id(),
            'token_hash' => hash('sha256', $token),
            'permission' => $data['permission'],
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        return response()->json([
            'url' => route('itineraries.shared', ['token' => $token]),
            'permission' => $data['permission'],
            'message' => 'Secure share link created.',
        ], 201);
    }

    /**
     * Download a PDF itinerary and eco summary without an external PDF package.
     */
    public function exportPdf(Trip $trip): \Illuminate\Http\Response
    {
        $trip = $this->ownedTrip($trip);
        $filename = Str::slug($trip->title ?: 'itinerary').'-itinerary.pdf';

        return response($this->pdfExporter->make($trip), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Download a calendar-compatible ICS copy of the itinerary.
     */
    public function exportCalendar(Trip $trip): \Illuminate\Http\Response
    {
        $trip = $this->ownedTrip($trip);
        $filename = Str::slug($trip->title ?: 'itinerary').'-itinerary.ics';

        return response($this->calendarExporter->make($trip), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Add an item through an edit-enabled shared itinerary link.
     */
    public function sharedStoreItem(Request $request, string $token): JsonResponse
    {
        return $this->storeItemForTrip($request, $this->editableShare($token)->trip->load('items'));
    }

    /**
     * Update an item through an edit-enabled shared itinerary link.
     */
    public function sharedUpdateItem(Request $request, string $token, ItineraryItem $item): JsonResponse
    {
        return $this->updateItemForTrip($request, $this->editableShare($token)->trip->load('items'), $item);
    }

    /**
     * Delete an item through an edit-enabled shared itinerary link.
     */
    public function sharedDestroyItem(string $token, ItineraryItem $item): JsonResponse
    {
        return $this->destroyItemForTrip($this->editableShare($token)->trip->load('items'), $item);
    }

    /**
     * Apply an eco alternative through an edit-enabled shared itinerary link.
     */
    public function sharedApplyEcoAlternative(string $token, ItineraryItem $item): JsonResponse
    {
        return $this->applyEcoAlternativeForTrip($this->editableShare($token)->trip->load('items'), $item);
    }

    /**
     * Create and return an itinerary item for a permitted trip context.
     */
    private function storeItemForTrip(Request $request, Trip $trip): JsonResponse
    {
        $item = $trip->items()->create($this->preparedItemData($request->all(), $trip));
        $trip->load('items');

        return response()->json([
            'message' => 'Itinerary item added and saved.',
            'item' => $this->itemPayload($item),
            'itinerary' => $this->tripPayload($trip),
        ], 201);
    }

    /**
     * Update and return an itinerary item for a permitted trip context.
     */
    private function updateItemForTrip(Request $request, Trip $trip, ItineraryItem $item): JsonResponse
    {
        $this->itemForTrip($trip, $item)->update($this->preparedItemData($request->all(), $trip));
        $trip->load('items');

        return response()->json([
            'message' => 'Itinerary item updated and saved.',
            'item' => $this->itemPayload($item->fresh()),
            'itinerary' => $this->tripPayload($trip),
        ]);
    }

    /**
     * Delete an itinerary item for a permitted trip context.
     */
    private function destroyItemForTrip(Trip $trip, ItineraryItem $item): JsonResponse
    {
        $this->itemForTrip($trip, $item)->delete();
        $trip->load('items');

        return response()->json([
            'message' => 'Itinerary item removed.',
            'itinerary' => $this->tripPayload($trip),
        ]);
    }

    /**
     * Apply a stored eco suggestion in a permitted trip context.
     */
    private function applyEcoAlternativeForTrip(Trip $trip, ItineraryItem $item): JsonResponse
    {
        $item = $this->itemForTrip($trip, $item);
        $suggestion = $this->ecoAlternatives->suggestionFor($item->transport_mode, $item->distance_km);

        if (! $suggestion) {
            return response()->json(['message' => 'No lower-carbon alternative is available for this item.'], 422);
        }

        $item->update([
            'transport_mode' => $suggestion['mode'],
            'carbon_kg' => $suggestion['alternative_carbon_kg'],
            'eco_note' => 'Eco alternative applied: '.$suggestion['label'].'.',
        ]);
        $trip->load('items');

        return response()->json([
            'message' => 'Eco alternative applied. Your footprint has been recalculated.',
            'itinerary' => $this->tripPayload($trip),
        ]);
    }

    /**
     * Persist item ordering in a permitted trip context.
     */
    private function reorderForTrip(Request $request, Trip $trip): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.item_id' => ['required', 'integer'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($data, $trip): void {
            foreach ($data['items'] as $position) {
                $trip->items()->whereKey($position['item_id'])->update(['sort_order' => $position['sort_order']]);
            }
        });
        $trip->load('items');

        return response()->json([
            'message' => 'Itinerary order saved.',
            'itinerary' => $this->tripPayload($trip),
        ]);
    }

    /**
     * Validate, normalize, and calculate values for an itinerary item.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function preparedItemData(array $input, Trip $trip): array
    {
        $data = Validator::make($input, $this->itemRules())->validate();
        $mode = $data['category'] === 'transport' ? ($data['transport_mode'] ?? null) : null;
        $distance = $mode ? (float) ($data['distance_km'] ?? 0) : 0.0;
        $suggestion = $this->ecoAlternatives->suggestionFor($mode, $distance);

        return [
            'category' => $data['category'],
            'title' => $data['title'],
            'scheduled_date' => $data['scheduled_date'] ?? $trip->start_date,
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'location' => $data['location'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'notes' => $data['notes'] ?? null,
            'transport_mode' => $mode,
            'distance_km' => $mode ? $distance : null,
            'carbon_kg' => $this->ecoAlternatives->estimate($mode, $distance),
            'eco_note' => $suggestion ? 'Lower-carbon option available: '.$suggestion['label'].'.' : null,
            'sort_order' => $data['sort_order'] ?? ((int) $trip->items()->max('sort_order') + 1),
            'metadata' => $data['metadata'] ?? null,
        ];
    }

    /**
     * Get validation rules shared by item mutations and offline reconciliation.
     *
     * @return array<string, mixed>
     */
    private function itemRules(): array
    {
        return [
            'category' => ['required', Rule::in(['transport', 'lodging', 'activity', 'food', 'sightseeing'])],
            'title' => ['required', 'string', 'max:160'],
            'scheduled_date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'location' => ['nullable', 'string', 'max:180'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'transport_mode' => ['nullable', Rule::in(['flight', 'private_car', 'taxi', 'ferry', 'bus', 'train', 'electric_train', 'walking', 'cycling'])],
            'distance_km' => ['nullable', 'numeric', 'min:0', 'max:50000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * Ensure that a private itinerary belongs to the current user.
     */
    private function ownedTrip(Trip $trip): Trip
    {
        abort_unless((int) $trip->user_id === (int) auth()->id(), 403);

        return $trip->load('items');
    }

    /**
     * Ensure that an item belongs to the supplied trip.
     */
    private function itemForTrip(Trip $trip, ItineraryItem $item): ItineraryItem
    {
        abort_unless((int) $item->trip_id === (int) $trip->getKey(), 404);

        return $item;
    }

    /**
     * Resolve a valid shared itinerary link.
     */
    private function activeShare(string $token): ItineraryShare
    {
        $share = ItineraryShare::with('trip.items')
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();

        abort_unless($share->isActive(), 410, 'This itinerary share link has expired.');

        return $share;
    }

    /**
     * Resolve a share link that grants collaborative editing rights.
     */
    private function editableShare(string $token): ItineraryShare
    {
        $share = $this->activeShare($token);
        abort_unless($share->canEdit(), 403, 'This share link is view-only.');

        return $share;
    }

    /**
     * Build safe client-side data for the itinerary interface.
     *
     * @return array<string, mixed>
     */
    private function tripPayload(Trip $trip): array
    {
        $trip->loadMissing('items');
        $items = $trip->items->map(fn (ItineraryItem $item) => $this->itemPayload($item))->values();
        $breakdown = $trip->items
            ->groupBy('category')
            ->map(fn ($group) => round((float) $group->sum('carbon_kg'), 2));

        return [
            'trip_id' => $trip->getKey(),
            'title' => $trip->title,
            'destination' => $trip->destination,
            'description' => $trip->description,
            'start_date' => $trip->start_date?->toDateString(),
            'end_date' => $trip->end_date?->toDateString(),
            'map_center' => $trip->map_center ?: [3.1390, 101.6869],
            'total_carbon_kg' => round((float) $trip->items->sum('carbon_kg'), 2),
            'carbon_breakdown' => $breakdown,
            'items' => $items,
        ];
    }

    /**
     * Build client-safe data for one itinerary item.
     *
     * @return array<string, mixed>
     */
    private function itemPayload(ItineraryItem $item): array
    {
        return [
            'item_id' => $item->item_id,
            'category' => $item->category,
            'title' => $item->title,
            'scheduled_date' => $item->scheduled_date?->toDateString(),
            'start_time' => $item->start_time ? substr((string) $item->start_time, 0, 5) : null,
            'end_time' => $item->end_time ? substr((string) $item->end_time, 0, 5) : null,
            'location' => $item->location,
            'latitude' => $item->latitude,
            'longitude' => $item->longitude,
            'notes' => $item->notes,
            'transport_mode' => $item->transport_mode,
            'transport_label' => $this->ecoAlternatives->labelFor($item->transport_mode),
            'distance_km' => $item->distance_km,
            'carbon_kg' => (float) $item->carbon_kg,
            'eco_note' => $item->eco_note,
            'eco_suggestion' => $this->ecoAlternatives->suggestionFor($item->transport_mode, $item->distance_km),
            'sort_order' => $item->sort_order,
            'metadata' => $item->metadata,
            'updated_at' => $item->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Build the small set of URLs and flags consumed by the itinerary browser UI.
     *
     * Keeping this out of Blade prevents nested route parameters from confusing
     * Laravel's @json directive during view compilation.
     *
     * @return array<string, mixed>
     */
    private function clientConfig(Trip $trip, bool $canEdit, ?string $shareToken = null): array
    {
        $shared = $shareToken !== null;
        $itemTemplate = '__ITEM__';

        return [
            'itinerary' => $this->tripPayload($trip),
            'canEdit' => $canEdit,
            'isShared' => $shared,
            'csrfToken' => csrf_token(),
            'itemStoreUrl' => $canEdit ? route('itineraries.items.store', $trip) : null,
            'itemUrlTemplate' => $canEdit ? route('itineraries.items.update', [$trip, $itemTemplate]) : null,
            'itemDeleteUrlTemplate' => $canEdit ? route('itineraries.items.destroy', [$trip, $itemTemplate]) : null,
            'ecoUrlTemplate' => $canEdit ? route('itineraries.items.eco', [$trip, $itemTemplate]) : null,
            'saveUrl' => $canEdit ? route('itineraries.save', $trip) : null,
            'syncUrl' => $canEdit ? route('itineraries.sync', $trip) : null,
            'reorderUrl' => $canEdit ? route('itineraries.items.reorder', $trip) : null,
            'shareUrl' => $shared ? null : route('itineraries.share', $trip),
            'weatherUrl' => $shared ? null : route('itinerary.weather', ['itinerary_id' => $trip->getKey()]),
        ];
    }
}
