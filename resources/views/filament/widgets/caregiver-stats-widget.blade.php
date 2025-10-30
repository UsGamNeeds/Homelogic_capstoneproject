<x-filament-widgets::widget>
    <div class="p-8">
        <!-- Header -->
        <div class="mb-8 text-center">
            <h3 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">Your Care Overview</h3>
            <p class="text-gray-600 dark:text-gray-400 text-lg">Real-time metrics for your care responsibilities</p>
        </div>

        <!-- Clean Stats Grid with Two Colors -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- My Residents Card - Blue -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 border-2 border-blue-500 hover:border-blue-600 dark:border-blue-400 dark:hover:border-blue-300">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg border border-blue-200 dark:border-blue-700">
                                <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                                </svg>
                            </div>
                            <div>
                                <h4 class="text-lg font-bold text-gray-900 dark:text-white">My Residents</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Assigned to me</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $this->getStats()['assigned_residents'] }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Active</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Today's Appointments Card - Blue -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 border-2 border-blue-500 hover:border-blue-600 dark:border-blue-400 dark:hover:border-blue-300">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg border border-blue-200 dark:border-blue-700">
                                <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <h4 class="text-lg font-bold text-gray-900 dark:text-white">Today's Appointments</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Scheduled meetings</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $this->getStats()['todays_appointments'] }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Today</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Assessments Card - Green -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 border-2 border-green-500 hover:border-green-600 dark:border-green-400 dark:hover:border-green-300">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-lg border border-green-200 dark:border-green-700">
                                <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div>
                                <h4 class="text-lg font-bold text-gray-900 dark:text-white">Pending Assessments</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Awaiting completion</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $this->getStats()['pending_assessments'] }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Urgent</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Vitals Recorded Card - Green -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 border-2 border-green-500 hover:border-green-600 dark:border-green-400 dark:hover:border-green-300">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-lg border border-green-200 dark:border-green-700">
                                <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                </svg>
                            </div>
                            <div>
                                <h4 class="text-lg font-bold text-gray-900 dark:text-white">Vitals Recorded</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Health measurements</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $this->getStats()['vitals_recorded_today'] }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Today</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Leave Requests Card - Blue -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 border-2 border-blue-500 hover:border-blue-600 dark:border-blue-400 dark:hover:border-blue-300">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg border border-blue-200 dark:border-blue-700">
                                <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <h4 class="text-lg font-bold text-gray-900 dark:text-white">Leave Requests</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Awaiting approval</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $this->getStats()['pending_leave_requests'] }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Pending</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Weekly Appointments Card - Green -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 border-2 border-green-500 hover:border-green-600 dark:border-green-400 dark:hover:border-green-300">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-lg border border-green-200 dark:border-green-700">
                                <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <h4 class="text-lg font-bold text-gray-900 dark:text-white">Weekly Appointments</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Upcoming schedule</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $this->getStats()['this_weeks_appointments'] }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">This Week</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
