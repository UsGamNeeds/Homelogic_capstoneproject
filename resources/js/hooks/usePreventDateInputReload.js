import { useEffect, useRef } from 'react';

/**
 * Hook to prevent page reloads when interacting with date inputs
 * This handles the case where native date picker interactions might trigger form submissions
 */
export function usePreventDateInputReload() {
    const containerRef = useRef(null);

    useEffect(() => {
        const container = containerRef.current;
        if (!container) return;

        // Find all date inputs in the container
        const dateInputs = container.querySelectorAll('input[type="date"]');

        const preventDefault = (e) => {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            return false;
        };

        const handleSubmit = (e) => {
            // Check if the submit event originated from within our container
            if (container.contains(e.target)) {
                preventDefault(e);
            }
        };

        const handleFormSubmit = (e) => {
            // Prevent any form submission from within container
            const form = e.target;
            if (container.contains(form)) {
                preventDefault(e);
            }
        };

        const handleKeyDown = (e) => {
            // Prevent Enter key from submitting forms when date inputs are focused
            if (container.contains(e.target) && e.key === 'Enter' && e.target.type === 'date') {
                preventDefault(e);
            }
        };

        const handleClick = (e) => {
            // Prevent clicks on date inputs from triggering form submissions
            if (e.target.type === 'date' && container.contains(e.target)) {
                e.stopPropagation();
            }
        };

        // Add direct event listeners to date inputs
        const changeHandlers = [];
        const inputHandlers = [];
        const clickHandlers = [];

        dateInputs.forEach(input => {
            const changeHandler = (e) => {
                e.stopPropagation();
            };
            const inputHandler = (e) => {
                e.stopPropagation();
            };
            const clickHandler = (e) => {
                e.stopPropagation();
            };
            
            input.addEventListener('change', changeHandler, true);
            input.addEventListener('input', inputHandler, true);
            input.addEventListener('click', clickHandler, true);
            
            changeHandlers.push({ input, handler: changeHandler });
            inputHandlers.push({ input, handler: inputHandler });
            clickHandlers.push({ input, handler: clickHandler });
        });

        // Use capture phase to catch events early - BEFORE they bubble
        document.addEventListener('submit', handleSubmit, true);
        document.addEventListener('submit', handleFormSubmit, true);
        document.addEventListener('keydown', handleKeyDown, true);
        document.addEventListener('click', handleClick, true);

        return () => {
            changeHandlers.forEach(({ input, handler }) => {
                input.removeEventListener('change', handler, true);
            });
            inputHandlers.forEach(({ input, handler }) => {
                input.removeEventListener('input', handler, true);
            });
            clickHandlers.forEach(({ input, handler }) => {
                input.removeEventListener('click', handler, true);
            });
            document.removeEventListener('submit', handleSubmit, true);
            document.removeEventListener('submit', handleFormSubmit, true);
            document.removeEventListener('keydown', handleKeyDown, true);
            document.removeEventListener('click', handleClick, true);
        };
    }, []);

    return containerRef;
}

