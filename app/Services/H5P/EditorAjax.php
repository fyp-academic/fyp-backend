<?php

namespace App\Services\H5P;

use H5PEditorAjaxInterface;
use Illuminate\Support\Facades\DB;

/**
 * Editor AJAX backend: surfaces installed library versions and the content-type
 * hub cache to the editor's content-type selector.
 */
class EditorAjax implements H5PEditorAjaxInterface
{
    public function getLatestLibraryVersions()
    {
        // Latest installed version of each runnable library.
        $rows = DB::table('h5p_libraries')
            ->where('runnable', 1)
            ->orderBy('machine_name')
            ->orderBy('major_version', 'desc')
            ->orderBy('minor_version', 'desc')
            ->orderBy('patch_version', 'desc')
            ->get();

        $seen   = [];
        $latest = [];
        foreach ($rows as $row) {
            if (isset($seen[$row->machine_name])) {
                continue;
            }
            $seen[$row->machine_name] = true;
            $latest[] = (object) [
                'id'            => (int) $row->id,
                'machine_name'  => $row->machine_name,
                'major_version' => (int) $row->major_version,
                'minor_version' => (int) $row->minor_version,
                'patch_version' => (int) $row->patch_version,
                'restricted'    => (int) $row->restricted,
                'has_icon'      => (int) $row->has_icon,
            ];
        }

        return $latest;
    }

    public function getContentTypeCache($machineName = null)
    {
        $query = DB::table('h5p_libraries_hub_cache');

        if ($machineName !== null) {
            return $query->where('machine_name', $machineName)->first();
        }

        return $query->get();
    }

    public function getAuthorsRecentlyUsedLibraries()
    {
        return [];
    }

    public function validateEditorToken($token)
    {
        // Editor routes are already gated by Sanctum auth + instructor middleware.
        return true;
    }

    public function getTranslations($libraries, $language_code)
    {
        $translations = [];

        foreach ($libraries as $libraryName) {
            $parsed = \H5PCore::libraryFromString($libraryName);
            if (! $parsed) {
                continue;
            }

            $libraryId = DB::table('h5p_libraries')
                ->where('machine_name', $parsed['machineName'])
                ->where('major_version', $parsed['majorVersion'])
                ->where('minor_version', $parsed['minorVersion'])
                ->value('id');

            if (! $libraryId) {
                continue;
            }

            $translation = DB::table('h5p_libraries_languages')
                ->where('library_id', $libraryId)
                ->where('language_code', $language_code)
                ->value('translation');

            if ($translation) {
                $translations[$libraryName] = $translation;
            }
        }

        return $translations;
    }
}
