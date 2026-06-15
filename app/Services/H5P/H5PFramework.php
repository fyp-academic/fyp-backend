<?php

namespace App\Services\H5P;

use H5PCore;
use H5PFrameworkInterface;
use H5PPermission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Laravel/Eloquent implementation of the H5P core framework interface.
 *
 * Modelled on the reference WordPress/Drupal implementations, adapted to the
 * h5p_* tables (see the create_h5p_tables migration) and Laravel's query
 * builder. File handling is delegated to H5PDefaultStorage by H5PCore.
 */
class H5PFramework implements H5PFrameworkInterface
{
    /** @var string Base URL where H5P content/libraries are served. */
    public string $url;

    /** @var string Absolute filesystem path that backs $url. */
    public string $path;

    private ?string $uploadedH5pPath = null;
    private ?string $uploadedH5pFolderPath = null;

    /** @var array<int, array{0:string,1:?string}> */
    private array $messages = ['error' => [], 'info' => []];

    public function __construct(string $path, string $url)
    {
        $this->path = rtrim($path, '/');
        $this->url  = rtrim($url, '/');
    }

    // ── Platform / messaging ────────────────────────────────────────────

    public function getPlatformInfo()
    {
        return [
            'name'       => 'APES LMS',
            'version'    => '1.0',
            'h5pVersion' => H5PCore::$coreApi['majorVersion'] . '.' . H5PCore::$coreApi['minorVersion'],
        ];
    }

    public function fetchExternalData($url, $data = null, $blocking = true, $stream = null, $fullData = false, $headers = [], $files = [], $method = 'POST')
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if ($method === 'POST' && $data !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : $data);
        }
        if (! empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        // When streaming to a file (library download), write the body to the temp path.
        if ($stream !== null) {
            $response = curl_exec($ch);
            $code     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($response === false || $code >= 400) {
                return false;
            }
            file_put_contents($stream, $response);
            return true;
        }

        $response = curl_exec($ch);
        $status   = ['code' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE)];
        curl_close($ch);

        if ($response === false) {
            return $fullData ? null : '';
        }

        if ($fullData) {
            return ['status' => $status['code'], 'data' => $response, 'headers' => []];
        }

        return $response;
    }

    public function setErrorMessage($message, $code = null)
    {
        if (Auth::check()) {
            $this->messages['error'][] = (object) ['code' => $code, 'message' => $message];
        }
        Log::warning('H5P error', ['code' => $code, 'message' => $message]);
    }

    public function setInfoMessage($message)
    {
        $this->messages['info'][] = $message;
    }

    public function getMessages($type)
    {
        if (empty($this->messages[$type])) {
            return null;
        }
        $messages = $this->messages[$type];
        $this->messages[$type] = [];
        return $messages;
    }

    public function t($message, $replacements = [])
    {
        return empty($replacements) ? $message : preg_replace_callback('/(!|@|%)[a-z0-9-]+/i', function ($matches) use ($replacements) {
            return $replacements[$matches[0]] ?? $matches[0];
        }, $message);
    }

    // ── Paths / URLs ────────────────────────────────────────────────────

    public function getLibraryFileUrl($libraryFolderName, $fileName)
    {
        return $this->url . '/libraries/' . $libraryFolderName . '/' . $fileName;
    }

    public function getUploadedH5pFolderPath($set = null)
    {
        if ($set !== null) {
            $this->uploadedH5pFolderPath = $set;
        }
        return $this->uploadedH5pFolderPath;
    }

    public function getUploadedH5pPath($set = null)
    {
        if ($set !== null) {
            $this->uploadedH5pPath = $set;
        }
        return $this->uploadedH5pPath;
    }

    public function getAdminUrl()
    {
        return '';
    }

    // ── Libraries: load / query ─────────────────────────────────────────

    public function loadAddons()
    {
        return DB::table('h5p_libraries as l1')
            ->select('l1.id as libraryId', 'l1.machine_name as machineName', 'l1.major_version as majorVersion', 'l1.minor_version as minorVersion', 'l1.patch_version as patchVersion', 'l1.add_to as addTo', 'l1.preloaded_js as preloadedJs', 'l1.preloaded_css as preloadedCss')
            ->leftJoin('h5p_libraries as l2', function ($join) {
                $join->on('l1.machine_name', '=', 'l2.machine_name')
                    ->where(function ($q) {
                        $q->whereColumn('l1.major_version', '<', 'l2.major_version')
                          ->orWhere(function ($q2) {
                              $q2->whereColumn('l1.major_version', '=', 'l2.major_version')
                                 ->whereColumn('l1.minor_version', '<', 'l2.minor_version');
                          });
                    });
            })
            ->whereNull('l2.machine_name')
            ->whereNotNull('l1.add_to')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();
    }

    public function getLibraryConfig($libraries = null)
    {
        return null;
    }

    public function loadLibraries()
    {
        $rows = DB::table('h5p_libraries')
            ->select('id', 'machine_name as name', 'title', 'major_version as majorVersion', 'minor_version as minorVersion', 'patch_version as patchVersion', 'runnable', 'restricted')
            ->orderBy('title')->orderBy('major_version')->orderBy('minor_version')
            ->get();

        $libraries = [];
        foreach ($rows as $row) {
            $libraries[$row->name][] = $row;
        }
        return $libraries;
    }

    public function getLibraryId($machineName, $majorVersion = null, $minorVersion = null)
    {
        $q = DB::table('h5p_libraries')->where('machine_name', $machineName);
        if ($majorVersion !== null) {
            $q->where('major_version', $majorVersion);
        }
        if ($minorVersion !== null) {
            $q->where('minor_version', $minorVersion);
        }
        $id = $q->orderBy('major_version', 'desc')->orderBy('minor_version', 'desc')->orderBy('patch_version', 'desc')->value('id');
        return $id ? (int) $id : false;
    }

    public function getWhitelist($isLibrary, $defaultContentWhitelist, $defaultLibraryWhitelist)
    {
        $whitelist = $defaultContentWhitelist;
        if ($isLibrary) {
            $whitelist .= ' ' . $defaultLibraryWhitelist;
        }
        return $whitelist;
    }

    public function isPatchedLibrary($library)
    {
        $patch = DB::table('h5p_libraries')
            ->where('machine_name', $library['machineName'])
            ->where('major_version', $library['majorVersion'])
            ->where('minor_version', $library['minorVersion'])
            ->value('patch_version');

        return $patch !== null && (int) $patch < (int) $library['patchVersion'];
    }

    public function isInDevMode()
    {
        return false;
    }

    public function mayUpdateLibraries()
    {
        return true;
    }

    public function saveLibraryData(&$libraryData, $new = true)
    {
        $embedTypes = isset($libraryData['embedTypes']) ? implode(', ', $libraryData['embedTypes']) : '';
        foreach (['preloadedJs', 'preloadedCss'] as $key) {
            if (isset($libraryData[$key])) {
                $libraryData[$key] = array_map(fn ($p) => $p['path'], $libraryData[$key]);
            }
        }

        $data = [
            'title'             => $libraryData['title'],
            'machine_name'      => $libraryData['machineName'],
            'major_version'     => $libraryData['majorVersion'],
            'minor_version'     => $libraryData['minorVersion'],
            'patch_version'     => $libraryData['patchVersion'],
            'runnable'          => $libraryData['runnable'],
            'fullscreen'        => $libraryData['fullscreen'] ?? 0,
            'embed_types'       => $embedTypes,
            'preloaded_js'      => isset($libraryData['preloadedJs']) ? implode(',', $libraryData['preloadedJs']) : null,
            'preloaded_css'     => isset($libraryData['preloadedCss']) ? implode(',', $libraryData['preloadedCss']) : null,
            'drop_library_css'  => isset($libraryData['dropLibraryCss']) ? implode(', ', array_map(fn ($l) => $l['machineName'], $libraryData['dropLibraryCss'])) : null,
            'semantics'         => $libraryData['semantics'] ?? null,
            'tutorial_url'      => $libraryData['tutorialUrl'] ?? '',
            'has_icon'          => $libraryData['hasIcon'] ? 1 : 0,
            'metadata_settings' => $libraryData['metadataSettings'] ?? null,
            'add_to'            => isset($libraryData['addTo']) ? json_encode($libraryData['addTo']) : null,
            'updated_at'        => now(),
        ];

        if ($new) {
            $data['created_at'] = now();
            $libraryData['libraryId'] = (int) DB::table('h5p_libraries')->insertGetId($data);
        } else {
            DB::table('h5p_libraries')->where('id', $libraryData['libraryId'])->update($data);
            $this->deleteLibraryDependencies($libraryData['libraryId']);
            DB::table('h5p_libraries_languages')->where('library_id', $libraryData['libraryId'])->delete();
        }

        if (isset($libraryData['language'])) {
            foreach ($libraryData['language'] as $languageCode => $translation) {
                DB::table('h5p_libraries_languages')->insert([
                    'library_id'    => $libraryData['libraryId'],
                    'language_code' => $languageCode,
                    'translation'   => $translation,
                ]);
            }
        }
    }

    public function saveLibraryDependencies($libraryId, $dependencies, $dependencyType)
    {
        foreach ($dependencies as $dependency) {
            $requiredId = $this->getLibraryId($dependency['machineName'], $dependency['majorVersion'], $dependency['minorVersion']);
            if (! $requiredId) {
                continue;
            }
            DB::table('h5p_libraries_libraries')->updateOrInsert(
                ['library_id' => $libraryId, 'required_library_id' => $requiredId],
                ['dependency_type' => $dependencyType]
            );
        }
    }

    public function loadLibrary($machineName, $majorVersion, $minorVersion)
    {
        $library = DB::table('h5p_libraries')
            ->where('machine_name', $machineName)
            ->where('major_version', $majorVersion)
            ->where('minor_version', $minorVersion)
            ->first();

        if (! $library) {
            return false;
        }

        $result = [
            'libraryId'        => (int) $library->id,
            'title'            => $library->title,
            'machineName'      => $library->machine_name,
            'majorVersion'     => (int) $library->major_version,
            'minorVersion'     => (int) $library->minor_version,
            'patchVersion'     => (int) $library->patch_version,
            'runnable'         => (int) $library->runnable,
            'fullscreen'       => (int) $library->fullscreen,
            'embedTypes'       => $library->embed_types,
            'preloadedJs'      => $library->preloaded_js,
            'preloadedCss'     => $library->preloaded_css,
            'dropLibraryCss'   => $library->drop_library_css,
            'semantics'        => $library->semantics,
            'tutorialUrl'      => $library->tutorial_url,
            'hasIcon'          => (int) $library->has_icon,
            'metadataSettings' => $library->metadata_settings,
        ];

        $dependencies = DB::table('h5p_libraries_libraries as ll')
            ->join('h5p_libraries as l', 'll.required_library_id', '=', 'l.id')
            ->where('ll.library_id', $library->id)
            ->select('l.machine_name as machineName', 'l.major_version as majorVersion', 'l.minor_version as minorVersion', 'll.dependency_type as dependencyType')
            ->get();

        foreach ($dependencies as $dependency) {
            $result[$dependency->dependencyType . 'Dependencies'][] = [
                'machineName'  => $dependency->machineName,
                'majorVersion' => (int) $dependency->majorVersion,
                'minorVersion' => (int) $dependency->minorVersion,
            ];
        }

        return $result;
    }

    public function loadLibrarySemantics($machineName, $majorVersion, $minorVersion)
    {
        return DB::table('h5p_libraries')
            ->where('machine_name', $machineName)
            ->where('major_version', $majorVersion)
            ->where('minor_version', $minorVersion)
            ->value('semantics');
    }

    public function alterLibrarySemantics(&$semantics, $machineName, $majorVersion, $minorVersion)
    {
        // No semantic alterations.
    }

    public function deleteLibraryDependencies($libraryId)
    {
        DB::table('h5p_libraries_libraries')->where('library_id', $libraryId)->delete();
    }

    public function lockDependencyStorage()
    {
        // SQLite/MySQL transactions are not required here.
    }

    public function unlockDependencyStorage()
    {
        // No-op (see lockDependencyStorage).
    }

    public function deleteLibrary($library)
    {
        $id = is_object($library) ? $library->id : $library['libraryId'] ?? $library['id'];
        \H5PCore::deleteFileTree($this->path . '/libraries/' . \H5PCore::libraryToFolderName(is_object($library) ? [
            'machineName'  => $library->name ?? $library->machine_name ?? '',
            'majorVersion' => $library->major_version ?? 0,
            'minorVersion' => $library->minor_version ?? 0,
        ] : $library));

        DB::table('h5p_libraries_libraries')->where('library_id', $id)->orWhere('required_library_id', $id)->delete();
        DB::table('h5p_libraries_languages')->where('library_id', $id)->delete();
        DB::table('h5p_libraries')->where('id', $id)->delete();
    }

    public function getLibraryUsage($libraryId, $skipContent = false)
    {
        $content = $skipContent ? -1 : (int) DB::table('h5p_contents_libraries')->where('library_id', $libraryId)->distinct('content_id')->count('content_id');
        $libraries = (int) DB::table('h5p_libraries_libraries')->where('required_library_id', $libraryId)->count();

        return ['content' => $content, 'libraries' => $libraries];
    }

    public function getLibraryContentCount()
    {
        $rows = DB::table('h5p_contents_libraries as cl')
            ->join('h5p_libraries as l', 'cl.library_id', '=', 'l.id')
            ->where('cl.dependency_type', 'preloaded')
            ->select('l.machine_name', 'l.major_version', 'l.minor_version', DB::raw('COUNT(DISTINCT cl.content_id) as count'))
            ->groupBy('l.machine_name', 'l.major_version', 'l.minor_version')
            ->get();

        $count = [];
        foreach ($rows as $row) {
            $count[$row->machine_name . ' ' . $row->major_version . '.' . $row->minor_version] = (int) $row->count;
        }
        return $count;
    }

    public function getLibraryStats($type)
    {
        return [];
    }

    public function libraryHasUpgrade($library)
    {
        return DB::table('h5p_libraries')
            ->where('machine_name', $library['machineName'])
            ->where(function ($q) use ($library) {
                $q->where('major_version', '>', $library['majorVersion'])
                  ->orWhere(function ($q2) use ($library) {
                      $q2->where('major_version', $library['majorVersion'])
                         ->where('minor_version', '>', $library['minorVersion']);
                  });
            })
            ->exists();
    }

    // ── Content: CRUD ───────────────────────────────────────────────────

    public function insertContent($content, $contentMainId = null)
    {
        return $this->updateContent($content);
    }

    public function updateContent($content, $contentMainId = null)
    {
        $libraryId = $content['library']['libraryId'] ?? null;

        $data = [
            'title'      => $content['title'] ?? '',
            'parameters' => $content['params'] ?? ($content['parameters'] ?? ''),
            'filtered'   => $content['filtered'] ?? '',
            'slug'       => $content['slug'] ?? '',
            'embed_type' => $content['embedType'] ?? 'div',
            'disable'    => $content['disable'] ?? 0,
            'library_id' => $libraryId,
            'updated_at' => now(),
        ];

        // Metadata
        $meta = $content['metadata'] ?? [];
        foreach ([
            'authors' => 'authors', 'source' => 'source', 'yearFrom' => 'year_from', 'yearTo' => 'year_to',
            'license' => 'license', 'licenseVersion' => 'license_version', 'licenseExtras' => 'license_extras',
            'authorComments' => 'author_comments', 'changes' => 'changes', 'defaultLanguage' => 'default_language',
            'a11yTitle' => 'a11y_title',
        ] as $key => $col) {
            if (isset($meta[$key])) {
                $data[$col] = is_array($meta[$key]) ? json_encode($meta[$key]) : $meta[$key];
            }
        }
        if (! empty($meta['title'])) {
            $data['title'] = $meta['title'];
        }

        if (empty($content['id'])) {
            $data['created_at'] = now();
            $data['user_id']    = Auth::id();
            return (int) DB::table('h5p_contents')->insertGetId($data);
        }

        DB::table('h5p_contents')->where('id', $content['id'])->update($data);
        return (int) $content['id'];
    }

    public function loadContent($id)
    {
        $content = DB::table('h5p_contents as c')
            ->join('h5p_libraries as l', 'c.library_id', '=', 'l.id')
            ->where('c.id', $id)
            ->select(
                'c.id', 'c.title', 'c.parameters as params', 'c.filtered',
                'c.slug', 'c.embed_type as embedType', 'c.disable',
                'c.library_id as libraryId', 'c.authors', 'c.source', 'c.year_from as yearFrom',
                'c.year_to as yearTo', 'c.license', 'c.license_version as licenseVersion',
                'c.license_extras as licenseExtras', 'c.author_comments as authorComments',
                'c.changes', 'c.default_language as defaultLanguage', 'c.a11y_title as a11yTitle',
                'l.machine_name as libraryName', 'l.major_version as libraryMajorVersion',
                'l.minor_version as libraryMinorVersion', 'l.embed_types as libraryEmbedTypes',
                'l.fullscreen as libraryFullscreen'
            )
            ->first();

        if (! $content) {
            return null;
        }

        $content = (array) $content;
        // H5PCore::loadContent expects a metadata sub-array.
        $content['metadata'] = array_filter([
            'title'           => $content['title'],
            'authors'         => ! empty($content['authors']) ? json_decode($content['authors'], true) : null,
            'source'          => $content['source'] ?? null,
            'yearFrom'        => $content['yearFrom'] ?? null,
            'yearTo'          => $content['yearTo'] ?? null,
            'license'         => $content['license'] ?? null,
            'licenseVersion'  => $content['licenseVersion'] ?? null,
            'licenseExtras'   => $content['licenseExtras'] ?? null,
            'authorComments'  => $content['authorComments'] ?? null,
            'changes'         => ! empty($content['changes']) ? json_decode($content['changes'], true) : null,
            'defaultLanguage' => $content['defaultLanguage'] ?? null,
            'a11yTitle'       => $content['a11yTitle'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        return $content;
    }

    public function loadContentDependencies($id, $type = null)
    {
        $query = DB::table('h5p_contents_libraries as cl')
            ->join('h5p_libraries as l', 'cl.library_id', '=', 'l.id')
            ->where('cl.content_id', $id)
            ->select(
                'l.id as libraryId', 'l.machine_name as machineName', 'l.major_version as majorVersion',
                'l.minor_version as minorVersion', 'l.patch_version as patchVersion',
                'l.preloaded_css as preloadedCss', 'l.preloaded_js as preloadedJs',
                'cl.drop_css as dropCss', 'cl.dependency_type as dependencyType'
            )
            ->orderBy('cl.weight');

        if ($type !== null) {
            $query->where('cl.dependency_type', $type);
        }

        return $query->get()->map(fn ($r) => (array) $r)->toArray();
    }

    public function deleteContentData($contentId)
    {
        DB::table('h5p_contents')->where('id', $contentId)->delete();
        $this->deleteLibraryUsage($contentId);
        DB::table('h5p_results')->where('content_id', $contentId)->delete();
    }

    public function deleteLibraryUsage($contentId)
    {
        DB::table('h5p_contents_libraries')->where('content_id', $contentId)->delete();
    }

    public function saveLibraryUsage($contentId, $librariesInUse)
    {
        $dropLibraryCssList = [];
        foreach ($librariesInUse as $dependency) {
            if (! empty($dependency['library']['dropLibraryCss'])) {
                $dropLibraryCssList = array_merge($dropLibraryCssList, explode(', ', $dependency['library']['dropLibraryCss']));
            }
        }

        $this->deleteLibraryUsage($contentId);
        foreach ($librariesInUse as $dependency) {
            DB::table('h5p_contents_libraries')->insert([
                'content_id'      => $contentId,
                'library_id'      => $dependency['library']['libraryId'],
                'dependency_type' => $dependency['type'],
                'drop_css'        => in_array($dependency['library']['machineName'], $dropLibraryCssList, true) ? 1 : 0,
                'weight'          => $dependency['weight'],
            ]);
        }
    }

    public function copyLibraryUsage($contentId, $copyFromId, $contentMainId = null)
    {
        $rows = DB::table('h5p_contents_libraries')->where('content_id', $copyFromId)->get();
        foreach ($rows as $row) {
            DB::table('h5p_contents_libraries')->insert([
                'content_id'      => $contentId,
                'library_id'      => $row->library_id,
                'dependency_type' => $row->dependency_type,
                'drop_css'        => $row->drop_css,
                'weight'          => $row->weight,
            ]);
        }
    }

    public function resetContentUserData($contentId)
    {
        // User state persistence not implemented.
    }

    public function updateContentFields($id, $fields)
    {
        DB::table('h5p_contents')->where('id', $id)->update($fields + ['updated_at' => now()]);
    }

    public function clearFilteredParameters($library_ids)
    {
        DB::table('h5p_contents')->whereIn('library_id', (array) $library_ids)->update(['filtered' => '']);
    }

    public function getNumNotFiltered()
    {
        return (int) DB::table('h5p_contents')->where('filtered', '')->count();
    }

    public function getNumContent($libraryId, $skip = null)
    {
        $query = DB::table('h5p_contents')->where('library_id', $libraryId);
        if (is_array($skip) && ! empty($skip)) {
            $query->whereNotIn('id', $skip);
        }
        return (int) $query->count();
    }

    public function isContentSlugAvailable($slug)
    {
        return ! DB::table('h5p_contents')->where('slug', $slug)->exists();
    }

    public function getNumAuthors()
    {
        return (int) DB::table('h5p_contents')->whereNotNull('user_id')->distinct('user_id')->count('user_id');
    }

    // ── Cached assets ───────────────────────────────────────────────────

    public function saveCachedAssets($key, $libraries)
    {
        foreach ($libraries as $library) {
            $id = $library['id'] ?? ($library['libraryId'] ?? null);
            if ($id) {
                DB::table('h5p_libraries_cachedassets')->updateOrInsert(
                    ['library_id' => $id, 'hash' => $key],
                    ['library_id' => $id, 'hash' => $key]
                );
            }
        }
    }

    public function deleteCachedAssets($library_id)
    {
        $hashes = DB::table('h5p_libraries_cachedassets')->whereIn('library_id', (array) $library_id)->pluck('hash')->unique();
        DB::table('h5p_libraries_cachedassets')->whereIn('hash', $hashes)->delete();
        return $hashes->values()->all();
    }

    // ── Options (key/value store) ───────────────────────────────────────

    public function getOption($name, $default = null)
    {
        $value = DB::table('h5p_options')->where('name', $name)->value('value');
        if ($value === null) {
            return $default;
        }
        $decoded = json_decode($value, true);
        return $decoded === null && $value !== 'null' ? $value : $decoded;
    }

    public function setOption($name, $value)
    {
        DB::table('h5p_options')->updateOrInsert(
            ['name' => $name],
            ['value' => is_scalar($value) ? (string) $value : json_encode($value)]
        );
    }

    // ── Export / permissions ────────────────────────────────────────────

    public function afterExportCreated($content, $filename)
    {
        // No post-export hook needed.
    }

    public function hasPermission($permission, $id = null)
    {
        // Instructors/admins manage H5P; the route middleware already gates access.
        return in_array($permission, [
            H5PPermission::CREATE_RESTRICTED,
            H5PPermission::UPDATE_LIBRARIES,
            H5PPermission::INSTALL_RECOMMENDED,
            H5PPermission::COPY_H5P,
        ], true);
    }

    // ── Content type hub cache ──────────────────────────────────────────

    public function replaceContentTypeCache($contentTypeCache)
    {
        DB::table('h5p_libraries_hub_cache')->truncate();
        if (! isset($contentTypeCache->contentTypes)) {
            return;
        }
        foreach ($contentTypeCache->contentTypes as $ct) {
            DB::table('h5p_libraries_hub_cache')->insert([
                'machine_name'      => $ct->id,
                'major_version'     => $ct->version->major,
                'minor_version'     => $ct->version->minor,
                'patch_version'     => $ct->version->patch,
                'h5p_major_version' => $ct->coreApiVersionNeeded->major ?? null,
                'h5p_minor_version' => $ct->coreApiVersionNeeded->minor ?? null,
                'title'             => $ct->title,
                'summary'           => $ct->summary ?? '',
                'description'       => $ct->description ?? '',
                'icon'              => $ct->icon ?? '',
                'created_at'        => strtotime($ct->createdAt ?? 'now'),
                'updated_at'        => strtotime($ct->updatedAt ?? 'now'),
                'is_recommended'    => ! empty($ct->isRecommended) ? 1 : 0,
                'popularity'        => $ct->popularity ?? 0,
                'screenshots'       => json_encode($ct->screenshots ?? []),
                'license'           => json_encode($ct->license ?? new \stdClass()),
                'example'           => $ct->example ?? '',
                'tutorial'          => $ct->tutorial ?? '',
                'keywords'          => json_encode($ct->keywords ?? []),
                'categories'        => json_encode($ct->categories ?? []),
                'owner'             => $ct->owner ?? '',
            ]);
        }
    }

    public function replaceContentHubMetadataCache($metadata, $lang)
    {
        $this->setOption('content_hub_metadata_' . $lang, $metadata);
    }

    public function getContentHubMetadataCache($lang = 'en')
    {
        return $this->getOption('content_hub_metadata_' . $lang, null);
    }

    public function getContentHubMetadataChecked($lang = 'en')
    {
        return $this->getOption('content_hub_metadata_checked_' . $lang, null);
    }

    public function setContentHubMetadataChecked($time, $lang = 'en')
    {
        $this->setOption('content_hub_metadata_checked_' . $lang, $time);
        return true;
    }

    public function resetHubOrganizationData()
    {
        // No cached organization data to reset.
    }

    public function setLibraryTutorialUrl($machineName, $tutorialUrl)
    {
        DB::table('h5p_libraries')->where('machine_name', $machineName)->update(['tutorial_url' => $tutorialUrl]);
    }
}
