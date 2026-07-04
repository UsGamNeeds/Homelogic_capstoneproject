import { useState } from 'react';
import { MessageCircle, X, Send } from 'lucide-react';
import { useAssistant, useStarterSuggestions } from '../hooks/useAssistant';

function Chip({ label, onClick, disabled }) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            className="rounded-full border border-[var(--theme-primary,#2563eb)] px-3 py-1 text-xs font-medium text-[var(--theme-primary,#2563eb)] hover:bg-[var(--theme-primary,#2563eb)] hover:text-white disabled:opacity-50"
        >
            {label}
        </button>
    );
}

export default function ChatWidget() {
    const [open, setOpen] = useState(false);
    const [input, setInput] = useState('');
    const [messages, setMessages] = useState([]); // { role: 'user'|'bot', text, suggestions? }
    const assistant = useAssistant();
    const starter = useStarterSuggestions(open && messages.length === 0);

    const sendMessage = (text) => {
        const t = (text || '').trim();
        if (!t || assistant.isPending) return;

        setMessages((m) => [...m, { role: 'user', text: t }]);
        setInput('');

        assistant.mutate(t, {
            onSuccess: (result) => {
                setMessages((m) => [
                    ...m,
                    { role: 'bot', text: result.answer, suggestions: result.suggestions ?? [] },
                ]);
            },
            onError: () => {
                setMessages((m) => [
                    ...m,
                    { role: 'bot', text: "Sorry, I couldn't answer that right now." },
                ]);
            },
        });
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
                        <span className="font-semibold text-gray-800 dark:text-gray-100">Assistant</span>
                        <button onClick={() => setOpen(false)} aria-label="Close assistant">
                            <X className="h-5 w-5 text-gray-500" />
                        </button>
                    </div>

                    <div className="flex-1 space-y-3 overflow-y-auto px-4 py-3">
                        {messages.length === 0 && (
                            <div className="space-y-3">
                                <p className="text-sm text-gray-400">
                                    Ask me a resident's meds for today, or their PRN meds.
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
                                <div
                                    className={
                                        m.role === 'user'
                                            ? 'ml-auto max-w-[85%] whitespace-pre-line rounded-lg bg-[var(--theme-primary,#2563eb)] px-3 py-2 text-sm text-white'
                                            : 'mr-auto max-w-[85%] whitespace-pre-line rounded-lg bg-gray-100 px-3 py-2 text-sm text-gray-800 dark:bg-gray-800 dark:text-gray-100'
                                    }
                                >
                                    {m.text}
                                </div>
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

                        {assistant.isPending && <p className="text-xs text-gray-400">Thinking…</p>}
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
