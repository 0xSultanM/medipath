<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectApprovalRequest;
use App\Http\Requests\Admin\VerifyDoctorRequest;
use App\Models\Doctor;
use App\Models\PendingApproval;
use App\Services\ActivityLogService;
use App\Services\AdminApprovalService;
use App\Support\ActivityActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** Approval queue and doctor verification decisions (admin/03). */
class ApprovalController extends Controller
{
    public function __construct(
        private readonly AdminApprovalService $approvals,
        private readonly ActivityLogService $activity,
    ) {}

    public function index(Request $request): Response
    {
        $tab = $request->query('tab') === 'verification' ? 'verification' : 'signups';

        $pending = PendingApproval::query()
            ->pending()
            ->with(['user' => fn ($query) => $query->with(['doctor.specialty', 'pharmacist'])])
            ->latest()
            ->get()
            ->map(function (PendingApproval $approval): array {
                $details = $approval->license_details ?? [];
                $user = $approval->user;

                return [
                    'id' => $approval->id,
                    'applicantName' => $user?->name,
                    'initials' => $user?->initials,
                    'email' => $user?->email,
                    'phone' => $user?->phone,
                    'requestedRole' => $approval->requested_role,
                    'requestedRoleLabel' => config("medipath.roles.{$approval->requested_role}.label", ucfirst($approval->requested_role)),
                    'specialty' => $user?->doctor?->specialty?->name,
                    'licenseNumber' => $details['license_number'] ?? $user?->doctor?->license_number,
                    'licenseAuthority' => $details['license_authority'] ?? $user?->doctor?->license_authority,
                    'experienceYears' => $user?->doctor?->experience_years,
                    'submittedAt' => $approval->created_at?->toIso8601String(),
                    'credentialAvailable' => filled($approval->credential_path),
                    'credentialUrl' => $approval->credential_path
                        ? route('admin.approvals.credential', $approval)
                        : null,
                ];
            })
            ->values();

        $doctors = Doctor::query()
            ->with(['user', 'specialty'])
            ->orderByRaw("FIELD(verification_status, 'unverified','pending','resubmission','revoked','verified')")
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(fn (Doctor $doctor): array => [
                'id' => $doctor->id,
                'name' => $doctor->user?->name,
                'initials' => $doctor->user?->initials,
                'email' => $doctor->user?->email,
                'specialty' => $doctor->specialty?->name,
                'licenseNumber' => $doctor->license_number,
                'licenseAuthority' => $doctor->license_authority,
                'verificationStatus' => $doctor->verification_status?->value,
                'verificationLabel' => $doctor->verification_status?->label(),
                'reviewReason' => $doctor->review_reason,
                'verifiedAt' => $doctor->verified_at?->toIso8601String(),
                'archivedAt' => $doctor->archived_at?->toIso8601String(),
                'credentialAvailable' => filled($doctor->credential_path),
                'credentialUrl' => $doctor->credential_path
                    ? route('admin.doctors.credential', $doctor)
                    : null,
                'canVerify' => $doctor->verification_status !== VerificationStatus::Verified,
            ])
            ->values();

        $forced = (string) $request->query('state', '');
        $activeCount = $tab === 'signups' ? $pending->count() : $doctors->count();
        $state = in_array($forced, ['success', 'skeleton', 'empty', 'error'], true)
            ? $forced
            : ($activeCount === 0 ? 'empty' : 'success');

        return Inertia::render('Admin/Approvals', [
            'tab' => $tab,
            'pending' => $pending,
            'doctors' => $doctors,
            'counts' => [
                'signups' => $pending->count(),
                'verification' => $doctors->where('canVerify', true)->count(),
            ],
            'dataState' => $state,
            'lastKnownAt' => now()->toIso8601String(),
        ]);
    }

    public function approve(Request $request, PendingApproval $approval): RedirectResponse
    {
        $this->approvals->approveSignup($approval, $request->user());

        return back()->with('success', 'Sign-up approved. The applicant was notified and their sessions were refreshed.');
    }

    public function reject(RejectApprovalRequest $request, PendingApproval $approval): RedirectResponse
    {
        $this->approvals->rejectSignup($approval, (string) $request->validated('review_reason'), $request->user());

        return back()->with('success', 'Application rejected with a reason. The applicant can read it on the holding screen.');
    }

    public function credential(Request $request, PendingApproval $approval): HttpResponse|BinaryFileResponse
    {
        $this->activity->record(ActivityActions::CREDENTIAL_PREVIEWED, $approval, [
            'applicant_id' => $approval->user_id,
            'source' => 'approval_queue',
        ], $request->user());

        $details = $approval->license_details ?? [];

        return $this->inlineCredential(
            $approval->credential_path,
            (string) ($details['license_number'] ?? '—'),
            (string) ($details['license_authority'] ?? '—'),
            (string) ($approval->user?->name ?? 'Applicant'),
        );
    }

    public function doctorCredential(Request $request, Doctor $doctor): HttpResponse|BinaryFileResponse
    {
        $this->activity->record(ActivityActions::CREDENTIAL_PREVIEWED, $doctor, [
            'doctor_id' => $doctor->id,
            'source' => 'doctor_verification',
        ], $request->user());

        return $this->inlineCredential(
            $doctor->credential_path,
            (string) $doctor->license_number,
            (string) $doctor->license_authority,
            (string) ($doctor->user?->name ?? 'Doctor'),
        );
    }

    public function verify(VerifyDoctorRequest $request, Doctor $doctor): RedirectResponse
    {
        $decision = (string) $request->validated('decision');
        $reason = (string) ($request->validated('reason') ?? '');

        $this->approvals->verifyDoctor($doctor, $decision, $reason, $request->user());

        $message = match ($decision) {
            'approve' => 'License verified. Booking access is now open for this doctor.',
            'resubmit' => 'Resubmission requested. The doctor was notified with the reason.',
            default => 'Verification revoked. The directory and booking access hide immediately.',
        };

        return back()->with('success', $message);
    }

    private function inlineCredential(?string $path, string $license, string $authority, string $name): HttpResponse|BinaryFileResponse
    {
        if ($path) {
            $disk = Storage::disk('credentials');

            if ($disk->exists($path)) {
                $absolute = $disk->path($path);
                $mime = mime_content_type($absolute) ?: 'application/octet-stream';

                return response()->file($absolute, [
                    'Content-Type' => $mime,
                    'Content-Disposition' => 'inline; filename="credential-preview"',
                    'Cache-Control' => 'no-store, private, max-age=0',
                    'X-Content-Type-Options' => 'nosniff',
                    'X-Frame-Options' => 'SAMEORIGIN',
                ]);
            }
        }

        // Seeded demo approvals reference a placeholder path. Render a clearly
        // marked, escaped, script-free preview so administrators can exercise
        // the private-preview flow without a binary upload.
        $e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $html = <<<HTML
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Credential preview</title>
<style>
body{font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;margin:0;padding:40px;background:#f4f6f8;color:#16202a}
.page{max-width:720px;margin:auto;background:#fff;border:2px solid #c9d2dc;border-radius:14px;padding:40px;position:relative;overflow:hidden}
.watermark{position:absolute;inset:0;display:grid;place-items:center;transform:rotate(-24deg);font-size:42px;font-weight:800;color:rgba(13,110,253,.08);letter-spacing:.08em;pointer-events:none;text-align:center}
h1{font-size:22px;margin:0 0 4px}p{line-height:1.6;margin:6px 0}dt{font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:#5c6b7a}dd{margin:0 0 14px;font-weight:600}
.note{background:#fff8e6;border:1px solid #f0d58a;border-radius:10px;padding:12px 14px;font-size:13px;margin-top:24px}
</style></head>
<body><div class="page"><div class="watermark">DEMO PREVIEW</div>
<p style="color:#5c6b7a;font-size:12px;font-weight:700;letter-spacing:.08em">MEDIPATH · PRIVATE CREDENTIAL PREVIEW</p>
<h1>{$e($name)}</h1>
<p>Professional license credential submitted for verification.</p>
<dl style="margin-top:24px"><dt>License number</dt><dd>{$e($license)}</dd><dt>Issuing authority</dt><dd>{$e($authority)}</dd><dt>Access</dt><dd>Administrator only · served from the private credentials disk</dd></dl>
<div class="note">No binary document was stored for this seeded approval. This generated preview stands in for the uploaded page 1; a live submission renders the real file inline with the same access rules and audit stamp.</div>
</div></body></html>
HTML;

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, private, max-age=0',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'",
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }
}