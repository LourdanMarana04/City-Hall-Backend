<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use ZipArchive;

class BackupController extends Controller
{
    /**
     * Create a full database backup
     * Only accessible by super_admin
     */
    public function createBackup(Request $request)
    {
        $user = $request->user();

        // Only super_admin can create backups
        if (!($user instanceof \App\Models\Admin && $user->role === 'super_admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Only super administrators can create backups.'
            ], 403);
        }

        try {
            $backupName = 'backup_' . Carbon::now()->format('Y-m-d_His') . '.sql';
            $backupPath = storage_path('app/backups');

            // Create backups directory if it doesn't exist
            if (!file_exists($backupPath)) {
                mkdir($backupPath, 0755, true);
            }

            $fullPath = $backupPath . '/' . $backupName;

            // Get database configuration
            $database = config('database.connections.mysql.database');
            $username = config('database.connections.mysql.username');
            $password = config('database.connections.mysql.password');
            $host = config('database.connections.mysql.host');
            $port = config('database.connections.mysql.port', 3306);

            // Find mysqldump executable (Windows XAMPP support)
            $mysqldump = 'mysqldump';
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Try common XAMPP paths
                $xamppPaths = [
                    'C:\\xampp\\mysql\\bin\\mysqldump.exe',
                    'C:\\xampp\\mysql\\bin\\mysqldump',
                    getcwd() . '\\mysql\\bin\\mysqldump.exe',
                ];
                foreach ($xamppPaths as $path) {
                    if (file_exists($path)) {
                        $mysqldump = $path;
                        break;
                    }
                }
            }

            // Create mysqldump command
            $command = sprintf(
                '%s -h %s -P %s -u %s %s %s > %s',
                escapeshellarg($mysqldump),
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($username),
                $password ? '-p' . escapeshellarg($password) : '',
                escapeshellarg($database),
                escapeshellarg($fullPath)
            );

            // Execute backup
            exec($command . ' 2>&1', $output, $returnVar);

            if ($returnVar !== 0) {
                $errorOutput = implode("\n", $output);
                Log::error('Backup failed', [
                    'output' => $output,
                    'return_var' => $returnVar,
                    'command' => $command,
                    'mysqldump_path' => $mysqldump
                ]);

                // Provide more helpful error messages
                $errorMessage = 'Backup failed. ';
                if (empty($errorOutput)) {
                    $errorMessage .= 'mysqldump command not found. Please ensure MySQL is installed and mysqldump is in your PATH, or configure the path in the backup controller.';
                } else {
                    $errorMessage .= $errorOutput;
                }

                return response()->json([
                    'status' => false,
                    'message' => $errorMessage,
                    'error' => $errorOutput
                ], 500);
            }

            // Get file size
            $fileSize = filesize($fullPath);

            // Record backup in database
            $backupRecord = DB::table('backup_history')->insert([
                'filename' => $backupName,
                'file_path' => $fullPath,
                'file_size' => $fileSize,
                'created_by' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Also backup uploaded files (if any)
            $this->backupFiles($backupName);

            return response()->json([
                'status' => true,
                'message' => 'Backup created successfully',
                'data' => [
                    'filename' => $backupName,
                    'file_size' => $this->formatBytes($fileSize),
                    'created_at' => now()->toDateTimeString(),
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Backup error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while creating backup: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * List all available backups
     */
    public function listBackups(Request $request)
    {
        $user = $request->user();

        if (!($user instanceof \App\Models\Admin && $user->role === 'super_admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Only super administrators can view backups.'
            ], 403);
        }

        try {
            $backups = DB::table('backup_history')
                ->leftJoin('admins', 'backup_history.created_by', '=', 'admins.id')
                ->select(
                    'backup_history.id',
                    'backup_history.filename',
                    'backup_history.file_path',
                    'backup_history.file_size',
                    'backup_history.created_at',
                    'admins.name as created_by_name'
                )
                ->orderBy('backup_history.created_at', 'desc')
                ->get()
                ->map(function ($backup) {
                    return [
                        'id' => $backup->id,
                        'filename' => $backup->filename,
                        'file_size' => $this->formatBytes($backup->file_size),
                        'created_at' => $backup->created_at,
                        'created_by' => $backup->created_by_name,
                        'exists' => file_exists($backup->file_path),
                    ];
                });

            return response()->json([
                'status' => true,
                'data' => $backups
            ], 200);

        } catch (\Exception $e) {
            Log::error('List backups error', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while listing backups: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download a backup file
     */
    public function downloadBackup(Request $request, $id)
    {
        $user = $request->user();

        if (!($user instanceof \App\Models\Admin && $user->role === 'super_admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Only super administrators can download backups.'
            ], 403);
        }

        try {
            $backup = DB::table('backup_history')->where('id', $id)->first();

            if (!$backup) {
                return response()->json([
                    'status' => false,
                    'message' => 'Backup not found'
                ], 404);
            }

            if (!file_exists($backup->file_path)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Backup file no longer exists'
                ], 404);
            }

            return response()->download($backup->file_path, $backup->filename);

        } catch (\Exception $e) {
            Log::error('Download backup error', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while downloading backup: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore database from backup
     */
    public function restoreBackup(Request $request, $id)
    {
        $user = $request->user();

        if (!($user instanceof \App\Models\Admin && $user->role === 'super_admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Only super administrators can restore backups.'
            ], 403);
        }

        // Validate confirmation
        $confirmed = $request->input('confirmed', false);
        if (!$confirmed) {
            return response()->json([
                'status' => false,
                'message' => 'Restore operation requires confirmation. This will replace all current data.',
                'requires_confirmation' => true
            ], 400);
        }

        try {
            $backup = DB::table('backup_history')->where('id', $id)->first();

            if (!$backup) {
                return response()->json([
                    'status' => false,
                    'message' => 'Backup not found'
                ], 404);
            }

            if (!file_exists($backup->file_path)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Backup file no longer exists'
                ], 404);
            }

            // Get database configuration
            $database = config('database.connections.mysql.database');
            $username = config('database.connections.mysql.username');
            $password = config('database.connections.mysql.password');
            $host = config('database.connections.mysql.host');
            $port = config('database.connections.mysql.port', 3306);

            // Find mysql executable (Windows XAMPP support)
            $mysql = 'mysql';
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Try common XAMPP paths
                $xamppPaths = [
                    'C:\\xampp\\mysql\\bin\\mysql.exe',
                    'C:\\xampp\\mysql\\bin\\mysql',
                    getcwd() . '\\mysql\\bin\\mysql.exe',
                ];
                foreach ($xamppPaths as $path) {
                    if (file_exists($path)) {
                        $mysql = $path;
                        break;
                    }
                }
            }

            // Disable foreign key checks temporarily
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');

            // Restore database
            $command = sprintf(
                '%s -h %s -P %s -u %s %s %s < %s',
                escapeshellarg($mysql),
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($username),
                $password ? '-p' . escapeshellarg($password) : '',
                escapeshellarg($database),
                escapeshellarg($backup->file_path)
            );

            exec($command . ' 2>&1', $output, $returnVar);

            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            if ($returnVar !== 0) {
                Log::error('Restore failed', ['output' => $output, 'return_var' => $returnVar]);
                return response()->json([
                    'status' => false,
                    'message' => 'Restore failed. Please check server logs.',
                    'error' => implode("\n", $output)
                ], 500);
            }

            // Log the restore operation
            DB::table('backup_history')
                ->where('id', $id)
                ->update([
                    'restored_at' => now(),
                    'restored_by' => $user->id,
                    'updated_at' => now(),
                ]);

            Log::info('Database restored', [
                'backup_id' => $id,
                'backup_file' => $backup->filename,
                'restored_by' => $user->id,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Database restored successfully from backup: ' . $backup->filename
            ], 200);

        } catch (\Exception $e) {
            Log::error('Restore error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while restoring backup: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a backup
     */
    public function deleteBackup(Request $request, $id)
    {
        $user = $request->user();

        if (!($user instanceof \App\Models\Admin && $user->role === 'super_admin')) {
            return response()->json([
                'status' => false,
                'message' => 'Only super administrators can delete backups.'
            ], 403);
        }

        try {
            $backup = DB::table('backup_history')->where('id', $id)->first();

            if (!$backup) {
                return response()->json([
                    'status' => false,
                    'message' => 'Backup not found'
                ], 404);
            }

            // Delete file if exists
            if (file_exists($backup->file_path)) {
                unlink($backup->file_path);
            }

            // Delete record
            DB::table('backup_history')->where('id', $id)->delete();

            return response()->json([
                'status' => true,
                'message' => 'Backup deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Delete backup error', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while deleting backup: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Backup uploaded files (optional)
     */
    private function backupFiles($backupName)
    {
        try {
            $storagePath = storage_path('app/public');
            if (!is_dir($storagePath)) {
                return;
            }

            $zipPath = storage_path('app/backups/' . str_replace('.sql', '_files.zip', $backupName));

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($storagePath),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($files as $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = substr($filePath, strlen($storagePath) + 1);
                        $zip->addFile($filePath, $relativePath);
                    }
                }

                $zip->close();
            }
        } catch (\Exception $e) {
            Log::warning('File backup failed', ['error' => $e->getMessage()]);
            // Don't fail the entire backup if file backup fails
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

