<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class ParkingExport implements FromArray
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $this->transformData($data);
    }

    public function array(): array
    {
        return $this->data;
    }

    private function transformData(array $rows): array
    {
        $flatRows = [];
        $allHeaders = [];

        // Step 1: Flatten rows and collect headers
        foreach ($rows as $row) {
            $flatRow = [];

            foreach ($row as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    foreach ((array) $value as $subKey => $subValue) {
                        $compositeKey = "{$key}_{$subKey}";
                        $flatRow[$compositeKey] = $subValue;
                        $allHeaders[$compositeKey] = true;
                    }
                } else {
                    $flatRow[$key] = $value;
                    $allHeaders[$key] = true;
                }
            }

            $flatRows[] = $flatRow;
        }

        // Step 2: Preserve original header order
        $originalHeaders = array_keys($allHeaders);

        // Step 3: Extract and sort monthly profit headers
        $monthOrder = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $monthlyHeaders = [];
        $finalHeaders = [];

        foreach ($originalHeaders as $header) {
            if (preg_match('/monthly_profit_(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)/', $header, $match)) {
                $monthlyHeaders[] = $header;
            } else {
                $finalHeaders[] = $header;
            }
        }

        usort($monthlyHeaders, function ($a, $b) use ($monthOrder) {
            preg_match('/_(\w{3})$/', $a, $matchA);
            preg_match('/_(\w{3})$/', $b, $matchB);

            $indexA = array_search($matchA[1], $monthOrder);
            $indexB = array_search($matchB[1], $monthOrder);

            return $indexA <=> $indexB;
        });

        // Step 4: Inject sorted monthly headers at the right position
        // You can customize where to insert them (e.g. after `total_profit`)
        $finalHeadersWithMonths = [];
        foreach ($finalHeaders as $header) {
            $finalHeadersWithMonths[] = $header;
            if (str_starts_with($header, 'total_profit')) {
                $finalHeadersWithMonths = array_merge($finalHeadersWithMonths, $monthlyHeaders);
            }
        }

        // If not injected yet, append monthly headers at the end
        if (count($finalHeadersWithMonths) === count($finalHeaders)) {
            $finalHeadersWithMonths = array_merge($finalHeadersWithMonths, $monthlyHeaders);
        }

        // Step 5: Construct final data
        $finalData = [$finalHeadersWithMonths];

        foreach ($flatRows as $flatRow) {
            $rowData = [];
            foreach ($finalHeadersWithMonths as $header) {
                $rowData[] = $flatRow[$header] ?? null;
            }
            $finalData[] = $rowData;
        }

        return $finalData;
    }
}

