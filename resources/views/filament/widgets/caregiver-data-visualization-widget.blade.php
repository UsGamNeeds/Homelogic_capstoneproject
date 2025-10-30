<x-filament-widgets::widget>
    <div class="p-8">
        <!-- Header -->
        <div class="mb-8 text-center">
            <h3 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">Health Data Overview</h3>
            <p class="text-gray-600 dark:text-gray-400 text-lg">Vital signs trends and resident health status</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Vital Signs Chart -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-2 border-blue-500 dark:border-blue-400">
                <h4 class="text-xl font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                    <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    Vital Signs Trend (7 Days)
                </h4>
                <div class="h-64">
                    <canvas id="vitalTrendsChart"></canvas>
                </div>
            </div>

            <!-- Resident Health Status -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 border-2 border-green-500 dark:border-green-400">
                <h4 class="text-xl font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                    <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                    </svg>
                    Resident Health Status
                </h4>
                <div class="space-y-4">
                    @forelse($this->getChartData()['residentHealthStatus'] as $resident)
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                            <div class="flex items-center gap-3">
                                <div class="p-2 rounded-full
                                    @if($resident['status'] == 'attention') bg-red-100 dark:bg-red-900/30
                                    @elseif($resident['status'] == 'caution') bg-yellow-100 dark:bg-yellow-900/30
                                    @else bg-green-100 dark:bg-green-900/30
                                    @endif">
                                    <svg class="h-4 w-4 
                                        @if($resident['status'] == 'attention') text-red-600 dark:text-red-400
                                        @elseif($resident['status'] == 'caution') text-yellow-600 dark:text-yellow-400
                                        @else text-green-600 dark:text-green-400
                                        @endif" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ $resident['name'] }}</p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $resident['last_vitals'] }}</p>
                                </div>
                            </div>
                            <span class="px-3 py-1 text-xs font-medium rounded-full
                                @if($resident['status'] == 'attention') bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400
                                @elseif($resident['status'] == 'caution') bg-yellow-100 text-yellow-600 dark:bg-yellow-900/30 dark:text-yellow-400
                                @else bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400
                                @endif">
                                {{ ucfirst($resident['status']) }}
                            </span>
                        </div>
                    @empty
                        <p class="text-gray-500 dark:text-gray-400 text-center">No health data available</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Chart Script -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const ctx = document.getElementById('vitalTrendsChart');
                if (ctx) {
                    const chartData = @json($this->getChartData());
                    
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: chartData.labels,
                            datasets: chartData.datasets
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    labels: {
                                        color: window.matchMedia('(prefers-color-scheme: dark)').matches ? '#ffffff' : '#374151'
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    ticks: {
                                        color: window.matchMedia('(prefers-color-scheme: dark)').matches ? '#ffffff' : '#374151'
                                    },
                                    grid: {
                                        color: window.matchMedia('(prefers-color-scheme: dark)').matches ? '#374151' : '#e5e7eb'
                                    }
                                },
                                y: {
                                    ticks: {
                                        color: window.matchMedia('(prefers-color-scheme: dark)').matches ? '#ffffff' : '#374151'
                                    },
                                    grid: {
                                        color: window.matchMedia('(prefers-color-scheme: dark)').matches ? '#374151' : '#e5e7eb'
                                    }
                                }
                            }
                        }
                    });
                }
            });
        </script>
    </div>
</x-filament-widgets::widget>
