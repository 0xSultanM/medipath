<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Support\ActivityActions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Pharmacist invoice payment-state transitions (PLAN Sprint 8.9). */
class InvoiceService
{
    public function __construct(
        private readonly ActivityLogService $activity,
        private readonly NotificationService $notifications,
        private readonly CurrencyFormatter $money,
    ) {}

    /** @param array{payment_status:string,refund_reason?:string|null} $data */
    public function updateStatus(Invoice $invoice, array $data, User $actor): Invoice
    {
        $target = PaymentStatus::from($data['payment_status']);

        if ($target === PaymentStatus::Refunded && trim((string) ($data['refund_reason'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'refund_reason' => 'A refund reason is required.',
            ]);
        }

        return DB::transaction(function () use ($invoice, $target, $data, $actor): Invoice {
            /** @var Invoice $locked */
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            $locked->forceFill([
                'payment_status' => $target,
                'paid_amount' => $target === PaymentStatus::Paid ? $locked->net_total : $locked->paid_amount,
                'paid_at' => $target === PaymentStatus::Paid ? ($locked->paid_at ?? now()) : $locked->paid_at,
                'refund_reason' => $target === PaymentStatus::Refunded ? $data['refund_reason'] : null,
                'refunded_at' => $target === PaymentStatus::Refunded ? now() : null,
            ])->save();

            $this->activity->record(
                $target === PaymentStatus::Refunded ? ActivityActions::INVOICE_REFUNDED : ActivityActions::INVOICE_PAID,
                $locked,
                [
                    'invoice_code' => $locked->invoice_code,
                    'net_total' => (float) $locked->net_total,
                    'reason' => $data['refund_reason'] ?? null,
                ],
                $actor,
            );

            if ($locked->patient?->user) {
                $this->notifications->send(
                    $locked->patient->user,
                    'invoice.'.$target->value,
                    $target === PaymentStatus::Refunded ? 'Invoice refunded' : 'Invoice paid',
                    $target === PaymentStatus::Refunded
                        ? "Invoice {$locked->invoice_code} was refunded ({$this->money->format((float) $locked->net_total, $locked->currency_code)})."
                        : "Payment received for {$locked->invoice_code}: {$this->money->format((float) $locked->net_total, $locked->currency_code)}.",
                    'billing',
                    ['invoice_id' => $locked->id],
                    '/patient/bills',
                );
            }

            return $locked->fresh(['items.medicine', 'patient', 'prescription']);
        });
    }
}