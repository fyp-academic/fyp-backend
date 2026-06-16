<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>H5P Editor</title>
    @foreach ($coreStyles as $style)
        <link rel="stylesheet" href="{{ $style }}">
    @endforeach
    @foreach ($editorStyles as $style)
        <link rel="stylesheet" href="{{ $style }}">
    @endforeach
    <style>
        html, body { margin: 0; padding: 0; background: #f8fafc; font-family: system-ui, sans-serif; }
        #h5p-editor { padding: 16px; min-height: 100vh; box-sizing: border-box; }
    </style>
</head>
<body>
    <div id="h5p-editor"></div>

    <script>window.H5PIntegration = @json($integration);</script>
    @foreach ($coreScripts as $script)
        <script src="{{ $script }}"></script>
    @endforeach
    @foreach ($editorScripts as $script)
        <script src="{{ $script }}"></script>
    @endforeach

    <script>
    (function () {
        var LIBRARY = @json($library);
        var PARAMS  = @json($params);

        function boot() {
            if (! window.H5P || ! window.H5PEditor || ! H5P.jQuery) { return false; }

            var ns  = window.H5PEditor;
            var cfg = window.H5PIntegration.editor;

            ns.$                 = H5P.jQuery;
            ns.basePath          = cfg.libraryUrl;
            ns.fileIcon          = cfg.fileIcon;
            ns.ajaxPath          = cfg.ajaxPath;
            ns.filesPath         = cfg.filesPath;
            ns.apiVersion        = cfg.apiVersion;
            ns.contentLanguage   = cfg.language;
            ns.copyrightSemantics = cfg.copyrightSemantics;
            ns.metadataSemantics = cfg.metadataSemantics;
            ns.assets            = cfg.assets;
            ns.baseUrl           = '';
            ns.enableContentHub  = false;

            // Host integration must provide getAjaxUrl (normally in
            // h5peditor-init.js, which we don't load). Defined on the outer
            // namespace; the editor form iframe inherits it via h5peditor.js's
            // window.parent.H5PEditor copy.
            ns.getAjaxUrl = function (action, parameters) {
                var url = cfg.ajaxPath + action;
                if (parameters !== undefined) {
                    var sep = url.indexOf('?') === -1 ? '?' : '&';
                    for (var p in parameters) {
                        if (Object.prototype.hasOwnProperty.call(parameters, p)) {
                            url += sep + p + '=' + parameters[p];
                            sep = '&';
                        }
                    }
                }
                return url;
            };

            var element = document.getElementById('h5p-editor');
            var editor  = new ns.Editor(LIBRARY, PARAMS, element);

            // Respond to the parent SPA's request for the authored content.
            window.addEventListener('message', function (e) {
                if (! e.data || e.data.type !== 'h5p-get-content') { return; }
                try {
                    editor.getContent(
                        function (content) {
                            parent.postMessage({
                                type:    'h5p-content',
                                library: content.library,
                                params:  content.params,
                                title:   content.title
                            }, '*');
                        },
                        function (reason) {
                            // Invalid content (missing title/library/params, validation, etc.)
                            parent.postMessage({ type: 'h5p-content-error', reason: String(reason) }, '*');
                        }
                    );
                } catch (err) {
                    parent.postMessage({ type: 'h5p-content-error', reason: String(err) }, '*');
                }
            });

            parent.postMessage({ type: 'h5p-editor-ready' }, '*');
            return true;
        }

        if (! boot()) {
            var tries = 0;
            var t = setInterval(function () {
                if (boot() || ++tries > 100) { clearInterval(t); }
            }, 150);
        }
    })();
    </script>
</body>
</html>
