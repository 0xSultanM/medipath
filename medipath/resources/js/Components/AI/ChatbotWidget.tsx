/**
 * ChatbotWidget — COMPONENT-SPEC §11 (FAQ chatbot with mandatory guardrails).
 * Pinned, non-dismissible disclaimer; aria-live stream; deterministic red-flag
 * intercept rendered as the in-stream `.msg-emergency` variant; AI-offline
 * fallback + Retry. The transport is injected so the Part 1 API can own the
 * red-flag-before-AI pipeline (G8).
 */
import { useEffect, useId, useRef, useState } from 'react';
import type { EmergencyContactItem } from '@/types';
import { cn } from '@/lib/format';
import { ApiError } from '@/lib/http';
import { useDisclosure, useFocusTrap } from '@/hooks/useUi';

export interface ChatEmergency {
    title: string;
    directive: string;
    contacts: EmergencyContactItem[];
    ruleId?: number;
}

export interface ChatMessage {
    id: string;
    kind: 'bot' | 'user' | 'emergency';
    text: string;
    caption?: string;
    emergency?: ChatEmergency;
}

export interface ChatReply {
    text: string;
    caption?: string;
    emergency?: ChatEmergency;
    source?: 'ai' | 'faq' | 'deterministic';
}

export type ChatTransport = (message: string) => Promise<ChatReply>;

interface ChatbotWidgetProps {
    transport?: ChatTransport;
    suggestions?: string[];
    disclaimer?: string;
    greeting?: string;
    contacts?: EmergencyContactItem[];
    offlineAnswer?: string;
    initialOpen?: boolean;
    label?: string;
    /** Force the error/offline presentation for QA (State=Error). */
    forceOffline?: boolean;
}

const DEFAULT_DISCLAIMER =
    'General guidance only — not a diagnosis. For emergencies call the hotline. AI answers never replace a clinician.';

export function ChatbotWidget({
    transport,
    suggestions = [],
    disclaimer = DEFAULT_DISCLAIMER,
    greeting = 'Hello — I can help with appointments, prescriptions, billing and clinic FAQs. How can I help today?',
    contacts = [],
    offlineAnswer,
    initialOpen = false,
    label = 'Ask MediPath',
    forceOffline = false,
}: ChatbotWidgetProps) {
    const panel = useDisclosure(initialOpen);
    const [messages, setMessages] = useState<ChatMessage[]>([
        { id: 'greeting', kind: 'bot', text: greeting },
    ]);
    const [input, setInput] = useState('');
    const [typing, setTyping] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [halted, setHalted] = useState(false);
    const [lastSent, setLastSent] = useState<string | null>(null);
    const streamRef = useRef<HTMLDivElement | null>(null);
    const panelRef = useFocusTrap(panel.isOpen, panel.close);
    const inputId = useId();

    useEffect(() => {
        if (!panel.isOpen) return;
        streamRef.current?.scrollTo({ top: streamRef.current.scrollHeight, behavior: 'smooth' });
    }, [messages, typing, panel.isOpen]);

    const push = (message: Omit<ChatMessage, 'id'>) =>
        setMessages((current) => [
            ...current,
            { ...message, id: `${Date.now()}-${Math.random().toString(16).slice(2)}` },
        ]);

    const send = async (text: string) => {
        const trimmed = text.trim();
        if (!trimmed || typing || halted) return;

        setLastSent(trimmed);
        setInput('');
        setError(null);
        push({ kind: 'user', text: trimmed });
        setTyping(true);

        try {
            if (forceOffline || !transport) {
                throw new Error('AI assistant unavailable');
            }

            const reply = await transport(trimmed);

            if (reply.emergency) {
                push({
                    kind: 'emergency',
                    text: reply.emergency.directive,
                    emergency: {
                        ...reply.emergency,
                        contacts: reply.emergency.contacts.length > 0 ? reply.emergency.contacts : contacts,
                    },
                });
                setHalted(true);
            } else {
                push({
                    kind: 'bot',
                    text: reply.text,
                    caption: reply.caption ?? 'General guidance only — not a diagnosis.',
                });
            }
        } catch (error) {
            if (error instanceof ApiError && error.status === 429) {
                setError('You are sending messages too quickly. Wait a moment, then retry.');
            } else {
                setError('AI assistant offline — answering from published FAQs.');
            }
            if (offlineAnswer) {
                push({
                    kind: 'bot',
                    text: offlineAnswer,
                    caption: 'Deterministic FAQ answer (AI unavailable). General guidance only.',
                });
            }
        } finally {
            setTyping(false);
        }
    };

    const retry = () => {
        if (lastSent) void send(lastSent);
    };

    return (
        <>
            <div className="bot-fab">
                <button
                    type="button"
                    aria-label={label}
                    aria-expanded={panel.isOpen}
                    onClick={panel.toggle}
                >
                    <span aria-hidden="true">{panel.isOpen ? '✕' : '💬'}</span>
                </button>
            </div>

            <section
                ref={panelRef}
                className={cn('bot-panel', panel.isOpen && 'is-open')}
                role="dialog"
                aria-modal="true"
                aria-label={label}
                hidden={!panel.isOpen}
            >
                <header className="bot-head">
                    <span aria-hidden="true" style={{ fontSize: 20 }}>🤖</span>
                    <div>
                        <div className="t">MediPath assistant</div>
                        <div className="s">Rule-first · AI-assisted</div>
                    </div>
                    <button
                        type="button"
                        className="bot-x"
                        onClick={panel.close}
                        aria-label="Close assistant"
                    >
                        ✕
                    </button>
                </header>

                {/* Pinned disclaimer — outside any dismiss control, always in a11y tree. */}
                <div className="bot-disclaimer" role="note">
                    <span aria-hidden="true">⚠</span>
                    <span>{disclaimer}</span>
                </div>

                <div className="bot-stream" ref={streamRef} aria-live="polite" aria-relevant="additions text">
                    {messages.map((message) =>
                        message.kind === 'emergency' && message.emergency ? (
                            <div className="msg msg-emergency" key={message.id} role="alert">
                                <span className="em-title">
                                    <span aria-hidden="true">🚨</span> Emergency alert
                                </span>
                                <span>{message.emergency.title}</span>
                                <span>{message.emergency.directive}</span>

                                <div className="em-calls">
                                    {message.emergency.contacts.map((contact) => (
                                        <a key={contact.key} href={contact.tel}>
                                            <span aria-hidden="true">{contact.icon ?? '☎'}</span>
                                            {contact.label}: {contact.number}
                                        </a>
                                    ))}
                                </div>

                                <button
                                    type="button"
                                    className="btn btn-quiet btn-sm"
                                    onClick={() => {
                                        setHalted(false);
                                        push({
                                            kind: 'bot',
                                            text: 'Thank you for confirming. I can continue with non-urgent questions, but please seek in-person care if symptoms persist.',
                                            caption: 'General guidance only — not a diagnosis.',
                                        });
                                    }}
                                >
                                    I understand — continue
                                </button>
                            </div>
                        ) : (
                            <div
                                className={cn('msg', message.kind === 'user' ? 'msg-user' : 'msg-bot')}
                                key={message.id}
                            >
                                {message.text}
                                {message.caption ? <span className="msg-caption">{message.caption}</span> : null}
                            </div>
                        ),
                    )}

                    {typing ? (
                        <div className="msg-skeleton" aria-label="Assistant is typing">
                            <i /><i /><i />
                        </div>
                    ) : null}
                </div>

                {error ? (
                    <div className="bot-error" role="alert">
                        <span aria-hidden="true">⚠</span>
                        <span className="txt">{error}</span>
                        <button type="button" className="btn btn-ghost btn-sm" onClick={retry}>
                            Retry
                        </button>
                    </div>
                ) : null}

                {!halted && suggestions.length > 0 ? (
                    <div className="bot-chips" aria-label="Suggested questions">
                        {suggestions.map((suggestion) => (
                            <button type="button" key={suggestion} onClick={() => void send(suggestion)}>
                                {suggestion}
                            </button>
                        ))}
                    </div>
                ) : null}

                <form
                    className="bot-input"
                    onSubmit={(event) => {
                        event.preventDefault();
                        void send(input);
                    }}
                >
                    <label className="sr-only" htmlFor={inputId}>Message the assistant</label>
                    <input
                        id={inputId}
                        className="input"
                        value={input}
                        placeholder={halted ? 'Acknowledge the emergency alert to continue' : 'Type your question…'}
                        onChange={(event) => setInput(event.target.value)}
                        disabled={halted}
                        autoComplete="off"
                    />
                    <button type="submit" className="btn btn-primary" disabled={halted || typing || input.trim() === ''}>
                        Send
                    </button>
                </form>
            </section>
        </>
    );
}