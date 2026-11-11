<?php

declare(strict_types=1);

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class StorageIntegrityService
{
    /**
     * Check known file-reference tables without exposing stored paths.
     *
     * @return array<string, mixed>
     */
    public function run(int $maxRecords = 0): array
    {
        $startedAt = now();
        $definitions = [
            'media_assets' => ['disk_column' => 'disk', 'default_disk' => 'public'],
            'service_images' => ['disk_column' => null, 'default_disk' => 'public'],
            'professional_documents' => ['disk_column' => 'disk', 'default_disk' => 'local'],
            'message_attachments' => ['disk_column' => 'disk', 'default_disk' => 'local'],
            'order_delivery_attachments' => ['disk_column' => null, 'default_disk' => 'local'],
            'order_dispute_attachments' => ['disk_column' => 'disk', 'default_disk' => 'local'],
            'service_request_attachments' => ['disk_column' => null, 'default_disk' => 'local'],
        ];

        $results = [];
        $recordsChecked = 0;
        $missingFiles = 0;
        $checkErrors = 0;

        foreach ($definitions as $table => $definition) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'path')) {
                continue;
            }

            $diskColumn = $definition['disk_column'];
            $hasDiskColumn = is_string($diskColumn) && Schema::hasColumn($table, $diskColumn);
            $checked = 0;
            $missing = 0;
            $errors = 0;

            $columns = ['id', 'path'];
            if ($hasDiskColumn) {
                $columns[] = $diskColumn;
            }

            DB::table($table)
                ->whereNotNull('path')
                ->orderBy('id')
                ->select($columns)
                ->chunkById(200, function ($rows) use (
                    $definition,
                    $hasDiskColumn,
                    $diskColumn,
                    $maxRecords,
                    &$checked,
                    &$missing,
                    &$errors,
                    &$recordsChecked,
                    &$missingFiles,
                    &$checkErrors,
                ): bool {
                    foreach ($rows as $row) {
                        if ($maxRecords > 0 && $recordsChecked >= $maxRecords) {
                            return false;
                        }

                        $path = $row->path;
                        if (! is_string($path) || trim($path) === '') {
                            continue;
                        }

                        $storedDisk = $hasDiskColumn ? trim((string) ($row->{$diskColumn} ?? '')) : '';
                        $diskName = $storedDisk !== '' ? $storedDisk : $definition['default_disk'];

                        try {
                            if (! Storage::disk($diskName)->exists($path)) {
                                $missing++;
                                $missingFiles++;
                            }
                        } catch (Throwable) {
                            $errors++;
                            $checkErrors++;
                        }

                        $checked++;
                        $recordsChecked++;
                    }

                    return $maxRecords === 0 || $recordsChecked < $maxRecords;
                });

            $results[$table] = [
                'checked' => $checked,
                'missing' => $missing,
                'errors' => $errors,
            ];
        }

        $status = $checkErrors > 0 ? 'completed_with_errors' : ($missingFiles > 0 ? 'missing_files_found' : 'completed');
        $completedAt = now();

        $runId = DB::table('storage_integrity_runs')->insertGetId([
            'status' => $status,
            'records_checked' => $recordsChecked,
            'missing_files' => $missingFiles,
            'check_errors' => $checkErrors,
            'table_results' => json_encode($results, JSON_THROW_ON_ERROR),
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'created_at' => $completedAt,
            'updated_at' => $completedAt,
        ]);

        return [
            'id' => $runId,
            'status' => $status,
            'records_checked' => $recordsChecked,
            'missing_files' => $missingFiles,
            'check_errors' => $checkErrors,
            'table_results' => $results,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
        ];
    }
}
