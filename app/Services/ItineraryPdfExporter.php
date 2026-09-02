<?php

namespace App\Services;

use App\Models\Trip;
use Illuminate\Support\Str;

class ItineraryPdfExporter
{
    /**
     * Create a dependency-free, downloadable PDF representation of an itinerary.
     */
    public function make(Trip $trip): string
    {
        $lines = [
            'ExploreMY - '.$trip->title,
            trim(($trip->destination ?: 'Malaysia').' | '.$this->dateRange($trip)),
            'Estimated footprint: '.number_format($trip->total_carbon_kg, 2).' kg CO2e',
            '',
        ];

        foreach ($trip->items as $item) {
            $time = $item->start_time ? substr((string) $item->start_time, 0, 5).' - ' : '';
            $lines[] = strtoupper((string) $item->scheduled_date?->format('D, d M Y'));
            $lines[] = $time.$item->title.' ('.ucfirst($item->category).')';
            $lines[] = 'Location: '.($item->location ?: 'Not specified');
            $lines[] = 'Estimated emissions: '.number_format((float) $item->carbon_kg, 2).' kg CO2e';

            if ($item->category === 'transport') {
                $metadata = $item->metadata ?? [];
                $lines[] = 'Transport: '.$this->transportLabel($item->transport_mode);
                $lines[] = 'Distance: '.number_format((float) $item->distance_km, 2).' km';

                if (! empty($metadata['duration'])) {
                    $lines[] = 'Travel time: '.$metadata['duration'];
                }

                if (isset($metadata['fare']) && $metadata['fare'] !== null) {
                    $lines[] = 'Estimated fare: '.($metadata['fare_currency'] ?? 'MYR').' '.number_format((float) $metadata['fare'], 2);
                }
            }

            if ($item->notes) {
                $lines[] = 'Notes: '.$item->notes;
            }

            $lines[] = '';
        }

        return $this->buildDocument($this->wrapLines($lines));
    }

    private function transportLabel(?string $mode): string
    {
        return Str::headline((string) ($mode ?: 'Not specified'));
    }

    /**
     * Format the trip's date range for the document heading.
     */
    private function dateRange(Trip $trip): string
    {
        if (! $trip->start_date) {
            return 'Dates to be confirmed';
        }

        if (! $trip->end_date || $trip->end_date->isSameDay($trip->start_date)) {
            return $trip->start_date->format('d M Y');
        }

        return $trip->start_date->format('d M Y').' - '.$trip->end_date->format('d M Y');
    }

    /**
     * Wrap printable text to keep it inside the page margins.
     *
     * @param array<int, string> $lines
     * @return array<int, string>
     */
    private function wrapLines(array $lines): array
    {
        $wrapped = [];

        foreach ($lines as $line) {
            $safe = Str::ascii($line);
            $parts = wordwrap($safe, 88, "\n", true);

            foreach (explode("\n", $parts ?: ' ') as $part) {
                $wrapped[] = $part;
            }
        }

        return $wrapped;
    }

    /**
     * Build a minimal standards-compliant PDF using a built-in Helvetica font.
     *
     * @param array<int, string> $lines
     */
    private function buildDocument(array $lines): string
    {
        $pages = array_chunk($lines, 47);
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pageReferences = [];
        $nextObject = 4;

        foreach ($pages as $pageLines) {
            $pageObject = $nextObject++;
            $contentObject = $nextObject++;
            $pageReferences[] = $pageObject.' 0 R';
            $stream = $this->pageStream($pageLines);
            $objects[$contentObject] = "<< /Length ".strlen($stream)." >>\nstream\n".$stream."\nendstream";
            $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R >> >> /Contents '.$contentObject.' 0 R >>';
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $pageReferences).'] /Count '.count($pageReferences).' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];

        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$object."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $maxObject = max(array_keys($objects));
        $pdf .= 'xref'."\n0 ".($maxObject + 1)."\n0000000000 65535 f \n";

        for ($number = 1; $number <= $maxObject; $number++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$number])."\n";
        }

        return $pdf.'trailer'."\n<< /Size ".($maxObject + 1).' /Root 1 0 R >>'."\nstartxref\n".$xrefOffset."\n%%EOF";
    }

    /**
     * Build a single page's text content stream.
     *
     * @param array<int, string> $lines
     */
    private function pageStream(array $lines): string
    {
        $stream = "BT\n/F1 10 Tf\n50 745 Td\n14 TL\n";

        foreach ($lines as $line) {
            $stream .= '('.str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line).") Tj\nT*\n";
        }

        return $stream.'ET';
    }
}
