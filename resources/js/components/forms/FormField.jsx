import React from 'react';
import TooltipLabel from '../ui/TooltipLabel';

/**
 * FormField - Wrapper component for form fields with label, error, and tooltip support
 */
export default function FormField({ label, name, error, required = false, hint, children, className = '' }) {
    return (
        <div className={className}>
            {label && (
                <TooltipLabel
                    htmlFor={name}
                    label={label}
                    required={required}
                    tooltip={hint}
                />
            )}
            {children}
            {error && (
                <p className="text-xs text-red-600 mt-1" role="alert">
                    {error}
                </p>
            )}
        </div>
    );
}
