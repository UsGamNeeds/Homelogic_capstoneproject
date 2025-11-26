import React, { useEffect, useRef } from 'react';
import Toast from './Toast';
import { staggerAnimation, slideInRight, shouldAnimate } from '../../utils/animationPresets';

export default function ToastContainer({ toasts, onClose }) {
    const containerRef = useRef(null);
    const prevToastsLengthRef = useRef(0);

    useEffect(() => {
        if (!containerRef.current || !shouldAnimate()) return;

        // Only animate new toasts (when count increases)
        if (toasts.length > prevToastsLengthRef.current) {
            const toastElements = containerRef.current.querySelectorAll('[role="alert"]');
            if (toastElements.length > 0) {
                // Animate only the new toast(s)
                const newToasts = Array.from(toastElements).slice(prevToastsLengthRef.current);
                if (newToasts.length > 0) {
                    staggerAnimation(newToasts, slideInRight, {
                        staggerDelay: 100,
                        duration: 400,
                        easing: 'easeOutExpo',
                    });
                }
            }
        }

        prevToastsLengthRef.current = toasts.length;
    }, [toasts.length]);

    if (toasts.length === 0) return null;

    return (
        <div
            ref={containerRef}
            className="fixed top-4 right-4 z-50 flex flex-col items-end space-y-2 pointer-events-none"
            aria-live="polite"
            aria-label="Notifications"
        >
            {toasts.map((toast) => (
                <div key={toast.id} className="pointer-events-auto">
                    <Toast toast={toast} onClose={onClose} />
                </div>
            ))}
        </div>
    );
}











