<?php

namespace App\Services;

use App\Models\Trip;
use Carbon\Carbon;

class ItineraryCalendarExporter
{
    /**
     * Build an RFC 5545 calendar document for a trip's itinerary.
     */
    public function make(Trip $trip): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//ExploreMY//Itinerary//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ($trip->items as $item) {
            $date = $item->scheduled_date ?? $trip->start_date;

            if (! $date) {
                continue;
            }

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:itinerary-item-'.$item->item_id.'@exploremy.local';
            $lines[] = 'DTSTAMP:'.now()->utc()->format('Ymd\\THis\\Z');
            $lines[] = 'SUMMARY:'.$this->escape($item->title);
            $lines[] = 'LOCATION:'.$this->escape((string) $item->location);
            $lines[] = 'DESCRIPTION:'.$this->escape(trim((string) $item->notes.' | Estimated emissions: '.number_format((float) $item->carbon_kg, 2).' kg CO2e'));

            if ($item->start_time) {
                $start = Carbon::parse($date->format('Y-m-d').' '.$item->start_time);
                $end = $item->end_time
                    ? Carbon::parse($date->format('Y-m-d').' '.$item->end_time)
                    : $start->copy()->addHour();
                $lines[] = 'DTSTART:'.$start->format('Ymd\\THis');
                $lines[] = 'DTEND:'.$end->format('Ymd\\THis');
            } else {
                $lines[] = 'DTSTART;VALUE=DATE:'.$date->format('Ymd');
                $lines[] = 'DTEND;VALUE=DATE:'.$date->copy()->addDay()->format('Ymd');
            }

            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    /**
     * Escape text for an iCalendar property value.
     */
    private function escape(string $value): string
    {
        return str_replace(["\\", ';', ',', "\r\n", "\n"], ["\\\\", '\\;', '\\,', '\\n', '\\n'], $value);
    }
}
