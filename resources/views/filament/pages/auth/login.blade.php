<x-filament-panels::page.simple>
    <div class="min-h-screen flex flex-col md:flex-row bg-[#F5F5F0]">
        <!-- Brand / Welcome Panel -->
        <div class="md:w-1/2 relative overflow-hidden flex items-center justify-center bg-gradient-to-br from-[#1F513B] via-[#25603E] to-[#36875E] text-white p-8 md:p-12">
            <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top,_rgba(255,255,255,0.4),_rgba(255,255,255,0))]"></div>
            <div class="absolute inset-0 opacity-10 mix-blend-soft-light bg-[radial-gradient(circle_at_center,_rgba(255,255,255,0.45)_0%,_rgba(255,255,255,0)_65%)]"></div>
            <div class="relative z-10 max-w-xl space-y-6 text-center md:text-left">
                <div class="flex flex-col md:flex-row md:items-center md:space-x-4 items-center space-y-4 md:space-y-0">
                    <img src="{{ asset('images/logo.png') }}" alt="Evergreen Oasis Care Home" class="h-20 w-20 rounded-full shadow-lg ring-2 ring-white/40">
                    <div>
                        <p class="uppercase tracking-[0.35em] text-xs font-semibold text-white/70">Evergreen Oasis Care Home</p>
                        <h1 class="text-3xl md:text-4xl font-bold leading-tight">Healthcare Management System</h1>
                    </div>
                </div>
                <p class="text-white/85 text-base md:text-lg leading-relaxed">
                    Welcome back to your care management hub. Log in to manage residents, staff schedules, medications, and every detail that keeps your community thriving.
                </p>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 text-sm text-white/70">
                    <div class="flex items-start space-x-3">
                        <span class="inline-flex items-center justify-center h-10 w-10 rounded-full bg-white/10 backdrop-blur-sm border border-white/20">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 7l8.89 5.26a2 2 0 002.22 0L23 7M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </span>
                        <div>
                            <p class="font-semibold text-white">Secure Access</p>
                            <p class="text-white/55 text-xs leading-relaxed">Enterprise-grade encryption keeps every login protected.</p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <span class="inline-flex items-center justify-center h-10 w-10 rounded-full bg-white/10 backdrop-blur-sm border border-white/20">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 3H5a2 2 0 00-2 2v5m0 4v5a2 2 0 002 2h5m4 0h5a2 2 0 002-2v-5m0-4V5a2 2 0 00-2-2h-5" />
                            </svg>
                        </span>
                        <div>
                            <p class="font-semibold text-white">HIPAA Ready</p>
                            <p class="text-white/55 text-xs leading-relaxed">Designed for compliant healthcare operations from day one.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Authentication Panel -->
        <div class="md:w-1/2 flex items-center justify-center p-6 md:p-12 bg-white">
            <div class="w-full max-w-md space-y-8">
                <div class="space-y-2 text-center md:text-left">
                    <p class="text-xs uppercase tracking-[0.4em] text-[#25603E] font-semibold">Welcome back</p>
                    <h2 class="text-2xl md:text-3xl font-semibold text-[#1B402D]">Sign in to continue</h2>
                    <p class="text-sm text-[#627567] leading-relaxed">Use your Evergreen credentials to access the care home dashboard.</p>
                </div>

                <div class="bg-white border border-[#E3E8E3] rounded-xl shadow-[0_18px_48px_-25px_rgba(27,64,45,0.35)] p-6 space-y-6">
                    <div class="space-y-2">
                        <p class="text-sm font-medium text-[#1F2E26]">Account credentials</p>
                        <p class="text-xs text-[#6F8276]">Please enter the credentials provided by the Evergreen administration team.</p>
                    </div>

                    {{ $this->form }}

                    <div class="flex items-center justify-between text-xs text-[#6F8276]">
                        <p>Need assistance? <a href="mailto:support@evergreenoasis.com" class="text-[#25603E] font-semibold hover:underline">Contact support</a></p>
                        <p>© {{ date('Y') }} Evergreen Oasis</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const attachToggle = () => {
                const passwordInput = document.querySelector('input[type="password"][name="password"]');
                if (!passwordInput || passwordInput.dataset.toggleAttached === 'true') {
                    return;
                }

                passwordInput.dataset.toggleAttached = 'true';

                const wrapper = passwordInput.closest('.fi-input-wrp, .fi-input-wrapper') || passwordInput.parentElement;
                if (!wrapper) {
                    return;
                }

                wrapper.classList.add('relative');
                passwordInput.classList.add('pr-16');

                const toggleButton = document.createElement('button');
                toggleButton.type = 'button';
                toggleButton.className = 'absolute inset-y-0 right-3 flex items-center text-xs font-semibold text-[#25603E] hover:text-[#1B402D] transition';
                toggleButton.style.paddingLeft = '0.5rem';
                toggleButton.style.paddingRight = '0.5rem';
                toggleButton.textContent = 'Show';

                toggleButton.addEventListener('click', () => {
                    const isHidden = passwordInput.type === 'password';
                    passwordInput.type = isHidden ? 'text' : 'password';
                    toggleButton.textContent = isHidden ? 'Hide' : 'Show';
                });

                wrapper.appendChild(toggleButton);
            };

            attachToggle();

            const observer = new MutationObserver(() => attachToggle());
            observer.observe(document.body, { childList: true, subtree: true });
        });
    </script>
</x-filament-panels::page.simple>
