<?php

namespace Database\Seeders;

use App\Models\MedicationSynonym;
use Illuminate\Database\Seeder;

/**
 * Starter synonym table for MedicationRxNormChecker: maps brand names and
 * common shorthand caregivers write in a rough shift note back to a generic
 * canonical name, with the ingredient-level RxNorm CUI attached.
 *
 * These ~30 entries cover common AFH medications and are a best-effort seed
 * from general reference knowledge, not a live RxNorm export — verify CUIs
 * against the RxNorm API (https://rxnav.nlm.nih.gov) before relying on them
 * for anything beyond this feature's own name-matching, and expand this list
 * (or replace it with a scheduled RxNormSync) as unmatched drugs surface in
 * the review queue.
 */
class MedicationSynonymSeeder extends Seeder
{
    public function run(): void
    {
        $entries = [
            ['acetaminophen', '161', ['acetaminophen', 'tylenol', 'apap']],
            ['ibuprofen', '5640', ['ibuprofen', 'advil', 'motrin']],
            ['lisinopril', '29046', ['lisinopril', 'prinivil', 'zestril']],
            ['metformin', '6809', ['metformin', 'glucophage']],
            ['atorvastatin', '83367', ['atorvastatin', 'lipitor']],
            ['omeprazole', '7646', ['omeprazole', 'prilosec']],
            ['levothyroxine', '10582', ['levothyroxine', 'synthroid']],
            ['amlodipine', '17767', ['amlodipine', 'norvasc']],
            ['metoprolol', '6918', ['metoprolol', 'lopressor', 'toprol', 'toprol-xl']],
            ['furosemide', '4603', ['furosemide', 'lasix']],
            ['sertraline', '36437', ['sertraline', 'zoloft']],
            ['donepezil', '3155', ['donepezil', 'aricept']],
            ['warfarin', '11289', ['warfarin', 'coumadin']],
            ['aspirin', '1191', ['aspirin', 'asa', 'bayer']],
            ['gabapentin', '25480', ['gabapentin', 'neurontin']],
            ['losartan', '52175', ['losartan', 'cozaar']],
            ['simvastatin', '36567', ['simvastatin', 'zocor']],
            ['hydrochlorothiazide', '5487', ['hydrochlorothiazide', 'hctz', 'microzide']],
            ['trazodone', '10737', ['trazodone', 'desyrel']],
            ['citalopram', '2556', ['citalopram', 'celexa']],
            ['prednisone', '8640', ['prednisone', 'deltasone']],
            ['clopidogrel', '32968', ['clopidogrel', 'plavix']],
            ['insulin glargine', '274783', ['insulin glargine', 'lantus', 'basaglar']],
            ['albuterol', '435', ['albuterol', 'proair', 'ventolin', 'proventil']],
            ['melatonin', '6619', ['melatonin']],
            ['docusate', '3389', ['docusate', 'colace']],
            ['senna', '9997', ['senna', 'senokot']],
            ['tramadol', '10689', ['tramadol', 'ultram']],
            ['quetiapine', '51272', ['quetiapine', 'seroquel']],
            ['risperidone', '35636', ['risperidone', 'risperdal']],
        ];

        foreach ($entries as [$canonical, $cui, $synonyms]) {
            foreach ($synonyms as $synonym) {
                MedicationSynonym::updateOrCreate(
                    ['synonym' => strtolower($synonym)],
                    ['canonical_name' => $canonical, 'rxnorm_cui' => $cui]
                );
            }
        }
    }
}
