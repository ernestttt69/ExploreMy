<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetItineraryWeatherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // This application calls itineraries "trips" and uses its existing trips table.
            'itinerary_id' => ['required', 'integer', 'exists:trips,id'],
            'refresh' => ['nullable', 'boolean'],
        ];
    }
}
