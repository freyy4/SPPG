<?php

namespace App\Services;

class FruitCalculator
{
    public function calculate(object $fruit, int $pb, int $pk, float $budget): array
    {
        $portions = ($pb * (float) $fruit->portion_pb) + ($pk * (float) $fruit->portion_pk);
        $kg = $portions / (float) $fruit->units_per_kg;
        // Keep full kilogram precision for pricing; round money only at the end.
        $cost = round($kg * (float) $fruit->price, 2);

        return [
            'id' => $fruit->id,
            'name' => $fruit->name,
            'region' => $fruit->source_region ?? null,
            'source_po_id' => $fruit->source_po_id,
            'unit' => $fruit->unit,
            'version' => $fruit->version,
            'units_per_kg' => (float) $fruit->units_per_kg,
            'portion_pb' => (float) $fruit->portion_pb,
            'portion_pk' => (float) $fruit->portion_pk,
            'price' => (float) $fruit->price,
            'portions' => round($portions, 4),
            'kg' => round($kg, 8),
            'cost' => $cost,
            'remaining' => round($budget - $cost, 2),
            'within_budget' => $cost <= $budget,
        ];
    }
}
