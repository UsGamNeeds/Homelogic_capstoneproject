<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-sm border p-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Inactive Users</h1>
                <p class="text-gray-600 mt-1">Review staff members that are currently deactivated and quickly reactivate them when needed.</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border">
        <div class="p-6">
            {{ $this->table }}
        </div>
    </div>
</div>
