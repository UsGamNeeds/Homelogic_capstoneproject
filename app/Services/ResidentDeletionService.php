<?php

namespace App\Services;

use App\Models\DocumentFolder;
use App\Models\Resident;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Permanently removes a resident and every record attached to them.
 *
 * Most child tables already cascade at the database level, but a few installs
 * predate those foreign keys, so the related rows are cleaned up explicitly to
 * make sure nothing is orphaned behind the deleted resident.
 */
class ResidentDeletionService
{
    /**
     * Child tables whose rows belong to the resident and go away with them.
     * Order matters: rows that other rows point at are deleted last.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const CHILD_TABLES = [
        ['medication_administrations', 'resident_id'],
        ['medication_deliveries', 'resident_id'],
        ['medications', 'resident_id'],
        ['vital_signs', 'resident_id'],
        ['sleep_records', 'resident_id'],
        ['sleep_patterns', 'resident_id'],
        ['behavior_charts', 'resident_id'],
        ['behaviors', 'resident_id'],
        ['incidents', 'resident_id'],
        ['assessments', 'resident_id'],
        ['appointments', 'resident_id'],
        ['assignments', 'resident_id'],
        ['t_logs', 'resident_id'],
        ['resident_sign_outs', 'resident_id'],
        ['resident_status_events', 'resident_id'],
        ['resident_contacts', 'resident_id'],
        ['resident_documents', 'resident_id'],
        ['family_messages', 'resident_id'],
        ['chart_messages', 'resident_id'],
        ['chart_assistant_conversations', 'resident_id'],
        ['billing_invoices', 'resident_id'],
    ];

    /**
     * Tables that keep their rows but must forget the resident they pointed at.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const DETACH_TABLES = [
        ['expenses', 'resident_id'],
        ['faxes', 'resident_id'],
        ['visitors', 'visiting_resident_id'],
    ];

    public function __construct(private readonly DocumentLibraryService $documentLibrary) {}

    /**
     * Permanently delete the resident, their related records and uploaded files.
     */
    public function delete(Resident $resident): void
    {
        $storedFiles = $this->collectStoredFiles($resident);

        DB::transaction(function () use ($resident): void {
            $this->deleteDocumentLibraryFolders($resident);

            foreach (self::DETACH_TABLES as [$table, $column]) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                    DB::table($table)->where($column, $resident->id)->update([$column => null]);
                }
            }

            foreach (self::CHILD_TABLES as [$table, $column]) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                    DB::table($table)->where($column, $resident->id)->delete();
                }
            }

            $resident->delete();
        });

        $this->deleteStoredFiles($storedFiles);
    }

    /**
     * Document library folders are removed through the library service so the
     * files stored on the library disk go with them.
     */
    private function deleteDocumentLibraryFolders(Resident $resident): void
    {
        if (! Schema::hasTable('document_folders') || ! Schema::hasColumn('document_folders', 'resident_id')) {
            return;
        }

        $folders = DocumentFolder::withoutGlobalScopes()
            ->where('resident_id', $resident->id)
            ->get();

        $folderIds = $folders->pluck('id')->all();

        foreach ($folders as $folder) {
            // Nested folders are handled by their root, so only start at the top ones.
            if ($folder->parent_id !== null && in_array($folder->parent_id, $folderIds, true)) {
                continue;
            }

            $this->documentLibrary->deleteFolderTree($folder);
        }
    }

    /**
     * Files on the public disk that only exist because of this resident.
     *
     * @return array<int, string>
     */
    private function collectStoredFiles(Resident $resident): array
    {
        $paths = [];

        if ($resident->profile_image && ! filter_var($resident->profile_image, FILTER_VALIDATE_URL)) {
            $paths[] = $resident->profile_image;
        }

        if (Schema::hasTable('resident_documents')) {
            $documentPaths = DB::table('resident_documents')
                ->where('resident_id', $resident->id)
                ->pluck('file_path')
                ->filter()
                ->all();

            $paths = array_merge($paths, $documentPaths);
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function deleteStoredFiles(array $paths): void
    {
        foreach ($paths as $path) {
            try {
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            } catch (\Throwable $e) {
                // The records are already gone; a leftover file must not fail the request.
                Log::warning('ResidentDeletionService: failed to delete stored file', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
