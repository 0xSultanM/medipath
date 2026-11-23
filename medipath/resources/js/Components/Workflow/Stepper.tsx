/**
 * Stepper (Stepper6 / Stepper3) + WorkflowTracker — COMPONENT-SPEC §14.
 * Vertical <640 / horizontal ≥640 via CSS; blocked stages carry reason text;
 * tracker supports jump-to-stage. Reproduces pharmacist/08-workflow-tracker,
 * guest/08-pending (3-step) and doctor/05 workflow summaries.
 */
import type { DataState } from '@/types';
import { cn } from '@/lib/format';

export interface Step {
    key: string;
    label: string;
    sub?: string | null;
    state: 'done' | 'current' | 'upcoming' | 'blocked';
    reason?: string | null;
}

interface StepperProps {
    steps: Step[];
    state?: DataState;
    onRetry?: () => void;
    ariaLabel?: string;
}

export function Stepper({ steps, state = 'success', onRetry, ariaLabel = 'Progress' }: StepperProps) {
    if (state === 'skeleton') {
        return (
            <div className="stepper" aria-busy="true">
                {Array.from({ length: steps.length || 6 }).map((_, index) => (
                    <div className="stp" key={index}>
                        <span className="sk sk-circle" style={{ width: 32, height: 32 }} aria-hidden="true" />
                        <span className="s-txt">
                            <span className="sk sk-line" style={{ width: 90 }} aria-hidden="true" />
                            <span className="sk sk-line" style={{ width: 60 }} aria-hidden="true" />
                        </span>
                    </div>
                ))}
            </div>
        );
    }

    if (state === 'error') {
        return (
            <div className="alert alert-danger" role="alert">
                <span className="a-ico" aria-hidden="true">⚠</span>
                <div className="a-body">
                    <strong className="a-title">Workflow stages could not be loaded.</strong>
                    <span className="caption">Showing static stages; status may be stale.</span>
                    <button type="button" className="btn btn-ghost btn-sm" onClick={onRetry}>Retry</button>
                </div>
            </div>
        );
    }

    if (state === 'empty' || steps.length === 0) {
        return (
            <p className="caption" role="status">
                No workflow yet — the tracker appears as soon as the visit moves past registration.
            </p>
        );
    }

    return (
        <ol className="stepper" aria-label={ariaLabel}>
            {steps.map((step) => (
                <li
                    key={step.key}
                    className={cn(
                        'stp',
                        step.state === 'done' && 'is-done',
                        step.state === 'current' && 'is-current',
                        step.state === 'blocked' && 'is-blocked',
                    )}
                    aria-current={step.state === 'current' ? 'step' : undefined}
                >
                    <span className="dot" aria-hidden="true">
                        {step.state === 'done' ? '✓' : step.state === 'blocked' ? '!' : ''}
                    </span>
                    <span className="s-txt">
                        <span className="s-name">{step.label}</span>
                        {step.state === 'blocked' && step.reason ? (
                            <span className="badge badge-danger badge-sm" title={step.reason}>
                                Blocked · {step.reason}
                            </span>
                        ) : step.sub ? (
                            <span className="s-sub">{step.sub}</span>
                        ) : null}
                    </span>
                </li>
            ))}
        </ol>
    );
}

export function WorkflowTracker({
    steps,
    state = 'success',
    onRetry,
    onJump,
    activeKey,
}: StepperProps & { onJump?: (key: string) => void; activeKey?: string }) {
    if (state === 'skeleton') {
        return <Stepper steps={steps.length ? steps : defaultWorkflow()} state="skeleton" />;
    }

    if (state === 'error') {
        return <Stepper steps={defaultWorkflow()} state="error" onRetry={onRetry} />;
    }

    if (state === 'empty' || steps.length === 0) {
        return <Stepper steps={[]} state="empty" />;
    }

    return (
        <ol className="stepper flow6" aria-label="Clinical workflow tracker">
            {steps.map((step) => {
                const interactive = Boolean(onJump);
                const content = (
                    <>
                        <span className="dot" aria-hidden="true">
                            {step.state === 'done' ? '✓' : step.state === 'blocked' ? '!' : ''}
                        </span>
                        <span className="s-txt">
                            <span className="s-name">{step.label}</span>
                            {step.sub ? <span className="s-sub">{step.sub}</span> : null}
                            {step.state === 'blocked' && step.reason ? (
                                <span className="s-sub" style={{ color: 'var(--mp-danger-text)', fontWeight: 600 }}>
                                    {step.reason}
                                </span>
                            ) : null}
                        </span>
                    </>
                );

                return (
                    <li
                        key={step.key}
                        className={cn(
                            'stp',
                            step.state === 'done' && 'is-done',
                            step.state === 'current' && 'is-current',
                            step.state === 'blocked' && 'is-blocked',
                            interactive && 'is-interactive',
                            activeKey === step.key && 'is-viewed',
                        )}
                        aria-current={step.state === 'current' ? 'step' : undefined}
                    >
                        {interactive ? (
                            <button
                                type="button"
                                className="stp-hit"
                                onClick={() => onJump?.(step.key)}
                                aria-label={`Jump to ${step.label}${step.state === 'blocked' ? ` — blocked: ${step.reason}` : ''}`}
                            >
                                {content}
                            </button>
                        ) : (
                            content
                        )}
                    </li>
                );
            })}
        </ol>
    );
}

export function defaultWorkflow(): Step[] {
    return [
        { key: 'registration', label: 'Registration', state: 'done' },
        { key: 'prescription', label: 'Prescription', state: 'done' },
        { key: 'stock', label: 'Stock Check', state: 'current' },
        { key: 'dispense', label: 'Dispense', state: 'upcoming' },
        { key: 'billing', label: 'Billing', state: 'upcoming' },
        { key: 'discharged', label: 'Discharged', state: 'upcoming' },
    ];
}