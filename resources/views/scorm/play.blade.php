<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        html, body { margin: 0; height: 100%; background: #0f172a; font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; }
        #scorm-frame { width: 100%; height: 100%; border: 0; display: block; background: #fff; }
        #scorm-status {
            position: fixed; bottom: 12px; right: 12px; z-index: 10;
            font-size: 12px; color: #e2e8f0; background: rgba(15,23,42,.85);
            padding: 6px 12px; border-radius: 8px; pointer-events: none;
            transition: opacity .4s; opacity: 0;
        }
    </style>
</head>
<body>
    <iframe id="scorm-frame" src="{{ $scoUrl }}" allowfullscreen></iframe>
    <div id="scorm-status"></div>

    <script>
    (function () {
        var TRACK_URL = @json($trackUrl);
        var VERSION   = @json($version); // "1.2" or "2004"

        var statusEl = document.getElementById('scorm-status');
        function flash(msg) {
            statusEl.textContent = msg;
            statusEl.style.opacity = '1';
            clearTimeout(flash._t);
            flash._t = setTimeout(function () { statusEl.style.opacity = '0'; }, 1500);
        }

        // Local CMI store with sensible defaults so SCOs can read before writing.
        var cmi = {
            'cmi.core.lesson_status': 'not attempted',
            'cmi.core.lesson_location': '',
            'cmi.core.entry': 'ab-initio',
            'cmi.core.score.raw': '',
            'cmi.core.score.max': '100',
            'cmi.core.score.min': '0',
            'cmi.core.session_time': '00:00:00',
            'cmi.suspend_data': '',
            'cmi.completion_status': 'unknown',
            'cmi.success_status': 'unknown',
            'cmi.score.raw': '',
            'cmi.score.max': '100',
            'cmi.score.min': '0',
            'cmi.score.scaled': '',
            'cmi.session_time': 'PT0H0M0S',
            'cmi.exit': '',
            'cmi.location': '',
            'cmi.mode': 'normal',
            'cmi.credit': 'credit',
            'cmi.entry': 'ab-initio'
        };

        var lastError = '0';

        // Persist an element to the backend (fire-and-forget, keepalive on unload).
        function persist(element, value) {
            try {
                fetch(TRACK_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ element: element, value: String(value == null ? '' : value) }),
                    keepalive: true
                }).catch(function () {});
            } catch (e) {}
        }

        // Elements worth persisting (status, score, time, resume data).
        var TRACKED = {
            'cmi.core.lesson_status': 1, 'cmi.core.score.raw': 1, 'cmi.core.score.max': 1,
            'cmi.core.score.min': 1, 'cmi.core.session_time': 1, 'cmi.core.lesson_location': 1,
            'cmi.suspend_data': 1, 'cmi.completion_status': 1, 'cmi.success_status': 1,
            'cmi.score.raw': 1, 'cmi.score.max': 1, 'cmi.score.min': 1, 'cmi.score.scaled': 1,
            'cmi.session_time': 1, 'cmi.location': 1, 'cmi.exit': 1
        };

        function setValue(element, value) {
            cmi[element] = String(value);
            lastError = '0';
            if (TRACKED[element]) {
                persist(element, value);
                if (element === 'cmi.core.lesson_status' || element === 'cmi.completion_status'
                    || element === 'cmi.success_status') {
                    flash('Progress saved: ' + value);
                }
            }
            return 'true';
        }

        function getValue(element) {
            lastError = '0';
            return cmi[element] != null ? cmi[element] : '';
        }

        function commit() {
            // Flush key terminal values to be safe.
            ['cmi.core.lesson_status', 'cmi.completion_status', 'cmi.success_status',
             'cmi.core.score.raw', 'cmi.score.raw', 'cmi.core.session_time', 'cmi.session_time',
             'cmi.suspend_data'].forEach(function (el) {
                if (cmi[el] !== undefined && cmi[el] !== '') persist(el, cmi[el]);
            });
            flash('Saved');
            return 'true';
        }

        // ── SCORM 1.2 API ───────────────────────────────────────────────
        window.API = {
            LMSInitialize: function () { lastError = '0'; return 'true'; },
            LMSFinish: function () { commit(); return 'true'; },
            LMSGetValue: function (el) { return getValue(el); },
            LMSSetValue: function (el, val) { return setValue(el, val); },
            LMSCommit: function () { return commit(); },
            LMSGetLastError: function () { return lastError; },
            LMSGetErrorString: function () { return lastError === '0' ? 'No error' : 'Error'; },
            LMSGetDiagnostic: function () { return ''; }
        };

        // ── SCORM 2004 API ──────────────────────────────────────────────
        window.API_1484_11 = {
            Initialize: function () { lastError = '0'; return 'true'; },
            Terminate: function () { commit(); return 'true'; },
            GetValue: function (el) { return getValue(el); },
            SetValue: function (el, val) { return setValue(el, val); },
            Commit: function () { return commit(); },
            GetLastError: function () { return lastError; },
            GetErrorString: function () { return lastError === '0' ? 'No error' : 'Error'; },
            GetDiagnostic: function () { return ''; }
        };

        // Final flush when the learner closes the activity.
        window.addEventListener('pagehide', commit);
        window.addEventListener('beforeunload', commit);
    })();
    </script>
</body>
</html>
