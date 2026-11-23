import type { ChatReply, ChatTransport } from '@/Components/AI/ChatbotWidget';
import { postJson } from '@/lib/http';

interface ApiChatReply {
    kind: 'answer' | 'emergency';
    message: string;
    title?: string;
    category?: string;
    rule_id?: number;
    caption?: string;
    source?: string;
    suggestions?: string[];
    contacts?: Array<{
        key: string;
        label: string;
        number: string;
        tel: string;
        icon?: string | null;
    }>;
}

/** Shared chatbot transport for all guest pages. */
export function createChatbotTransport(): ChatTransport {
    return async (message): Promise<ChatReply> => {
        const data = await postJson<ApiChatReply>('/api/chatbot', { message });

        if (data.kind === 'emergency') {
            return {
                text: data.message,
                emergency: {
                    title: data.title ?? 'Emergency red flag detected',
                    directive: data.message,
                    ruleId: data.rule_id,
                    contacts: data.contacts ?? [],
                },
            };
        }

        return {
            text: data.message,
            caption: data.caption ?? 'General guidance only — not a diagnosis.',
            source: data.source === 'fallback' ? 'faq' : 'faq',
        };
    };
}

export const CHATBOT_SUGGESTIONS = [
    'How do I book an appointment?',
    'Can I download my prescription?',
    'What is the cancellation policy?',
];

export const CHATBOT_OFFLINE_ANSWER =
    'Appointments are booked from the doctor directory. Bring your patient code to the clinic. '
    + 'Prescriptions are checked for allergies and drug interactions before dispensing. '
    + 'For an emergency, call the emergency number immediately.';