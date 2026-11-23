/**
 * AppErrorBoundary — COMPONENT-SPEC §15/§17 global error+retry handler.
 * Catches render failures and offers a full-page Retry without losing the URL.
 */
import { Component, type ErrorInfo, type ReactNode } from 'react';

interface Props {
    children: ReactNode;
}

interface State {
    hasError: boolean;
    message: string;
    lastKnownAt: string;
}

export class AppErrorBoundary extends Component<Props, State> {
    state: State = { hasError: false, message: '', lastKnownAt: '' };

    static getDerivedStateFromError(error: Error): State {
        return {
            hasError: true,
            message: error.message || 'An unexpected error occurred.',
            lastKnownAt: new Date().toISOString(),
        };
    }

    componentDidCatch(error: Error, info: ErrorInfo): void {
        // eslint-disable-next-line no-console
        console.error('MediPath UI error', error, info.componentStack);
    }

    render() {
        if (!this.state.hasError) return this.props.children;

        return (
            <main id="main" className="container" style={{ paddingBlock: 'var(--mp-space-2xl)' }}>
                <div className="card card-pad stack" role="alert">
                    <h1 className="h2">Something went wrong on this screen</h1>
                    <p className="muted">
                        The rest of MediPath is still available. Retry the page — your filters and URL are preserved.
                    </p>
                    <p className="caption">
                        Error: {this.state.message}
                    </p>
                    <div className="mp-row">
                        <button type="button" className="btn btn-primary" onClick={() => window.location.reload()}>
                            Retry
                        </button>
                        <a className="btn btn-quiet" href="/">Go to a safe page</a>
                    </div>
                </div>
            </main>
        );
    }
}