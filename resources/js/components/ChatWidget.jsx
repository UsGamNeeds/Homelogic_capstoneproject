import { useState } from 'react';
import { MessageCircle, X, Send } from 'lucide-react';
import { useAssistant } from '../hooks/useAssistant';

export default function ChatWidget() {
    const [open, setOpen] = useState(false);
    const [input, setInput] = useState('');
    const [messages, setMessages] = useState([]); // { role: 'user'|'bot', text }
    const assistant = useAssistant();

    const send = (e) => {
        e.preventDefault();
        const text = input.trim();
        if (!text || assistant.isPending) return;

        setMessages((m) => [...m, { role: 'user', text }]);
        setInput('');

        assistant.mutate(text, {
            onSuccess: (result) => {
                setMessages((m) => [...m, { role: 'bot', text: result.answer }]);
            },
            onError: () => {
                setMessages((m) => [
                    ...m,
                    { role: 'bot', text: "Sorry, I couldn't answer that right now." },
                ]);
            },
        });
    };

    return (
        <>
            {open && (
                <div className="fixed bottom-24 right-6 z-[100] flex h-[28rem] w-80 flex-col rounded-xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900">
                    <div className="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                        <span className="font-semibold text-gray-800 dark:text-gray-100">Assistant</span>
                        <button onClick={() => setOpen(false)} aria-label="Close assistant">
                            <X className="h-5 w-5 text-gray-500" />
                        </button>
                    </div>

                    <div className="flex-1 space-y-2 overflow-y-auto px-4 py-3">
                        {messages.length === 0 && (
                            <p className="text-sm text-gray-400">
                                Ask me a resident's meds for today, or their PRN meds.
                            </p>
                        )}
                        {messages.map((m, i) => (
                            <div
                                key={i}
                                className={
                                    m.role === 'user'
                                        ? 'ml-auto max-w-[85%] whitespace-pre-line rounded-lg bg-[var(--theme-primary,#2563eb)] px-3 py-2 text-sm text-white'
                                        : 'mr-auto max-w-[85%] whitespace-pre-line rounded-lg bg-gray-100 px-3 py-2 text-sm text-gray-800 dark:bg-gray-800 dark:text-gray-100'
                                }
                            >
                                {m.text}
                            </div>
                        ))}
                        {assistant.isPending && <p className="text-xs text-gray-400">Thinking…</p>}
                    </div>

                    <form onSubmit={send} className="flex items-center gap-2 border-t border-gray-200 p-3 dark:border-gray-700">
                        <input
                            value={input}
                            onChange={(e) => setInput(e.target.value)}
                            placeholder="Ask about a resident…"
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
