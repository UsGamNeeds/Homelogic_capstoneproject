<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            -webkit-print-color-adjust: exact;
        }
        @page {
            size: A4 landscape;
            margin: 0;
        }
        .text-primary { color: {{ $primaryColor ?? '#1E3A5F' }}; }
        .bg-primary { background-color: {{ $primaryColor ?? '#1E3A5F' }}; }
        .border-primary { border-color: {{ $primaryColor ?? '#1E3A5F' }}; }
        .text-secondary { color: {{ $secondaryColor ?? '#86EFAC' }}; }
        .bg-secondary { background-color: {{ $secondaryColor ?? '#86EFAC' }}; }
        .border-secondary { border-color: {{ $secondaryColor ?? '#86EFAC' }}; }
    </style>
</head>
<body class="bg-white p-8">
    <!-- Header Section -->
    <div class="flex justify-between items-start mb-8 border-b-2 border-secondary pb-6">
        <div class="flex items-center gap-6">
            @if(!empty($facilityLogoDataUri))
                <img src="{{ $facilityLogoDataUri }}" class="h-16 w-auto object-contain" />
            @else
                <div class="h-16 w-16 bg-primary rounded-lg flex items-center justify-center text-white font-bold text-xl">
                    {{ substr($facilityName, 0, 1) }}
                </div>
            @endif
            <div>
                <h1 class="text-2xl font-bold text-primary tracking-tight">Medication Administration Log</h1>
                <p class="text-lg font-semibold text-gray-700">{{ $facilityName }} @if($branchName) — {{ $branchName }} @endif</p>
                <p class="text-sm text-gray-500">{{ $facilityAddress ?? 'Facility Address' }}</p>
            </div>
        </div>
        <div class="text-right">
            <div class="inline-block bg-primary text-white px-4 py-2 rounded-md font-bold text-sm mb-2">
                Period: {{ $rangeLabel }}
            </div>
            <p class="text-xs text-gray-400">Exported: {{ $exportedAt }}</p>
        </div>
    </div>

    <!-- Resident Info Card -->
    <div class="grid grid-cols-4 gap-6 mb-8 bg-gray-50 p-6 rounded-xl border border-gray-200">
        <div class="flex items-center gap-4 border-r border-gray-200">
            @if(!empty($residentPhotoDataUri))
                <img src="{{ $residentPhotoDataUri }}" class="h-20 w-20 rounded-lg object-cover border-2 border-secondary" />
            @else
                <div class="h-20 w-20 bg-gray-200 rounded-lg flex items-center justify-center text-primary font-bold text-2xl border-2 border-secondary">
                    {{ $residentInitials }}
                </div>
            @endif
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Resident</p>
                <p class="text-lg font-bold text-primary">{{ $residentName }}</p>
                <p class="text-sm text-gray-600">Room: {{ $residentRoom ?? 'N/A' }}</p>
            </div>
        </div>
        <div>
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Clinical Details</p>
            <p class="text-sm"><strong>DOB:</strong> {{ $residentDob }}</p>
            <p class="text-sm"><strong>Physician:</strong> {{ $physician }}</p>
        </div>
        <div class="col-span-2">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Conditions & Allergies</p>
            <p class="text-sm"><strong>Diagnosis:</strong> {{ $diagnosis }}</p>
            <p class="text-sm text-red-600"><strong>Allergies:</strong> {{ $allergies }}</p>
        </div>
    </div>

    <!-- Medications Table -->
    <div class="space-y-8">
        <div>
            <h2 class="text-xl font-bold text-primary mb-4 flex items-center gap-2">
                <span class="w-2 h-6 bg-secondary rounded-full"></span>
                Scheduled Medications
            </h2>
            
            @forelse($scheduledSections as $section)
                <div class="mb-8 border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                    <div class="bg-gray-100 px-4 py-3 border-b border-gray-200 flex justify-between items-center">
                        <div>
                            <span class="text-lg font-bold text-primary">{{ $section['title'] }}</span>
                            <span class="ml-4 text-sm text-gray-600 italic">{{ $section['strength'] }} {{ $section['form_line'] }}</span>
                        </div>
                        <div class="text-xs font-semibold text-gray-500 bg-white px-3 py-1 rounded-full border border-gray-300">
                            {{ $section['instructions'] }}
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs border-collapse">
                            <thead>
                                <tr class="bg-gray-50 text-gray-600">
                                    <th class="border-b border-r p-2 text-left w-24">Time</th>
                                    @foreach($days as $day)
                                        <th class="border-b border-r p-2 text-center {{ count($days) > 14 ? 'w-6' : 'w-10' }}">
                                            <span class="block text-[10px] font-bold">{{ $day['dom'] }}</span>
                                            <span class="block text-[8px] uppercase">{{ substr($day['short'], 0, 3) }}</span>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($section['rows'] as $row)
                                    <tr>
                                        <td class="border-b border-r p-2 font-bold text-primary bg-white">{{ $row['time_label'] }}</td>
                                        @foreach($days as $day)
                                            @php
                                                $cell = $row['cells'][$day['date']] ?? ['text' => '—', 'tone' => 'inactive'];
                                            @endphp
                                            <td class="border-b border-r p-2 text-center @if($cell['tone'] == 'taken') bg-green-50 text-green-700 font-bold @elseif($cell['tone'] == 'not_taken') bg-red-50 text-red-700 @else bg-gray-50 text-gray-300 @endif">
                                                {{ $cell['text'] }}
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-gray-400 italic">No scheduled medications recorded for this period.</div>
            @endforelse
        </div>

        @if(count($prnSections) > 0)
        <div>
            <h2 class="text-xl font-bold text-primary mb-4 flex items-center gap-2">
                <span class="w-2 h-6 bg-orange-400 rounded-full"></span>
                PRN (As Needed) Medications
            </h2>
            <div class="grid grid-cols-2 gap-6">
                @foreach($prnSections as $prn)
                <div class="border border-gray-200 rounded-lg overflow-hidden shadow-sm h-fit">
                    <div class="bg-orange-50 px-4 py-3 border-b border-orange-100">
                        <span class="text-md font-bold text-orange-800">{{ $prn['title'] }}</span>
                        <p class="text-xs text-orange-600 font-medium tracking-tight">{{ $prn['instructions'] }}</p>
                    </div>
                    <table class="w-full text-[11px] border-collapse">
                        <thead class="bg-white">
                            <tr class="text-gray-500 border-b">
                                <th class="p-2 text-left">Date/Time</th>
                                <th class="p-2 text-center uppercase tracking-tighter">Initial</th>
                                <th class="p-2 text-left">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($prn['rows'] as $r)
                            <tr class="border-b last:border-0 hover:bg-gray-50 transition-colors">
                                <td class="p-2 text-gray-700">{{ $r['date'] }} <span class="text-gray-400 italic">{{ $r['time'] }}</span></td>
                                <td class="p-2 text-center font-bold text-green-600">{{ $r['initials'] }}</td>
                                <td class="p-2 text-gray-500 overflow-hidden text-ellipsis whitespace-nowrap">{{ $r['notes'] }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="p-4 text-center text-gray-300 italic">No PRN administrations.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    <!-- Footer Legend -->
    <div class="mt-12 p-6 bg-primary text-white rounded-xl flex justify-between items-center bg-opacity-95">
        <div class="flex gap-8 text-xs font-medium">
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 bg-green-400 rounded-sm"></span>
                Dose Administered
            </div>
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 bg-red-400 rounded-sm"></span>
                Dose Missed/Refused
            </div>
            <div class="flex items-center gap-2 text-gray-300 italic">
                <span>—</span>
                Not Scheduled
            </div>
        </div>
        <div class="text-[10px] text-primary-foreground opacity-70">
            Powered by HomeLogic360 | Secure Clinical Reporting
        </div>
    </div>
</body>
</html>
