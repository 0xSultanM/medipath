<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Medicine;
use App\Services\CurrencyFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Live medicine search and side-effect reference for prescribing (PLAN 6.7). */
class MedicineController extends Controller
{
    public function search(Request $request, CurrencyFormatter $money): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $rows = Medicine::query()
            ->active()
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(fn ($inner) => $inner->where('name', 'like', $like)
                    ->orWhere('generic_name', 'like', $like)
                    ->orWhere('category', 'like', $like)
                    ->orWhere('batch_number', 'like', $like));
            })
            ->orderBy('name')
            ->limit(12)
            ->get()
            ->map(fn (Medicine $medicine): array => $this->payload($medicine, $money));

        return response()->json(['items' => $rows])->header('Cache-Control', 'no-store');
    }

    public function show(Medicine $medicine, CurrencyFormatter $money): JsonResponse
    {
        return response()->json($this->payload($medicine, $money, true))->header('Cache-Control', 'no-store');
    }

    /** @return array<string, mixed> */
    private function payload(Medicine $medicine, CurrencyFormatter $money, bool $full = false): array
    {
        return [
            'id' => $medicine->id,
            'name' => $medicine->name,
            'genericName' => $medicine->generic_name,
            'strength' => $medicine->strength,
            'form' => $medicine->form,
            'category' => $medicine->category,
            'batch' => $medicine->batch_number,
            'stock' => $medicine->stock_quantity,
            'stockStatus' => $medicine->stock_status,
            'expiryStatus' => $medicine->expiry_status,
            'expiryDate' => $medicine->expiry_date?->toDateString(),
            'priceFormatted' => $money->format((float) $medicine->unit_price),
            'sideEffects' => $medicine->side_effects ?? [],
            'contraindications' => $full ? $medicine->contraindications : null,
            'requiresPrescription' => (bool) $medicine->requires_prescription,
        ];
    }
}