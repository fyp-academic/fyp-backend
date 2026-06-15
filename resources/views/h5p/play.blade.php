<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>H5P Content</title>
    @foreach ($coreStyles as $style)
        <link rel="stylesheet" href="{{ $style }}">
    @endforeach
    <style>
        html, body { margin: 0; padding: 0; background: #fff; }
        .h5p-content { border: 0 !important; }
    </style>
</head>
<body>
    <div class="h5p-content" data-content-id="{{ $contentId }}"></div>

    <script>window.H5PIntegration = @json($integration);</script>
    @foreach ($coreScripts as $script)
        <script src="{{ $script }}"></script>
    @endforeach

    <script>
    (function () {
        var RESULTS_URL = @json($resultsUrl);
        var sent = false;

        function report(score, maxScore, finished) {
            fetch(RESULTS_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ score: score, max_score: maxScore, finished: !!finished }),
                keepalive: true
            }).catch(function () {});
        }

        function attach() {
            if (! window.H5P || ! H5P.externalDispatcher) { return false; }

            H5P.externalDispatcher.on('xAPI', function (event) {
                try {
                    var verb = event.getVerb();
                    if (['completed', 'answered', 'passed', 'failed'].indexOf(verb) === -1) { return; }

                    var statement = event.data && event.data.statement;
                    if (! statement || ! statement.result || ! statement.result.score) { return; }

                    // Only the top-level statement (sub-content has a parent context).
                    var ctx = statement.context && statement.context.contextActivities;
                    if (ctx && ctx.parent && ctx.parent.length) { return; }

                    var raw = statement.result.score.raw;
                    var max = statement.result.score.max;
                    if (raw == null || max == null) { return; }

                    var finished = (verb === 'completed' || verb === 'passed' || verb === 'failed');
                    report(raw, max, finished);
                    sent = true;
                } catch (e) {}
            });
            return true;
        }

        if (! attach()) {
            var tries = 0;
            var t = setInterval(function () {
                if (attach() || ++tries > 50) { clearInterval(t); }
            }, 200);
        }
    })();
    </script>
</body>
</html>
