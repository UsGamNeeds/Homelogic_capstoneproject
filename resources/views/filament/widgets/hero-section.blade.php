<x-filament-widgets::widget>
    <div class="relative overflow-hidden rounded-xl bg-gradient-to-r from-sky-500 to-blue-600 shadow-lg">
        <div class="absolute top-0 right-0 -mt-4 -mr-16 opacity-10">
            <svg class="w-48 h-48" fill="currentColor" viewBox="0 0 24 24">
                <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>
            </svg>
        </div>
        
        <div class="relative px-6 py-4 flex items-center justify-between">
            <div class="flex-1">
                <h2 class="text-lg font-semibold text-white mb-1">
                    Welcome back, {{ auth()->user()->name }}!
                </h2>
                <p class="text-sky-100 text-sm">
                    @if(auth()->user()->hasRole('administrator') || auth()->user()->hasRole('super_admin'))
                        Managing care with compassion and excellence
                    @else
                        Providing exceptional care to {{ auth()->user()->assignments()->count() ?? 'our' }} residents
                    @endif
                </p>
            </div>
            
            <div class="hidden md:flex items-center space-x-4">
                <div class="text-right">
                    <p class="text-sky-100 text-xs">{{ now()->format('l') }}</p>
                    <p class="text-white font-medium">{{ now()->format('F j, Y') }}</p>
                </div>
                <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center backdrop-blur-sm">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
