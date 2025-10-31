@php
    $user = Auth::user();
    $profileImageUrl = $user->profile_image 
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($user->profile_image) 
        : null;
    $initials = strtoupper(substr($user->name ?? 'AU', 0, 2));
@endphp

<div x-data="{ open: false }" class="relative inline-block text-left">
    <!-- Avatar Button -->
    <button 
        @click="open = !open"
        type="button"
        class="flex items-center focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
    >
        @if($profileImageUrl)
            <img 
                src="{{ $profileImageUrl }}" 
                alt="{{ $user->name }}"
                class="h-8 w-8 rounded-full object-cover ring-2 ring-white dark:ring-gray-800"
                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
            >
            <div class="h-8 w-8 rounded-full bg-primary-500 flex items-center justify-center hidden">
                <span class="text-xs font-medium text-white">{{ $initials }}</span>
            </div>
        @else
            <div class="h-8 w-8 rounded-full bg-primary-500 flex items-center justify-center">
                <span class="text-xs font-medium text-white">{{ $initials }}</span>
            </div>
        @endif
    </button>

    <!-- Dropdown Menu -->
    <div 
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        @click.away="open = false"
        x-cloak
        class="absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 z-50"
        style="display: none;"
    >
        <div class="py-1" role="menu" aria-orientation="vertical">
            <!-- User Info -->
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $user->name }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ $user->email }}</p>
            </div>

            <!-- Profile Link -->
            <a 
                href="{{ route('filament.admin.pages.user-profile') }}"
                class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                role="menuitem"
            >
                <x-heroicon-o-user-circle class="mr-3 h-5 w-5" />
                My Profile
            </a>

            <!-- Logout Link -->
            <form method="POST" action="{{ route('filament.admin.auth.logout') }}">
                @csrf
                <button
                    type="submit"
                    class="flex items-center w-full px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                    role="menuitem"
                >
                    <x-heroicon-o-arrow-right-on-rectangle class="mr-3 h-5 w-5" />
                    Logout
                </button>
            </form>
        </div>
    </div>
</div>
