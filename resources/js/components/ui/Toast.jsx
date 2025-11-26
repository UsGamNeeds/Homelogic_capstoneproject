import React, { useEffect, useRef } from 'react';
import { X, CheckCircle, AlertCircle, Info, AlertTriangle } from 'lucide-react';
import { slideInRight, slideInRight as slideOutRight, fadeOut, shouldAnimate } from '../../utils/animationPresets';

const TOAST_TYPES = {
    success: {
        icon: CheckCircle,
        bgColor: 'bg-green-50',
        borderColor: 'border-green-200',
        textColor: 'text-green-800',
        iconColor: 'text-green-600',
    },
    error: {
        icon: AlertCircle,
        bgColor: 'bg-red-50',
        borderColor: 'border-red-200',
        textColor: 'text-red-800',
        iconColor: 'text-red-600',
    },
    warning: {
        icon: AlertTriangle,
        bgColor: 'bg-amber-50',
        borderColor: 'border-amber-200',
        textColor: 'text-amber-800',
        iconColor: 'text-amber-600',
    },
    info: {
        icon: Info,
        bgColor: 'bg-blue-50',
        borderColor: 'border-blue-200',
        textColor: 'text-blue-800',
        iconColor: 'text-blue-600',
    },
};

export default function Toast({ toast, onClose }) {
    const { id, type = 'info', title, message, duration = 5000 } = toast;
    const config = TOAST_TYPES[type] || TOAST_TYPES.info;
    const Icon = config.icon;
    const toastRef = useRef(null);
    const animationRef = useRef(null);

    // Animate entrance
    useEffect(() => {
        if (toastRef.current && shouldAnimate()) {
            // Set initial state
            toastRef.current.style.opacity = '0';
            toastRef.current.style.transform = 'translateX(100%)';

            // Animate in
            animationRef.current = slideInRight(toastRef.current, {
                duration: 400,
                easing: 'easeOutExpo',
            });
        }
    }, []);

    // Handle auto-dismiss with exit animation
    useEffect(() => {
        if (duration > 0) {
            const timer = setTimeout(() => {
                handleClose();
            }, duration);

            return () => clearTimeout(timer);
        }
    }, [id, duration]);

    const handleClose = () => {
        if (toastRef.current && shouldAnimate()) {
            // Animate out
            const exitAnim = fadeOut(toastRef.current, {
                duration: 250,
                easing: 'easeInQuad',
            });

            // Wait for animation to complete before removing
            setTimeout(() => {
                onClose(id);
            }, 250);
        } else {
            onClose(id);
        }
    };

    return (
        <div
            ref={toastRef}
            className={`${config.bgColor} ${config.borderColor} border rounded-lg shadow-lg p-4 min-w-[300px] max-w-md mb-3`}
            role="alert"
            aria-live="polite"
        >
            <div className="flex items-start space-x-3">
                <Icon className={`w-5 h-5 ${config.iconColor} flex-shrink-0 mt-0.5`} />
                <div className="flex-1 min-w-0">
                    {title && (
                        <p className={`${config.textColor} font-semibold text-sm mb-1`}>
                            {title}
                        </p>
                    )}
                    {message && (
                        <p className={`${config.textColor} text-sm`}>
                            {message}
                        </p>
                    )}
                </div>
                <button
                    onClick={handleClose}
                    className={`${config.textColor} hover:opacity-70 transition-opacity flex-shrink-0`}
                    aria-label="Close notification"
                >
                    <X className="w-4 h-4" />
                </button>
            </div>
        </div>
    );
}











