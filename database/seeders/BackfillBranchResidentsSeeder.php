<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Branch;
use App\Models\Resident;
use Carbon\Carbon;

class BackfillBranchResidentsSeeder extends Seeder
{
    /**
     * Ensure each branch has some residents.
     */
    public function run(): void
    {
        $branches = Branch::where('is_active', true)->get();

        foreach ($branches as $branch) {
            $currentCount = Resident::where('branch_id', $branch->id)->count();
            if ($currentCount > 0) {
                $this->command->line("Branch {$branch->name} already has {$currentCount} residents.");
                continue;
            }

            $this->command->info("Creating residents for branch: {$branch->name}");

            for ($i = 1; $i <= 5; $i++) {
                $firstNames = ['John','Mary','James','Patricia','Robert','Linda','Michael','Barbara','William','Elizabeth','David','Jennifer','Richard','Maria','Joseph','Susan'];
                $lastNames = ['Smith','Johnson','Williams','Brown','Jones','Garcia','Miller','Davis','Rodriguez','Martinez','Hernandez','Lopez','Gonzalez','Wilson','Anderson','Taylor'];

                $first = $firstNames[array_rand($firstNames)];
                $last = $lastNames[array_rand($lastNames)];
                $name = "$first $last";

                $gender = in_array($first, ['Mary','Patricia','Linda','Barbara','Elizabeth','Jennifer','Maria','Susan']) ? 'Female' : 'Male';

                Resident::firstOrCreate(
                    ['name' => $name, 'branch_id' => $branch->id],
                    [
                        'first_name' => $first,
                        'last_name' => $last,
                        'gender' => $gender,
                        'date_of_birth' => Carbon::now()->subYears(rand(65, 96))->subDays(rand(0, 365))->toDateString(),
                        'diagnosis' => 'General care',
                        'physician_name' => 'Facility Physician',
                        'room' => (string) rand(101, 599),
                        'room_number' => (string) rand(101, 599),
                        'is_active' => true,
                        'admission_date' => Carbon::now()->subMonths(rand(1, 18))->toDateString(),
                    ]
                );
            }
        }

        $this->command->info('BackfillBranchResidentsSeeder completed.');
    }
}


