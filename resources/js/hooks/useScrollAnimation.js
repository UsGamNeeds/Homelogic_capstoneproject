import { useEffect, useRef } from 'react';
import anime from 'animejs';
import { shouldAnimate, fadeIn, slideInUp } from '../utils/animationPresets';

/**
 * Hook for scroll-triggered animations using anime.js
 * Animates elements when they come into viewport
 */
export function useScrollAnimation(options = {}) {
    const elementRef = useRef(null);
    const hasAnimatedRef = useRef(false);

    useEffect(() => {
        if (!elementRef.current || !shouldAnimate() || hasAnimatedRef.current) return;

        const element = elementRef.current;
        const {
            animationType = 'fade',
            threshold = 0.1,
            duration = 600,
            delay = 0,
            easing = 'easeOutExpo',
        } = options;

        // Set initial state
        element.style.opacity = '0';
        if (animationType === 'slideUp') {
            element.style.transform = 'translateY(30px)';
        }

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting && !hasAnimatedRef.current) {
                        hasAnimatedRef.current = true;

                        // Apply animation based on type
                        switch (animationType) {
                            case 'fade':
                                fadeIn(element, { duration, delay, easing });
                                break;
                            case 'slideUp':
                                slideInUp(element, { duration, delay, easing });
                                break;
                            default:
                                fadeIn(element, { duration, delay, easing });
                        }

                        observer.disconnect();
                    }
                });
            },
            {
                threshold,
                rootMargin: '0px 0px -50px 0px',
            }
        );

        observer.observe(element);

        return () => {
            observer.disconnect();
        };
    }, [options.animationType, options.threshold, options.duration, options.delay]);

    return elementRef;
}

