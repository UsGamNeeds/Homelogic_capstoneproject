import React, { createContext, useContext, useEffect } from 'react';
import { Toaster, toast as sonnerToast } from 'sonner';

const ToastContext = createContext(null);

export function ToastProvider({ children }) {
    // Make toast available globally
    useEffect(() => {
        window.toast = sonnerToast;
        return () => {
            delete window.toast;
        };
    }, []);

    // Create toast methods that use Sonner with backward compatibility
    const toast = {
        success: (title, message, options = {}) => {
            return sonnerToast.success(message || title, {
                description: message ? title : undefined,
                ...options,
            });
        },
        error: (title, message, options = {}) => {
            return sonnerToast.error(message || title, {
                description: message ? title : undefined,
                ...options,
            });
        },
        warning: (title, message, options = {}) => {
            return sonnerToast.warning(message || title, {
                description: message ? title : undefined,
                ...options,
            });
        },
        info: (title, message, options = {}) => {
            return sonnerToast.info(message || title, {
                description: message ? title : undefined,
                ...options,
            });
        },
        showToast: (message, type = 'success', options = {}) => {
            if (typeof type === 'string') {
                const toastFn = sonnerToast[type] || sonnerToast.success;
                return toastFn(message, options);
            } else {
                return sonnerToast.success(message, type || {});
            }
        },
        // Legacy methods for backward compatibility
        addToast: (toastData) => {
            const { type = 'info', title, message, ...rest } = toastData;
            const toastFn = sonnerToast[type] || sonnerToast.info;
            return toastFn(message || title, {
                description: message ? title : undefined,
                ...rest,
            });
        },
        removeToast: () => {}, // Sonner handles this automatically
        toasts: [], // Empty for backward compatibility
    };

    return (
        <ToastContext.Provider value={toast}>
            {children}
            <Toaster
                position="top-right"
                richColors
                closeButton
                toastOptions={{
                    style: {
                        background: 'white',
                        border: '1px solid #e5e7eb',
                    },
                    classNames: {
                        toast: 'shadow-lg',
                        title: 'font-semibold',
                        description: 'text-sm text-gray-600',
                    },
                }}
            />
        </ToastContext.Provider>
    );
}

export function useToastContext() {
    const context = useContext(ToastContext);
    if (!context) {
        throw new Error('useToastContext must be used within ToastProvider');
    }
    return context;
}











