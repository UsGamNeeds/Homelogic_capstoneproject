import { useEffect, useState } from 'react';
import { Bot, X, Send, MessageCircle } from 'lucide-react';
import { useAssistant, useStarterSuggestions } from '../../hooks/useAssistant';
import { useAssistantResident } from '../../contexts/AssistantResidentContext';
import Chip from './Chip';
import MedResult from './MedResult';
import PrnResult from './PrnResult';
import AllMedsResult from './AllMedsResult';

function BotContent({ msg }) {
    const hasData = Array.isArray(msg.data) && msg.data.length > 0;
    if (msg.intent === 'todays_meds' && hasData) {
        return <MedResult answer={msg.answer} meds={msg.data} />;
    }
    if (msg.intent === 'all_medications' && hasData) {
        return <AllMedsResult answer={msg.answer} meds={msg.data} />;
    }
    if (msg.intent === 'prn_status' && hasData) {
        return <PrnResult answer={msg.answer} meds={msg.data} />;
    }
    return (
        <div className="mr-auto max-w-[85%] whitespace-pre-line rounded-lg bg-gray-100 px-3 py-2 text-sm text-gray-800 dark:bg-gray-800 dark:text-gray-100">
            {msg.answer}
        </div>
    );
}

function TypingDots() {
    return (
        <div className="mr-auto flex items-center gap-1 px-1 py-2" aria-label="Assistant is typing">
            <span className="h-2 w-2 animate-bounce rounded-full bg-gray-400 [animation-delay:-0.3s]" />
            <span className="h-2 w-2 animate-bounce rounded-full bg-gray-400 [animation-delay:-0.15s]" />
            <span className="h-2 w-2 animate-bounce rounded-full bg-gray-400" />
        </div>
    );
}

export default function ChatWidget() {
    const [open, setOpen] = useState(false);
    const [input, setInput] = useState('');
    const [messages, setMessages] = useState([]);
    const [activeResident, setActiveResident] = useState(null); // { id, name } | null
    const assistant = useAssistant();
    const starter = useStarterSuggestions(open && messages.length === 0);
    const { pageResident } = useAssistantResident();

    // Seed the active resident from the page when opening a fresh chat.
    useEffect(() => {
        if (open && messages.length === 0 && pageResident && !activeResident) {
            setActiveResident(pageResident);
        }
    }, [open, pageResident, messages.length, activeResident]);

    const sendMessage = (text) => {
        const t = (text || '').trim();
        if (!t || assistant.isPending) return;

        setMessages((m) => [...m, { role: 'user', text: t }]);
        setInput('');

        assistant.mutate(
            { message: t, contextResidentId: activeResident?.id ?? null },
            {
                onSuccess: (result) => {
                    if (result.resident) {
                        setActiveResident(result.resident);
                    }
                    setMessages((m) => [
                        ...m,
                        {
                            role: 'bot',
                            intent: result.intent,
                            answer: result.answer,
                            data: result.data ?? [],
                            suggestions: result.suggestions ?? [],
                        },
                    ]);
                },
                onError: () => {
                    setMessages((m) => [
                        ...m,
                        {
                            role: 'bot',
                            intent: 'unknown',
                            answer: "Sorry, I couldn't answer that right now.",
                            data: [],
                            suggestions: [],
                        },
                    ]);
                },
            },
        );
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        sendMessage(input);
    };

    const starterChips = starter.data ?? [];

    return (
        <>
            {open && (
                <div className="fixed inset-y-0 right-0 z-[100] flex w-full flex-col border-l border-gray-200 bg-white shadow-2xl sm:w-[400px] dark:border-gray-700 dark:bg-gray-900">
                    <div className="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                        <div className="flex items-center gap-2">
                            <span className="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--theme-primary,#2563eb)] text-white">
                                <Bot className="h-5 w-5" />
                            </span>
                            <div className="leading-tight">
                                <div className="text-sm font-semibold text-gray-800 dark:text-gray-100">Assistant</div>
                                {activeResident ? (
                                    <div className="flex items-center gap-1 text-xs text-gray-400">
                                        <span>About: {activeResident.name}</span>
                                        <button
                                            type="button"
                                            onClick={() => setActiveResident(null)}
                                            className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                            aria-label="Clear active resident"
                                        >
                                            <X className="h-3 w-3" />
                                        </button>
                                    </div>
                                ) : (
                                    <div className="text-xs text-gray-400">Meds &amp; PRN helper</div>
                                )}
                            </div>
                        </div>
                        <button onClick={() => setOpen(false)} aria-label="Close assistant">
                            <X className="h-5 w-5 text-gray-500" />
                        </button>
                    </div>

                    <div className="flex-1 space-y-3 overflow-y-auto px-4 py-3">
                        {messages.length === 0 && (
                            <div className="space-y-3">
                                <p className="text-sm text-gray-400">
                                    Ask me a resident's meds for today, all their meds, or their PRN meds.
                                </p>
                                {starterChips.length > 0 && (
                                    <div className="flex flex-wrap gap-2">
                                        {starterChips.map((c, i) => (
                                            <Chip
                                                key={i}
                                                label={c.label}
                                                disabled={assistant.isPending}
                                                onClick={() => sendMessage(c.message)}
                                            />
                                        ))}
                                    </div>
                                )}
                            </div>
                        )}

                        {messages.map((m, i) => (
                            <div key={i} className="space-y-2">
                                {m.role === 'user' ? (
                                    <div className="ml-auto max-w-[85%] whitespace-pre-line rounded-lg bg-[var(--theme-primary,#2563eb)] px-3 py-2 text-sm text-white">
                                        {m.text}
                                    </div>
                                ) : (
                                    <BotContent msg={m} />
                                )}
                                {m.role === 'bot' && m.suggestions && m.suggestions.length > 0 && (
                                    <div className="mr-auto flex max-w-[85%] flex-wrap gap-2">
                                        {m.suggestions.map((c, j) => (
                                            <Chip
                                                key={j}
                                                label={c.label}
                                                disabled={assistant.isPending}
                                                onClick={() => sendMessage(c.message)}
                                            />
                                        ))}
                                    </div>
                                )}
                            </div>
                        ))}

                        {assistant.isPending && <TypingDots />}
                    </div>

                    <form onSubmit={handleSubmit} className="flex items-center gap-2 border-t border-gray-200 p-3 dark:border-gray-700">
                        <input
                            value={input}
                            onChange={(e) => setInput(e.target.value)}
                            placeholder="Ask about a resident…"
                            aria-label="Message"
                            className="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                        />
                        <button type="submit" disabled={assistant.isPending} aria-label="Send">
                            <Send className="h-5 w-5 text-[var(--theme-primary,#2563eb)]" />
                        </button>
                    </form>
                </div>
            )}

            <button
                onClick={() => setOpen((o) => !o)}
                aria-label="Open assistant"
                className="fixed bottom-6 right-6 z-[100] flex h-14 w-14 items-center justify-center rounded-full bg-[var(--theme-primary,#2563eb)] text-white shadow-lg hover:opacity-90"
            >
                {open ? <X className="h-6 w-6" /> : <MessageCircle className="h-6 w-6" />}
            </button>
        </>
    );
}
