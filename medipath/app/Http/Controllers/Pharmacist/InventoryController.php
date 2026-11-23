<?php

namespace App\Http\Controllers\Pharmacist;

use App\Http\Controllers\Controller;
use App\Models\InventoryLedger;
use App\Models\Medicine;
use App\Services\CurrencyFormatter;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Medicine inventory + restock + archive (PLAN Sprints 8.4/8.5). */
class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly CurrencyFormatter $money,
    ) {}

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $category = trim((string) $request->query('category', ''));
        $low = $request->boolean('low');
        $expiry = (string) $request->query('expiry', 'any');

        $rows = Medicine::query()
            ->where('is_active', true)
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
                $query->where(fn ($inner) => $inner->where('name', 'like', $like)
                    ->orWhere('generic_name', 'like', $like)
                    ->orWhere('batch_number', 'like', $like)
                    ->orWhere('category', 'like', $like));
            })
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->when($low, fn ($query) => $query->whereColumn('stock_quantity', '<=', 'min_stock_threshold'))
            ->when($expiry === 'expired', fn ($query) => $query->whereNotNull('expiry_date')->whereDate('expiry_date', '<', now()->toDateString()))
            ->when($expiry === '90', fn ($query) => $query->whereNotNull('expiry_date')->whereDate('expiry_date', '<=', now()->addDays(90)->toDateString()))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $categories = Medicine::query()->where('is_active', true)->distinct()->orderBy('category')->pluck('category')->values();

        $ledger = InventoryLedger::query()
            ->with(['medicine:id,name,strength', 'actor:id,name'])
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(fn (InventoryLedger $entry): array => [
                'id' => $entry->id,
                'medicine' => trim(($entry->medicine?->name ?? 'Medicine').' '.($entry->medicine?->strength ?? '')),
                'type' => $entry->type?->value,
                'typeLabel' => $entry->type?->label(),
                'change' => (int) $entry->quantity_change,
                'new' => (int) $entry->new_qty,
                'actor' => $entry->actor?->name,
                'reference' => $entry->reference,
                'createdAt' => $entry->created_at?->toIso8601String(),
            ])->values();

        return Inertia::render('Pharmacist/Inventory', [
            'medicines' => $rows->getCollection()->map(fn (Medicine $medicine): array => [
                'id' => $medicine->id,
                'name' => $medicine->name,
                'displayName' => trim($medicine->name.' '.($medicine->strength ?? '')),
                'genericName' => $medicine->generic_name,
                'category' => $medicine->category,
                'strength' => $medicine->strength,
                'form' => $medicine->form,
                'manufacturer' => $medicine->manufacturer,
                'unitPrice' => (string) $medicine->unit_price,
                'priceFormatted' => $this->money->format((float) $medicine->unit_price),
                'stock' => (int) $medicine->stock_quantity,
                'threshold' => (int) $medicine->min_stock_threshold,
                'stockStatus' => $medicine->stock_status,
                'batch' => $medicine->batch_number,
                'expiryDate' => $medicine->expiry_date?->toDateString(),
                'expiryStatus' => $medicine->expiry_status,
                'requiresPrescription' => (bool) $medicine->requires_prescription,
                'sideEffects' => implode(', ', $medicine->side_effects ?? []),
                'contraindications' => $medicine->contraindications,
                'description' => $medicine->description,
            ])->values(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'from' => $rows->firstItem(),
                'to' => $rows->lastItem(),
            ],
            'filters' => ['q' => $q, 'category' => $category, 'low' => $low, 'expiry' => $expiry],
            'categories' => $categories,
            'ledger' => $ledger,
            'dataState' => $this->state($request) ?? ($rows->total() === 0 ? 'empty' : 'success'),
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request, true);
        $this->inventory->create($request->user(), $validated);

        return back()->with('success', 'Medicine added and opening stock recorded.');
    }

    public function update(Request $request, Medicine $medicine): RedirectResponse
    {
        $validated = $this->validated($request, false);
        $this->inventory->update($request->user(), $medicine, $validated);

        return back()->with('success', 'Medicine details updated. Stock changes go through restock.');
    }

    public function restock(Request $request, Medicine $medicine): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'batch_number' => ['required', 'string', 'max:60'],
            'expiry_date' => ['required', 'date', 'after:today'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->inventory->restock($request->user(), $medicine, $validated);

        return back()->with('success', "Restocked {$medicine->name}. Ledger entry written.");
    }

    public function archive(Request $request, Medicine $medicine): RedirectResponse
    {
        $this->inventory->archive($request->user(), $medicine);

        return back()->with('success', "{$medicine->name} archived. Historical records remain intact.");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $creating): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'generic_name' => ['nullable', 'string', 'max:160'],
            'category' => ['required', 'string', 'max:80'],
            'strength' => ['nullable', 'string', 'max:60'],
            'form' => ['required', Rule::in(['tablet', 'capsule', 'syrup', 'injection', 'inhaler', 'cream', 'drops', 'other'])],
            'manufacturer' => ['nullable', 'string', 'max:160'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'min_stock_threshold' => ['required', 'integer', 'min:0', 'max:100000'],
            'expiry_date' => ['nullable', 'date'],
            'batch_number' => ['nullable', 'string', 'max:60'],
            'side_effects' => ['nullable', 'string', 'max:1000'],
            'contraindications' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:2000'],
            'requires_prescription' => ['sometimes', 'boolean'],
            'stock_quantity' => [$creating ? 'required' : 'nullable', 'integer', 'min:0', 'max:1000000'],
        ]);

        $validated['side_effects'] = collect(explode(',', (string) ($validated['side_effects'] ?? '')))
            ->map(fn (string $effect) => trim($effect))
            ->filter()
            ->values()
            ->all() ?: null;

        if (! $creating) {
            unset($validated['stock_quantity']);
        }

        return $validated;
    }

    private function state(Request $request): ?string
    {
        $state = (string) $request->query('state', '');

        return in_array($state, ['success', 'skeleton', 'empty', 'error'], true) ? $state : null;
    }
}