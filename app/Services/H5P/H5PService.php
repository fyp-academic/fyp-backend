<?php

namespace App\Services\H5P;

use H5PContentValidator;
use H5PCore;
use H5peditor;
use H5PEditorAjax;
use H5PStorage;
use H5PValidator;

/**
 * Wires the official H5P core + editor libraries to the Laravel adapters and
 * builds the H5PIntegration payloads consumed by the player and editor views.
 */
class H5PService
{
    public H5PFramework $framework;
    public H5PCore $core;
    public H5PValidator $validator;
    public H5PStorage $storage;
    public H5PContentValidator $contentValidator;
    public H5peditor $editor;
    public EditorAjax $editorAjax;
    public H5PEditorAjax $ajaxHandler;

    public string $filesPath;
    public string $filesUrl;
    public string $coreUrl;
    public string $editorUrl;

    public function __construct()
    {
        $this->filesPath = storage_path('app/public/h5p');
        $this->filesUrl  = rtrim(url('storage/h5p'), '/');
        $this->coreUrl   = rtrim(url('vendor/h5p-core'), '/');
        $this->editorUrl = rtrim(url('vendor/h5p-editor'), '/');

        foreach (['', '/libraries', '/content', '/temp', '/editor', '/cachedassets', '/exports'] as $sub) {
            $dir = $this->filesPath . $sub;
            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }

        EditorStorage::$basePath = $this->filesPath;

        $this->framework        = new H5PFramework($this->filesPath, $this->filesUrl);
        // Export disabled: we don't offer .h5p downloads, and the export path
        // (zipping content + all libraries) is heavy/fragile on large packages.
        $this->core             = new H5PCore($this->framework, $this->filesPath, $this->filesUrl, 'en', false);
        $this->validator        = new H5PValidator($this->framework, $this->core);
        $this->storage          = new H5PStorage($this->framework, $this->core);
        $this->contentValidator = new H5PContentValidator($this->framework, $this->core);
        $this->editorAjax       = new EditorAjax();
        $editorStorage          = new EditorStorage();
        $this->editor           = new H5peditor($this->core, $editorStorage, $this->editorAjax);
        $this->ajaxHandler      = new H5PEditorAjax($this->core, $this->editor, $editorStorage);
    }

    // ── Package / content persistence ───────────────────────────────────

    /**
     * Validate and install an uploaded .h5p file as new content.
     * Returns the new content id, or null on failure (messages via framework).
     */
    public function installPackage(string $absoluteH5pFilePath): ?int
    {
        // Tell the validator where the uploaded package lives.
        $this->framework->getUploadedH5pPath($absoluteH5pFilePath);
        $this->framework->getUploadedH5pFolderPath(preg_replace('/\.h5p$/', '', $absoluteH5pFilePath));

        if (! $this->validator->isValidPackage(false, false)) {
            return null;
        }

        $content = ['disable' => H5PCore::DISABLE_NONE];
        $this->storage->savePackage($content);

        return isset($this->storage->contentId) ? (int) $this->storage->contentId : null;
    }

    /**
     * Persist content produced by the editor.
     *
     * @param string $library  uber name, e.g. "H5P.MultiChoice 1.16"
     * @param string $paramsJson JSON string: {"params":{...},"metadata":{...}}
     */
    public function saveEditorContent(string $library, string $paramsJson, ?int $existingId = null): ?int
    {
        $libraryData = H5PCore::libraryFromString($library);
        if (! $libraryData) {
            return null;
        }
        $libraryId = $this->framework->getLibraryId($libraryData['machineName'], $libraryData['majorVersion'], $libraryData['minorVersion']);
        if (! $libraryId) {
            return null;
        }
        $libraryData['libraryId'] = $libraryId;

        $decoded   = json_decode($paramsJson);
        $params    = $decoded->params ?? $decoded;
        $metadata  = $decoded->metadata ?? new \stdClass();

        $oldLibrary = null;
        $oldParams  = null;
        if ($existingId) {
            $existing = $this->framework->loadContent($existingId);
            if ($existing) {
                $oldLibrary = [
                    'machineName'  => $existing['libraryName'],
                    'majorVersion' => $existing['libraryMajorVersion'],
                    'minorVersion' => $existing['libraryMinorVersion'],
                ];
                $oldParams = json_decode($existing['params']);
            }
        }

        $content = [
            'id'       => $existingId,
            'title'    => $metadata->title ?? 'Interactive content',
            'library'  => $libraryData,
            'params'   => json_encode($params),
            'metadata' => (array) $metadata,
            'disable'  => H5PCore::DISABLE_NONE,
        ];

        $contentId = $this->core->saveContent($content);

        // Move uploaded editor files into the content folder + track library usage.
        $this->editor->processParameters(
            ['id' => $contentId, 'library' => $libraryData],
            $libraryData,
            $params,
            $oldLibrary,
            $oldParams
        );

        return (int) $contentId;
    }

    public function deleteContent(int $contentId): void
    {
        $content = $this->framework->loadContent($contentId);
        if ($content) {
            $this->core->fs->deleteContent(['id' => $contentId]);
        }
        $this->framework->deleteContentData($contentId);
    }

    // ── Player integration ──────────────────────────────────────────────

    /**
     * Build the data the player view needs: H5PIntegration array plus the core
     * and per-content asset URLs to include on the page.
     *
     * @return array{integration:array,coreScripts:array,coreStyles:array}|null
     */
    public function playerData(int $contentId): ?array
    {
        $content = $this->core->loadContent($contentId);
        if (! $content) {
            return null;
        }

        $filtered     = $this->core->filterParameters($content);
        $dependencies = $this->core->loadContentDependencies($contentId, 'preloaded');

        // Self-heal: a content whose dependency cache is empty/stale (e.g. saved
        // before this code, or whose first filter never persisted usage) would
        // render with no library scripts → "Unable to find constructor". Force a
        // fresh recompute by clearing the filtered cache and re-filtering.
        if (empty($dependencies)) {
            $this->framework->updateContentFields($contentId, ['filtered' => '']);
            $fresh = $this->core->loadContent($contentId);
            if ($fresh) {
                $content  = $fresh;
                $filtered = $this->core->filterParameters($content);
            }
            $dependencies = $this->core->loadContentDependencies($contentId, 'preloaded');
            if (empty($dependencies)) {
                \Illuminate\Support\Facades\Log::warning('H5P: no preloaded dependencies for content ' . $contentId . ' — its library may not be installed.', [
                    'errors' => $this->framework->getMessages('error'),
                ]);
            }
        }

        $files = $this->core->getDependenciesFiles($dependencies);

        $contentScripts = $this->assetUrls($files['scripts']);
        $contentStyles  = $this->assetUrls($files['styles']);

        $libraryString = H5PCore::libraryToString([
            'machineName'  => $content['library']['name'],
            'majorVersion' => $content['library']['majorVersion'],
            'minorVersion' => $content['library']['minorVersion'],
        ]);

        $integration = $this->baseIntegration();
        $integration['contents']['cid-' . $contentId] = [
            'library'         => $libraryString,
            'jsonContent'     => $filtered,
            'fullScreen'      => $content['library']['fullscreen'] ?? 0,
            'exportUrl'       => '',
            'embedCode'       => '',
            'resizeCode'      => '',
            'mainId'          => $contentId,
            'url'             => $this->filesUrl . '/content/' . $contentId,
            'title'           => $content['title'],
            'displayOptions'  => [
                'frame'     => false,
                'export'    => false,
                'embed'     => false,
                'copyright' => false,
                'icon'      => false,
            ],
            'contentUserData' => [['state' => '{}']],
            'metadata'        => ['title' => $content['title']],
            'scripts'         => $contentScripts,
            'styles'          => $contentStyles,
        ];

        return [
            'integration' => $integration,
            'coreScripts' => $this->coreScriptUrls(),
            'coreStyles'  => $this->coreStyleUrls(),
        ];
    }

    // ── Editor integration ──────────────────────────────────────────────

    /**
     * @return array{integration:array,coreScripts:array,coreStyles:array,editorScripts:array,editorStyles:array,library:string,params:string}
     */
    public function editorData(?int $contentId, string $ajaxPath): array
    {
        $library = '';
        $params  = '{"params":{},"metadata":{}}';

        if ($contentId) {
            $content = $this->core->loadContent($contentId);
            if ($content) {
                $library = H5PCore::libraryToString([
                    'machineName'  => $content['library']['name'],
                    'majorVersion' => $content['library']['majorVersion'],
                    'minorVersion' => $content['library']['minorVersion'],
                ]);
                $params = json_encode([
                    'params'   => json_decode($content['params']),
                    'metadata' => $content['metadata'] ?? ['title' => $content['title']],
                ]);
            }
        }

        $integration = $this->baseIntegration();
        $integration['editor'] = [
            'filesPath'         => $this->filesUrl . '/editor',
            'fileIcon'          => [
                'path'   => $this->editorUrl . '/images/binary-file.png',
                'width'  => 50,
                'height' => 50,
            ],
            'ajaxPath'          => $ajaxPath,
            'libraryUrl'        => $this->editorUrl . '/',
            'copyrightSemantics' => $this->contentValidator->getCopyrightSemantics(),
            'metadataSemantics' => $this->contentValidator->getMetadataSemantics(),
            'assets'            => [
                'css' => array_merge($this->coreStyleUrls(), $this->editorStyleUrls()),
                'js'  => array_merge($this->coreScriptUrls(), $this->editorScriptUrls(), [
                    $this->editorUrl . '/language/en.js',
                ]),
            ],
            'deleteMessage'     => 'Are you sure you wish to delete this content?',
            'apiVersion'        => H5PCore::$coreApi,
            'language'          => 'en',
            'enableContentHub'  => false,
            'hub'               => ['contentSearchUrl' => ''],
        ];

        return [
            'integration'   => $integration,
            'coreScripts'   => $this->coreScriptUrls(),
            'coreStyles'    => $this->coreStyleUrls(),
            'editorScripts' => array_merge($this->editorScriptUrls(), [$this->editorUrl . '/language/en.js']),
            'editorStyles'  => $this->editorStyleUrls(),
            'library'       => $library,
            'params'        => $params,
        ];
    }

    // ── Internals ───────────────────────────────────────────────────────

    private function baseIntegration(): array
    {
        return [
            'baseUrl'            => rtrim(url('/'), '/'),
            'url'                => $this->filesUrl,
            'urlLibraries'       => $this->filesUrl . '/libraries',
            'postUserStatistics' => false,
            'saveFreq'           => false,
            'siteUrl'            => rtrim(url('/'), '/'),
            'l10n'               => ['H5P' => $this->coreL10n()],
            // Hub disabled: the editor uses the legacy content-type selector populated
            // from installed libraries. Instructors add content types by uploading .h5p
            // packages (which install their libraries), avoiding any hub dependency.
            'hubIsEnabled'       => false,
            'reportingIsEnabled' => false,
            'crossorigin'        => null,
            'pluginCacheBuster'  => '?ver=' . (H5PCore::$coreApi['majorVersion'] . '.' . H5PCore::$coreApi['minorVersion']),
            'libraryUrl'         => $this->coreUrl . '/js',
            'core'               => [
                'scripts' => $this->coreScriptUrls(),
                'styles'  => $this->coreStyleUrls(),
            ],
            'contents'           => [],
        ];
    }

    /** @param array<int, object> $files */
    private function assetUrls(array $files): array
    {
        return array_map(function ($file) {
            return $this->filesUrl . '/' . ltrim($file->path, '/') . $file->version;
        }, $files);
    }

    private function coreScriptUrls(): array
    {
        return array_map(fn ($s) => $this->coreUrl . '/' . $s, H5PCore::$scripts);
    }

    private function coreStyleUrls(): array
    {
        return array_map(fn ($s) => $this->coreUrl . '/' . $s, H5PCore::$styles);
    }

    private function editorScriptUrls(): array
    {
        return array_map(fn ($s) => $this->editorUrl . '/' . $s, H5peditor::$scripts);
    }

    private function editorStyleUrls(): array
    {
        return array_map(fn ($s) => $this->editorUrl . '/' . $s, H5peditor::$styles);
    }

    private function coreL10n(): array
    {
        return [
            'fullscreen'                  => 'Fullscreen',
            'disableFullscreen'           => 'Disable fullscreen',
            'download'                    => 'Download',
            'copyrights'                  => 'Rights of use',
            'embed'                       => 'Embed',
            'size'                        => 'Size',
            'showAdvanced'                => 'Show advanced',
            'hideAdvanced'                => 'Hide advanced',
            'advancedHelp'                => 'Include this script on your website if you want dynamic sizing of the embedded content:',
            'copyrightInformation'        => 'Rights of use',
            'close'                       => 'Close',
            'title'                       => 'Title',
            'author'                      => 'Author',
            'year'                        => 'Year',
            'source'                      => 'Source',
            'license'                     => 'License',
            'thumbnail'                   => 'Thumbnail',
            'noCopyrights'                => 'No copyright information available for this content.',
            'reuse'                       => 'Reuse',
            'reuseContent'                => 'Reuse Content',
            'reuseDescription'            => 'Reuse this content.',
            'downloadDescription'         => 'Download this content as a H5P file.',
            'copyrightsDescription'       => 'View the copyright information for this content.',
            'embedDescription'            => 'View the embed code for this content.',
            'h5pDescription'              => 'Visit H5P.org to check out more cool content.',
            'contentChanged'              => 'This content has changed since you last used it.',
            'startingOver'                => "You'll be starting over.",
            'by'                          => 'by',
            'showMore'                    => 'Show more',
            'showLess'                    => 'Show less',
            'subLevel'                    => 'Sublevel',
            'confirmDialogHeader'         => 'Confirm action',
            'confirmDialogBody'           => 'Please confirm that you wish to proceed. This action is not reversible.',
            'cancelLabel'                 => 'Cancel',
            'confirmLabel'                => 'Confirm',
            'licenseU'                    => 'Undisclosed',
            'licenseCCBY'                 => 'Attribution',
            'licenseCCBYSA'               => 'Attribution-ShareAlike',
            'licenseCCBYND'               => 'Attribution-NoDerivs',
            'licenseCCBYNC'               => 'Attribution-NonCommercial',
            'licenseCCBYNCSA'             => 'Attribution-NonCommercial-ShareAlike',
            'licenseCCBYNCND'             => 'Attribution-NonCommercial-NoDerivs',
            'licenseCC40'                 => '4.0 International',
            'licenseCC30'                 => '3.0 Unported',
            'licenseGPL'                  => 'General Public License',
            'licenseV3'                   => 'Version 3',
            'licenseCC010'                => 'CC0 1.0 Universal (CC0 1.0) Public Domain Dedication',
            'licensePD'                   => 'Public Domain',
            'licensePDM'                  => 'Public Domain Mark',
            'licenseC'                    => 'Copyright',
            'contentType'                 => 'Content Type',
            'licenseExtras'               => 'License Extras',
            'changes'                     => 'Changelog',
            'contentCopied'               => 'Content is copied to the clipboard',
            'connectionLost'              => 'Connection lost. Results will be stored and sent when you regain connection.',
            'connectionReestablished'     => 'Connection reestablished.',
            'resubmitScores'              => 'Attempting to submit stored results.',
            'offlineDialogHeader'         => 'Your connection to the server was lost',
            'offlineDialogBody'           => 'We were unable to send information about your completion of this task. Please check your internet connection.',
            'offlineDialogRetryMessage'   => 'Retrying in :num....',
            'offlineDialogRetryButtonLabel' => 'Retry now',
            'offlineSuccessfulSubmit'     => 'Successfully submitted results.',
        ];
    }
}
