<?php

namespace App\Services\H5P;

use H5peditorStorage;
use Illuminate\Support\Facades\DB;

/**
 * Editor-side storage: library listings for the content-type selector,
 * language packs, and temporary-file bookkeeping (h5p_tmpfiles). Physical
 * file writes are handled by H5PDefaultStorage via H5PCore->fs.
 */
class EditorStorage implements H5peditorStorage
{
    /** Absolute path that backs the public H5P directory (set by H5PService). */
    public static string $basePath = '';

    public function getLanguage($machineName, $majorVersion, $minorVersion, $language)
    {
        $libraryId = DB::table('h5p_libraries')
            ->where('machine_name', $machineName)
            ->where('major_version', $majorVersion)
            ->where('minor_version', $minorVersion)
            ->value('id');

        if (! $libraryId) {
            return null;
        }

        return DB::table('h5p_libraries_languages')
            ->where('library_id', $libraryId)
            ->where('language_code', $language)
            ->value('translation');
    }

    public function getAvailableLanguages($machineName, $majorVersion, $minorVersion)
    {
        $libraryId = DB::table('h5p_libraries')
            ->where('machine_name', $machineName)
            ->where('major_version', $majorVersion)
            ->where('minor_version', $minorVersion)
            ->value('id');

        if (! $libraryId) {
            return ['en'];
        }

        $languages = DB::table('h5p_libraries_languages')
            ->where('library_id', $libraryId)
            ->pluck('language_code')
            ->toArray();

        array_unshift($languages, 'en');
        return array_values(array_unique($languages));
    }

    public function keepFile($fileId)
    {
        DB::table('h5p_tmpfiles')->where('path', $fileId)->delete();
    }

    public function getLibraries($libraries = null)
    {
        if ($libraries !== null) {
            // Caller asks about specific libraries — annotate the ones we have.
            $result = [];
            foreach ($libraries as $library) {
                $row = DB::table('h5p_libraries')
                    ->where('machine_name', $library->name)
                    ->where('major_version', $library->majorVersion)
                    ->where('minor_version', $library->minorVersion)
                    ->first();

                if ($row && (int) $row->runnable !== 0 && (int) $row->restricted === 0) {
                    $library->tutorialUrl     = $row->tutorial_url;
                    $library->title           = $row->title;
                    $library->runnable        = (int) $row->runnable;
                    $library->restricted      = (bool) $row->restricted;
                    $library->metadataSettings = $row->metadata_settings ? json_decode($row->metadata_settings) : null;
                    $result[] = $library;
                }
            }
            return $result;
        }

        // No filter — return all runnable, non-restricted libraries (latest each).
        $rows = DB::table('h5p_libraries')
            ->where('runnable', 1)
            ->where('restricted', 0)
            ->orderBy('title')
            ->orderBy('major_version', 'desc')
            ->orderBy('minor_version', 'desc')
            ->get();

        $seen   = [];
        $result = [];
        foreach ($rows as $row) {
            if (isset($seen[$row->machine_name])) {
                continue; // keep only the newest version
            }
            $seen[$row->machine_name] = true;
            $result[] = (object) [
                'name'            => $row->machine_name,
                'title'           => $row->title,
                'majorVersion'    => (int) $row->major_version,
                'minorVersion'    => (int) $row->minor_version,
                'tutorialUrl'     => $row->tutorial_url,
                'runnable'        => (int) $row->runnable,
                'restricted'      => (bool) $row->restricted,
                'metadataSettings' => $row->metadata_settings ? json_decode($row->metadata_settings) : null,
            ];
        }
        return $result;
    }

    public function alterLibraryFiles(&$files, $libraries)
    {
        // No custom asset alteration.
    }

    public static function saveFileTemporarily($data, $move_file)
    {
        $path = self::$basePath . '/temp';
        if (! is_dir($path)) {
            @mkdir($path, 0755, true);
        }

        $unique   = uniqid('h5p-');
        $filePath = $path . '/' . $unique;

        if ($move_file) {
            rename($data, $filePath);
        } else {
            file_put_contents($filePath, $data);
        }

        DB::table('h5p_tmpfiles')->insert([
            'path'       => $filePath,
            'created_at' => time(),
        ]);

        return (object) ['dir' => $path, 'fileName' => $unique];
    }

    public static function markFileForCleanup($file, $content_id = null)
    {
        DB::table('h5p_tmpfiles')->insert([
            'path'       => $file,
            'created_at' => time(),
        ]);

        return null;
    }

    public static function removeTemporarilySavedFiles($filePath)
    {
        if (is_dir($filePath)) {
            \H5PCore::deleteFileTree($filePath);
        } elseif (file_exists($filePath)) {
            @unlink($filePath);
        }
    }
}
