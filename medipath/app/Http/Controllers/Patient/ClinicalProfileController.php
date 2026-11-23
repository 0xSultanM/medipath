<?php

namespace App\Http\Controllers\Patient;

use App\Enums\AllergySeverity;
use App\Http\Controllers\Controller;
use App\Models\PatientAllergy;
use App\Models\PatientMedication;
use App\Services\ActivityLogService;
use App\Support\ActivityActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Patient-owned allergy and medication safety lists (PLAN Sprint 4.3). */
class ClinicalProfileController extends Controller
{
    private const ALLERGY_TYPES = ['medication', 'food', 'environmental', 'other'];

    public function __construct(private readonly ActivityLogService $activity) {}

    public function index(Request $request): Response
    {
        $patient = $this->patient($request);
        $tab = $request->query('tab') === 'medications' ? 'medications' : 'allergies';

        $allergies = PatientAllergy::query()->where('patient_id', $patient->id)->orderBy('substance')->get();
        $medications = PatientMedication::query()->where('patient_id', $patient->id)->orderByDesc('is_active')->orderBy('medicine_name')->get();

        $forced = $this->state($request);
        $activeCount = $tab === 'allergies' ? $allergies->count() : $medications->count();
        $state = $forced ?? ($activeCount === 0 ? 'empty' : 'success');

        return Inertia::render('Patient/ClinicalProfile', [
            'tab' => $tab,
            'allergies' => $allergies->map(fn (PatientAllergy $allergy): array => [
                'id' => $allergy->id,
                'substance' => $allergy->substance,
                'type' => $allergy->allergy_type,
                'severity' => $allergy->severity?->value,
                'severityLabel' => $allergy->severity?->label(),
                'reactionNotes' => $allergy->reaction_notes,
                'createdAt' => $allergy->created_at?->toIso8601String(),
            ])->values(),
            'medications' => $medications->map(fn (PatientMedication $medication): array => [
                'id' => $medication->id,
                'name' => $medication->medicine_name,
                'dosage' => $medication->dosage,
                'frequency' => $medication->frequency,
                'startDate' => $medication->start_date?->toDateString(),
                'endDate' => $medication->end_date?->toDateString(),
                'isActive' => $medication->is_active,
                'notes' => $medication->notes,
            ])->values(),
            'counts' => [
                'allergies' => $allergies->count(),
                'severeAllergies' => $allergies->filter(fn (PatientAllergy $a): bool => $a->isSevere())->count(),
                'medications' => $medications->count(),
                'activeMedications' => $medications->where('is_active', true)->count(),
            ],
            'options' => [
                'allergyTypes' => self::ALLERGY_TYPES,
                'severities' => collect(AllergySeverity::cases())->map(fn (AllergySeverity $s) => ['value' => $s->value, 'label' => $s->label()])->values(),
            ],
            'dataState' => $state,
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function storeAllergy(Request $request): RedirectResponse
    {
        $patient = $this->patient($request);
        $data = $this->validateAllergy($request);

        if (PatientAllergy::query()->where('patient_id', $patient->id)->where('substance', $data['substance'])->exists()) {
            throw ValidationException::withMessages(['substance' => 'That substance is already recorded. Edit the existing entry instead.']);
        }

        $allergy = PatientAllergy::query()->create([
            'patient_id' => $patient->id,
            'recorded_by' => $request->user()->id,
            ...$data,
        ]);

        $this->activity->record(ActivityActions::ALLERGY_ADDED, $allergy, [
            'substance' => $allergy->substance,
            'severity' => $allergy->severity?->value,
        ], $request->user());

        return back()->with('success', 'Allergy added. Prescribing and pharmacy gates read it immediately.');
    }

    public function updateAllergy(Request $request, PatientAllergy $allergy): RedirectResponse
    {
        $patient = $this->patient($request);
        abort_unless((int) $allergy->patient_id === (int) $patient->id, 403);

        $data = $this->validateAllergy($request, $allergy);

        if (PatientAllergy::query()->where('patient_id', $patient->id)->where('substance', $data['substance'])->whereKeyNot($allergy->id)->exists()) {
            throw ValidationException::withMessages(['substance' => 'That substance is already recorded.']);
        }

        $allergy->forceFill([...$data, 'recorded_by' => $request->user()->id])->save();

        $this->activity->record(ActivityActions::ALLERGY_UPDATED, $allergy, [
            'substance' => $allergy->substance,
            'severity' => $allergy->severity?->value,
        ], $request->user());

        return back()->with('success', 'Allergy updated.');
    }

    public function destroyAllergy(Request $request, PatientAllergy $allergy): RedirectResponse
    {
        $patient = $this->patient($request);
        abort_unless((int) $allergy->patient_id === (int) $patient->id, 403);

        $this->activity->record(ActivityActions::ALLERGY_REMOVED, $allergy, [
            'substance' => $allergy->substance,
            'severity' => $allergy->severity?->value,
        ], $request->user());

        $allergy->delete();

        return back()->with('success', 'Allergy removed. Clinicians will see the change in the safety history.');
    }

    public function storeMedication(Request $request): RedirectResponse
    {
        $patient = $this->patient($request);
        $data = $this->validateMedication($request);

        $medication = PatientMedication::query()->create([
            'patient_id' => $patient->id,
            'is_active' => true,
            ...$data,
        ]);

        $this->activity->record(ActivityActions::MEDICATION_ADDED, $medication, [
            'medicine' => $medication->medicine_name,
            'dosage' => $medication->dosage,
        ], $request->user());

        return back()->with('success', 'Medication added to your current list.');
    }

    public function updateMedication(Request $request, PatientMedication $medication): RedirectResponse
    {
        $patient = $this->patient($request);
        abort_unless((int) $medication->patient_id === (int) $patient->id, 403);

        $medication->forceFill($this->validateMedication($request))->save();

        $this->activity->record(ActivityActions::MEDICATION_UPDATED, $medication, [
            'medicine' => $medication->medicine_name,
            'is_active' => $medication->is_active,
        ], $request->user());

        return back()->with('success', 'Medication updated.');
    }

    public function toggleMedication(Request $request, PatientMedication $medication): RedirectResponse
    {
        $patient = $this->patient($request);
        abort_unless((int) $medication->patient_id === (int) $patient->id, 403);

        $medication->forceFill([
            'is_active' => ! $medication->is_active,
            'end_date' => $medication->is_active ? today()->toDateString() : null,
        ])->save();

        $this->activity->record(ActivityActions::MEDICATION_UPDATED, $medication, [
            'medicine' => $medication->medicine_name,
            'is_active' => $medication->is_active,
        ], $request->user());

        return back()->with('success', $medication->is_active ? 'Medication marked active.' : 'Medication stopped.');
    }

    /** @return array<string, mixed> */
    private function validateAllergy(Request $request, ?PatientAllergy $allergy = null): array
    {
        $validated = $request->validate([
            'substance' => ['required', 'string', 'min:2', 'max:160'],
            'allergy_type' => ['required', Rule::in(self::ALLERGY_TYPES)],
            'severity' => ['required', Rule::in(array_column(AllergySeverity::cases(), 'value'))],
            'reaction_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        return [
            'substance' => trim($validated['substance']),
            'allergy_type' => $validated['allergy_type'],
            'severity' => $validated['severity'],
            'reaction_notes' => $validated['reaction_notes'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function validateMedication(Request $request): array
    {
        $validated = $request->validate([
            'medicine_name' => ['required', 'string', 'min:2', 'max:160'],
            'dosage' => ['required', 'string', 'max:80'],
            'frequency' => ['required', 'string', 'max:80'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        return [
            'medicine_name' => trim($validated['medicine_name']),
            'dosage' => trim($validated['dosage']),
            'frequency' => trim($validated['frequency']),
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];
    }

    private function patient(Request $request)
    {
        $patient = $request->user()->patient;
        abort_unless($patient, 403, 'No patient chart is linked to this account.');

        return $patient;
    }

    private function state(Request $request): ?string
    {
        $state = (string) $request->query('state', '');

        return in_array($state, ['success', 'skeleton', 'empty', 'error'], true) ? $state : null;
    }
}