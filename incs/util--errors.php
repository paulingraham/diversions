<?php #plumbing > error handling: detection, logging, dev display, build markers

/* util--errors.php — PainSci error handling, July 2026. Replaces incs/debugging.php (2010–2026), which was mostly dead machinery around a small working core (its set_error_handler registration had been commented out for years, so PHP-engine errors never actually flowed through it).

WHAT THIS FILE DOES:

1. Registers all three PHP hooks — set_error_handler, set_exception_handler, register_shutdown_function — so every kind of problem is detected: engine errors (warnings, notices, deprecations), uncaught exceptions, and true fatals (via the shutdown check).

2. Owns the writing of error-log lines. The handler returns true (suppressing PHP's own duplicate line) and writes a line in a format byte-compatible with PHP's native prefixes ("PHP Warning:  … in FILE on line N"), because three downstream consumers parse those prefixes: buildErrorsReport() in util--build.php, tools/view-log-errors.php, and tools/error-logs-daemon.php. Each line gets a context suffix — " [doc: …]" during builds (from $GLOBALS['_buildCurrentDoc'], set by buildTrackDoc()) or " [url: …]" for web requests — which finally attributes non-fatal errors inside eval()'d content to the source document instead of "eval()'d code".

3. Collects errors during dev page views and renders a floating error panel at shutdown: a fixed badge with per-severity counts, expandable per-error reports, and backtrace tables with x-bbedit:// edit links. Dev only, HTML responses only, never checkout endpoints. Styles live in css-errors.css (same dir).

4. Echoes the legacy HTML-comment error markers in build contexts, at error time, so they land inside the per-document output buffers that the build scripts scan and abort on. The marker format is a three-consumer contract — make-ps-site.php (preg for "!!! ERROR !!! (.+?)-->"), PubSys.php (same, requires the space before -->), and check-ps-output.sh (ack for "error #") — do not change it without changing all three.

5. Provides the psErr* family — the app-level error-reporting API (see its doc block below): psErrFail/psErrWarn/psErrNotice report and continue; psErrThrow throws a PsError. This replaced the legacy error() function entirely in Phase 3 (July 2026).

CONTEXTS: behaviour branches on a three-way context — see psErrContext(). 'dev' collects and displays; 'build' logs and marks; 'live' only logs (the error-logs-daemon is the sole production notification channel — two-tier since July 2026: page-worthy lines email+Pushover immediately, minor lines roll into a daily digest; see the daemon column in the PROD matrix below). The default when no MODE constants exist is 'build', which is correct for the family blogs (writerly/ephemeral/diversions): this file is on the shared-code list in make-ps-blog.php and must run standalone, without the PainSci environment — no dependencies outside this file except function_exists-guarded courtesy calls.

Canonical documentation of WHERE ERRORS GO is in env-bootstrap.php, above its ini_set block. This file changes none of that plumbing; it adds detection, context, and display on top.

THE BEHAVIOUR MATRIX — what every kind of error does, by severity, origin, and context (July 2026). Each row reads: LOG → BUILD → PANEL → RENDER, i.e. is it logged; does it abort builds; does it appear in the floating error panel; does it abort the render. "Panel" gates apply on top of everything below: the panel never renders on live, on CLI, on checkout endpoints, or into non-HTML responses. A nicely formatted HTML rendering of this same matrix is in tools/error-behaviour-matrix.php (displayed by test-error.php and view-log-errors.php) — KEEP THE TWO IN SYNC when policy changes.

DEV (page views and builds — builds only happen dev-side):

	engine notice/deprecation → logged [1] → no build abort → panel → render continues
	
	engine warning → logged [1] → no build abort → panel → render continues
	
	engine E_USER_ERROR (unused here) → logged [1] → no build abort [2] → panel → render continues
	
	psErrNotice → logged [1] → no build abort → panel → render continues
	
	psErrWarn → logged [1] → no build abort → panel → render continues
	
	psErrFail → logged [1] → ABORTS BUILDS (marker) → panel → render continues
	
	uncaught Throwable (incl psErrThrow) → logged with trace → ABORTS BUILDS → panel → KILLS THE RENDER
	
	true fatal (parse error, OOM…) → logged [3] → ABORTS BUILDS → panel [4] → KILLS THE RENDER

PROD (dynamic renders; builds and panels don't happen here — the log and its daemon are everything). Rows here read LOG → DAEMON → RENDER; the daemon column is the two-tier notification policy of tools/error-logs-daemon.php (July 2026): "pages" = email + Pushover at the next 10-min cron tick, "digest" = rolled into a daily minor-activity email, no Pushover:

	engine notice/deprecation → logged [1] → daemon: digest → render continues

	engine warning → logged [1] → daemon: PAGES → render continues

	psErrNotice → UNLOGGED [5] → daemon: (nothing to see) → render continues

	psErrWarn → logged [1] → daemon: PAGES → render continues

	psErrFail → logged [1] → daemon: PAGES → render continues

	uncaught Throwable (incl psErrThrow) → logged with trace → daemon: PAGES → KILLS THE RENDER, HTTP 500 (if headers unsent)

	true fatal (parse error, OOM…) → logged [3] → daemon: PAGES → KILLS THE RENDER

	[1] subject to the flood guard: max 3 identical lines per request, remainder tallied in one line at shutdown
	[2] build markers are origin-gated as well as severity-gated: only psErrFail aborts builds; an engine-origin "failure" is tallied in the end-of-build delta like other engine noise
	[3] logged natively by PHP (absolute path, no context suffix) plus our shutdown supplement (FATAL CONTEXT on pages, BUILD FATAL banner in builds)
	[4] collected via error_get_last() at shutdown: message/file/line only — the stack died with the script, so no backtrace
	[5] app-level notices are dev/build-only by policy: production never hears them at all, which is what makes the notes-to-self pattern safe in dynamically-rendered content (see psReport)

	And one row that appears in neither table: a CAUGHT PsError does nothing anywhere — no log, no panel, no count. The verdict belongs to the catcher (see psErrThrow's docs). */



/** returns @string: the error-handling context, 'dev'|'build'|'live', memoized on first call. Defaults to 'build' (log + markers, no display) when no MODE constants exist — true for the family blogs and any other standalone use.  */
function psErrContext() {
	static $context = null;
	if ($context !== null) return $context;
	if (defined('MODE_BUILD') && MODE_BUILD) return $context = 'build'; // checked first: MODE_BUILD flips MODE_DEV off anyway, but belt and braces
	if (defined('MODE_DEV') && MODE_DEV) return $context = 'dev';
	if (defined('MODE_LIVE') && MODE_LIVE) return $context = 'live';
	return $context = 'build';
}


/** returns @array: [nativeLogPrefix, severity] for a PHP error number, e.g. [E_WARNING] → ['PHP Warning', 'warning']. Severity is the PainSci 3-class scheme: failure|warning|notice.  */
function psErrClassify($errno) {
	switch ($errno) {
		case E_WARNING: case E_USER_WARNING: case E_CORE_WARNING: case E_COMPILE_WARNING:
			return ['PHP Warning', 'warning'];
		case E_NOTICE: case E_USER_NOTICE:
			return ['PHP Notice', 'notice'];
		case E_DEPRECATED: case E_USER_DEPRECATED:
			return ['PHP Deprecated', 'notice'];
		case E_USER_ERROR: case E_RECOVERABLE_ERROR:
			/* effectively unused in this codebase (and trigger_error(E_USER_ERROR) is deprecated in PHP 8.4); classified as failure but execution continues, unlike native handling — acceptable for a case that never occurs */
			return ['PHP Fatal error', 'failure'];
	}
	return ['PHP Unknown error (' . $errno . ')', 'warning']; // future-proofing; E_ERROR/E_PARSE etc never reach a custom handler
}


/** returns @bool: the custom PHP error handler. Logs one native-format line (with context suffix) per error and routes it through psReport(). Returns true = handled, so PHP does not write its own duplicate line.  */
function psErrorHandler($errno, $msg, $file, $line) {
	if (!(error_reporting() & $errno)) return false; // respect @-suppression — bitmask check, not === 0, because PHP 8 keeps fatal bits in the mask inside @

	/* reentrancy guard: if reporting an error itself errors, fall back to a bare log line rather than recursing (the old debugging.php error() could recurse into itself on its own failure paths) */
	static $inHandler = false;
	if ($inHandler) { error_log("PainSci handler reentry: $msg in $file on line $line"); return true; }
	$inHandler = true;

	[$prefix, $severity] = psErrClassify($errno);
	psReport('php', $severity, $msg, $file, $line, $prefix . ':  '); // two spaces after the colon = PHP's native log format, load-bearing for the log parsers

	$inHandler = false;
	return true;
}


/** returns @void: the uncaught-exception handler. Logs a native-format fatal line (with stack trace and context suffix); renders the dev panel immediately (the request is dying); in build context echoes a visible FATAL line so the build scripts and make-all.command's failure scan see it (the exception handler pre-empts the E_ERROR that buildErrorsMark's shutdown reporter watches for).  */
function psExceptionHandler($e) {
	$msg = 'Uncaught ' . get_class($e) . ': ' . $e->getMessage();

	/* Attribution remap for psErrThrow: an exception's getFile/getLine point at where the OBJECT WAS CONSTRUCTED, so a PsError born inside psErrThrow blames this file instead of the call site. When (and only when) the construction site is this file, the first trace frame is the psErrThrow call — the location the report should name. A PsError thrown directly (throw new PsError) already carries the right location and is left alone. */
	$file = $e->getFile(); $line = $e->getLine();
	if ($e instanceof PsError && $file === __FILE__ && ($t = $e->getTrace()) && isset($t[0]['file'])) { $file = $t[0]['file']; $line = $t[0]['line'] ?? 0; }

	psErrLog('PHP Fatal error:  ', $msg, $file, $line, "\nStack trace:\n" . $e->getTraceAsString());

	$GLOBALS['_psErrCounts']['failure']++;
	$context = psErrContext();

	if ($context === 'build') {
		$doc = $GLOBALS['_buildCurrentDoc'] ?? '';
		echo "<h2 class='warning' style='color:red'>☠️ BUILD FATAL (uncaught " . get_class($e) . ")" . ($doc ? " while processing: " . htmlentities($doc) : "") . "</h2><p>" . htmlentities($e->getMessage()) . " ({$file}:{$line})</p>"; // $file/$line, not the exception's own — the psErrThrow attribution remap above applies here too
	}

	psCollect('exception', 'failure', $msg, $file, $line, $e->getTrace(), true);
	if ($context === 'dev') psRenderErrorPanel(); // render now; shutdown will still run but the rendered flag prevents a double panel (in build context, shutdown does the rendering, after the FATAL banner above)

	if ($context === 'live' && !headers_sent()) http_response_code(500);
}


/** returns @void: shutdown hook. Supplements true fatals (which PHP already logged natively) with a context line — and in build context, echoes the BUILD FATAL banner naming the tracked document (consolidated here from buildErrorsMark()'s former anonymous shutdown reporter, July 2026). Also renders the dev error panel if anything was collected and not yet rendered.  */
function psShutdown() {
	$e = error_get_last();
	if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
		if (psErrContext() === 'build') {
			$doc = $GLOBALS['_buildCurrentDoc'] ?? '' ?: '(no document being tracked)';
			$msg = "BUILD FATAL while processing: {$doc}";
			error_log($msg); // enrich the log: the fatal's own native line typically blames only "eval()'d code", with no doc and no suffix (native lines don't get psErrLogSuffix)
			echo "<h2 class='warning' style='color:red'>☠️ {$msg}</h2><p>" . htmlentities($e['message']) . " ({$e['file']}:{$e['line']})</p>"; // 'BUILD FATAL' trips make-all.command's failure-marker scan, aborting the build chain
		} else {
			error_log('FATAL CONTEXT:' . (psErrLogSuffix() ?: ' (no url/doc context)'));
		}
		psCollect('php', 'failure', $e['message'], $e['file'], $e['line']);
	}

	// flood-guard summaries: one line per diagnostic that exceeded psErrLog()'s 3-per-request cap
	foreach ($GLOBALS['_psErrLogTally'] ?? [] as $line_str => $n)
		if ($n > 3) error_log('PainSci flood guard: suppressed ' . ($n - 3) . ' repeats of: ' . $line_str);

	psRenderErrorPanel();
}


/** returns @void: the shared reporting core used by the PHP handler and the psErr* family — logs, counts, collects for the dev panel, and echoes the build marker for app-level errors.  */
function psReport($origin, $severity, $msg, $file, $line, $logPrefix) {
	$context = psErrContext();

	/* App-level notices are dev/build-only by policy (Paul's, July 2026): a psErrNotice is an author's note-to-self — "advertise this to me while I'm working" — and production is not where Paul works, so on live it does nothing at all, not even a log line. This is what makes the notes-to-self pattern safe in dynamically-rendered content (member books, bibliography.php), where a logged notice would otherwise churn the log — and the error-daemon's notifications — on every page view. The origin check matters: ENGINE notices/deprecations (origin 'php') also classify as severity 'notice' and must keep logging on prod, because prod deprecation lines are how PHP-upgrade readiness gets spotted. The contract in one line: if you need to hear about it from production, it's at least a psErrWarn. */
	if ($origin === 'app' && $severity === 'notice' && $context === 'live') return;

	$GLOBALS['_psErrCounts'][$severity]++;
	$n = array_sum($GLOBALS['_psErrCounts']);

	psErrLog($logPrefix, $msg, $file, $line);

	/* Markers: app-level FAILURES only (policy set with Paul, July 2026). Engine diagnostics never mark (they'd abort every build; the log delta tallies them). App-level warnings/notices don't mark either — severity is an honest author declaration now, and "warning" means suboptimal-but-publishable: the build should trust it, finish, and surface the orange in the build-page panel + delta instead of stopping the line. (Two real cases forced this line: the unbalanced-HTML detector in PubSys and sql.php's no-DB-connection warning, both of which must never abort — under an any-severity policy they'd have to mislabel themselves to keep builds alive.) But every psErrFail IS worth stopping a build for — broken content must not ship. If something orange turns out to be publication-blocking, the fix is promotion to psErrFail, not a policy change. */
	if ($context === 'build' && $origin === 'app' && $severity === 'failure') psErrMarker($origin, $severity, $n, $msg);

	if ($context !== 'live') {
		$bt = debug_backtrace(0, 25);
		while (!empty($bt) && ($bt[0]['file'] ?? '') === __FILE__) array_shift($bt); // drop this file's own frames (psReport, the handler, the psErr* wrappers) so the trace starts at the call site
		psCollect($origin, $severity, $msg, $file, $line, $bt, true);
	}
}


/** returns @void: stores one error for the shutdown panel (dev page views and build pages; never live). Also callable directly by code that handles an error itself but still wants it on the panel — e.g. make-ps-site.php's per-page try/catch, which catches render exceptions locally (so they never reach psExceptionHandler) but should still be inspectable with an edit link. External callers omit $alreadyCounted so the severity counter increments here; internal callers (psReport etc) have already counted. Capped at 50 stored errors; counters keep the true totals.  */
function psCollect($origin, $severity, $msg, $file, $line, $bt = [], $alreadyCounted = false) {
	if (!$alreadyCounted) $GLOBALS['_psErrCounts'][$severity]++;
	if (psErrContext() === 'live') return;
	if (count($GLOBALS['_psErrors'] ?? []) >= 50) return;
	$GLOBALS['_psErrors'][] = ['n' => array_sum($GLOBALS['_psErrCounts']), 'origin' => $origin, 'severity' => $severity, 'msg' => $msg, 'file' => $file, 'line' => $line, 'doc' => $GLOBALS['_buildCurrentDoc'] ?? '', 'bt' => $bt];
}


/** returns @void: writes one line to the unified error log (destination set by env-bootstrap.php's ini_set) in native-compatible format plus the [doc:|url:] context suffix. Paths are logged project-relative (the _ROOT prefix stripped) so the same error produces the same line in dev and prod; the native-format contract lives in the prefix and the "in FILE on line N" shape, not the path text. Native-authored lines (true fatals, pre-runtime) still carry absolute paths.

FLOOD GUARD: a diagnostic that fires once per iteration of a big loop (e.g. a null-field deprecation while iterating ~7000 bib records) can write thousands of near-identical lines per request — a real incident, July 2026: make-article-index.php generated 3975 log lines from three deprecations. So each unique line (keyed WITHOUT the context suffix, so per-doc build repeats group) is logged at most 3 times per request; further repeats are counted silently and summarized in one line at shutdown by psShutdown(). Only the log I/O is throttled — panel counters and collection are unaffected, so the badge still shows true totals. Quirk: the shutdown summary line lands after buildErrorsReport() has already read the log, so during builds it shows up in the NEXT build's delta instead — harmless, but don't be confused by it.  */
function psErrLog($prefix, $msg, $file, $line, $trailer = '') {
	if (defined('_ROOT') && strpos($file, _ROOT) === 0) $file = substr($file, strlen(_ROOT));
	$line_str = "{$prefix}{$msg} in {$file} on line {$line}";
	$n = $GLOBALS['_psErrLogTally'][$line_str] = ($GLOBALS['_psErrLogTally'][$line_str] ?? 0) + 1;
	if ($n > 3) return; // suppressed; psShutdown logs the final tally
	error_log($line_str . psErrLogSuffix() . $trailer); /* $trailer carries multi-line extras (stack traces) AFTER the location and context suffix, so the first physical line is complete and parseable on its own — view-log-errors.php reads type, message, location, and [url|doc:] all from line one, and the trace lines below are recognizably continuation (no [date] prefix) */
}


/** returns @string: the context suffix for log lines — ' [doc: …]' during builds (the source document being rendered, via buildTrackDoc()), else ' [url: …]' for web requests, else ''.  */
function psErrLogSuffix() {
	if (!empty($GLOBALS['_buildCurrentDoc'])) return ' [doc: ' . $GLOBALS['_buildCurrentDoc'] . ']';
	if (!empty($_SERVER['REQUEST_URI'])) return ' [url: ' . $_SERVER['REQUEST_URI'] . ']';
	return '';
}


/** returns @void: echoes the legacy build-context error marker. FORMAT IS A CONTRACT — scanned by make-ps-site.php ("!!! ERROR !!! (.+?)-->"), PubSys.php (same regex, requires the space before -->), and check-ps-output.sh (ack 'error #'). Emitted at error time so it lands inside the per-document ob_start buffer being scanned.  */
function psErrMarker($origin, $severity, $n, $msg) {
	$date = date('D M j');
	$time = date('g:i:sa');
	echo "<!-- !!! ERROR !!! $date $time $origin {$severity} error #$n $msg -->\n";
}


/** returns @void: renders the error panel at shutdown — a fixed badge with per-severity counts and expandable per-error reports with backtrace tables. Renders on dev page views AND at the end of build pages (the build journal is itself a dev-side page; by shutdown all artifact-capturing ob buffers are long closed, so the panel can't leak into rendered output). Gated hard: never on live, never checkout endpoints (webhook.php must return clean responses to Stripe), and only when the response is HTML (protects JSON/ajax endpoints — a gate the old system lacked).  */
function psRenderErrorPanel() {
	if ($GLOBALS['_psErrPanelRendered'] ?? false) return;
	if (PHP_SAPI === 'cli') return; // an HTML panel has no business in a terminal; CLI scripts get the log lines only
	if (psErrContext() === 'live') return;
	if (empty($GLOBALS['_psErrors'])) return;

	global $_doc, $_dir;
	if (($_doc ?? '') === 'webhook.php' || strpos($_dir ?? '', '/incs/checkout') !== false) return;

	foreach (headers_list() as $header) // if a non-HTML Content-Type was sent, this response is data, not a page: stay out of it
		if (stripos($header, 'content-type:') === 0 && stripos($header, 'text/html') === false) return;

	$GLOBALS['_psErrPanelRendered'] = true;

	// styles: inlined from the sibling CSS file (resolved via __DIR__ so the family-blog copies find their own copy); injected here rather than head.php so the panel works on tools pages, partials, and blogs; minifyCSS takes a filename, not content
	$cssFile = __DIR__ . '/css-errors.css';
	$css = function_exists('minifyCSS') ? minifyCSS($cssFile) : (@file_get_contents($cssFile) ?: '');
	echo "\n<style id='ps-err-css'>$css</style>\n";

	$c = $GLOBALS['_psErrCounts'];
	$chips = '';
	foreach (['failure', 'warning', 'notice'] as $sev)
		if ($c[$sev] > 0) $chips .= "<span class='ps-err-chip ps-err-$sev'>{$c[$sev]}</span>";

	echo "<div class='ps-err-badge' onclick='document.getElementById(\"ps-err-reports\").classList.toggle(\"ps-err-open\")' title='errors on this page — click for details'>$chips</div>\n";

	echo "<div id='ps-err-reports'>\n";
	$total = array_sum($c);
	$stored = count($GLOBALS['_psErrors']);
	echo "<p class='ps-err-summary'>$total error" . ($total == 1 ? '' : 's') . " on this page ({$c['failure']} failures, {$c['warning']} warnings, {$c['notice']} notices)" . ($stored < $total ? " — details stored for the first $stored" : '') . "</p>\n";

	foreach ($GLOBALS['_psErrors'] as $i => $err) {
		$msg = htmlentities($err['msg']);
		/* Build-context errors fire inside eval()'d content, so PHP's "file" is a pseudo-path like "make-ps-site.php(350) : eval()'d code" that no editor can open — but buildTrackDoc() recorded the real source document. Substitute it for the edit link. For whole-file evals (site pages) the eval line maps 1:1 onto the source line; for blog posts the content is assembled before eval, so the line is approximate — still better than no link. */
		$doc = $err['doc'] ?? '';
		$isEvalPseudoPath = strpos($err['file'], "eval()'d code") !== false;
		if ($doc && $isEvalPseudoPath && ($docAbs = psDocPath($doc))) {
			$loc = htmlentities($doc) . ':' . $err['line'];
			$editUrl = psEditUrl($docAbs, $err['line']);
		} else {
			$loc = htmlentities($err['file']) . ':' . $err['line'];
			$editUrl = psEditUrl($err['file'], $err['line']);
		}
		$docNote = ($doc && !$isEvalPseudoPath) ? " <span class='ps-err-origin'>while building " . htmlentities($doc) . "</span>" : ''; // a real file (e.g. an incs/ include) erred while a doc was being rendered: name both
		echo "<div class='ps-err-report ps-err-{$err['severity']}'>";
		echo "<p><strong>#{$err['n']}</strong> <span class='ps-err-chip ps-err-{$err['severity']}'>{$err['severity']}</span> <span class='ps-err-origin'>{$err['origin']}</span> — <strong>$msg</strong><br><code>$loc</code> <a class='ps-err-edit' href='$editUrl'>edit</a>$docNote";
		if (!empty($err['bt'])) echo " <span class='ps-err-bt-toggle' onclick='this.closest(\".ps-err-report\").querySelector(\".ps-err-bt\").classList.toggle(\"ps-err-open\")'>backtrace ▾</span>";
		echo "</p>";
		if (!empty($err['bt'])) echo psBacktraceHtml($err['bt']);
		echo "</div>\n";
	}
	echo "</div>\n";

	/* ESC / off-click dismissal, mirroring the house popup-banishing conventions (see keyboard-shortcuts-dev-js.php): same guards — never intercept keys while an INPUT has focus; clicks inside the panel or on the badge don't banish. Deliberately self-contained rather than joining the .pupw class system: the shortcuts plumbing doesn't load on tools pages or the family blogs (where this panel does), and the banisher's inline display:none would fight the panel's class-based toggle. */
	echo "<script>
(function () {
	var panel = document.getElementById('ps-err-reports');
	document.addEventListener('keydown', function (e) {
		if (document.activeElement && document.activeElement.nodeName === 'INPUT') return;
		if (e.key === 'Escape') panel.classList.remove('ps-err-open');
	});
	document.addEventListener('click', function (e) {
		if (e.target.closest('#ps-err-reports') || e.target.closest('.ps-err-badge')) return;
		panel.classList.remove('ps-err-open');
	});
})();
</script>\n";
}


/** returns @string: an HTML table for a backtrace — one row per frame: function, stubbed args, file:line, edit link.  */
function psBacktraceHtml($bt) {
	$rows = '';
	foreach ($bt as $i => $frame) {
		$func = htmlentities(($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '?'));
		$args = '';
		if (!empty($frame['args'])) { // stub args down to type/size hints — enough to orient, never huge
			$stubs = [];
			foreach ($frame['args'] as $arg) $stubs[] = psStubArg($arg);
			$args = htmlentities(implode(', ', $stubs));
		}
		$file = $frame['file'] ?? '';
		$line = $frame['line'] ?? 0;
		$loc = $file ? htmlentities(basename($file)) . ':' . $line : '';
		$edit = $file ? "<a class='ps-err-edit' href='" . psEditUrl($file, $line) . "'>edit</a>" : '';
		$rows .= "<tr><td>$i</td><td>$func()</td><td class='ps-err-args'>$args</td><td>$loc</td><td>$edit</td></tr>\n";
	}
	return "<table class='ps-err-bt'><tr><th></th><th>function</th><th>args</th><th>where called</th><th></th></tr>\n$rows</table>\n";
}


/** returns @string|false: absolute path for a build-tracked doc (as recorded by buildTrackDoc), resolved against the project root and the blog dir (blog docs are tracked relative to /blog); false if it can't be found  */
function psDocPath($doc) {
	if (!defined('_ROOT')) return false;
	foreach ([_ROOT . '/' . $doc, _ROOT . '/blog/' . $doc] as $path)
		if (file_exists($path)) return $path;
	return false;
}


/** returns @string: an x-bbedit:// URL opening a file at a line — the house editor-link convention (see also admin-tools.php, PubSys.php). Path segments are URL-encoded individually because source paths can contain spaces (e.g. blog post filenames).  */
function psEditUrl($file, $line) {
	$path = implode('/', array_map('rawurlencode', explode('/', $file)));
	return "x-bbedit://open?url=file://$path&line=$line";
}


/** returns @string: a compact stub describing one backtrace argument, e.g. "array [3]", "object Customer", "'trunca…[47]'".  */
function psStubArg($arg) {
	if (is_array($arg)) return 'array [' . count($arg) . ']';
	if (is_object($arg)) return 'object ' . get_class($arg);
	if (is_string($arg)) return strlen($arg) > 12 ? "'" . substr($arg, 0, 12) . "…[" . strlen($arg) . "]'" : "'$arg'";
	if (is_bool($arg)) return $arg ? 'true' : 'false';
	if ($arg === null) return 'null';
	return (string) $arg;
}


/* ============================================================================
THE psErr* FAMILY — the app-level error-reporting API (July 2026, error-handling Phase 3), replacing the legacy error() function. Four verbs, one rule: THREE REPORT, ONE THROWS, AND THE ONE THAT THROWS SAYS SO.

	psErrFail($msg)     report a FAILURE (red) and continue — content is broken but execution isn't (missing audio file, unrenderable citation)
	psErrWarn($msg)     report a WARNING (orange) and continue — quality problems, questionable usage
	psErrNotice($msg)   report a NOTICE (blue) and continue — FYI-grade observations and deliberate notes-to-self; DEV/BUILD-ONLY: on live it does nothing at all (not even a log line), so it's safe in dynamically-rendered content. If you need to hear about it from production, use psErrWarn.
	psErrThrow($msg)    throw a PsError — for genuinely abortive conditions; catch it at a boundary or let psExceptionHandler() present it (log + panel + 500 on live)

All three reporters share psAppReport(): call-site attribution, the checkout order-details mirror, and psReport() with 'app' origin. Build-abort policy: only psErrFail emits the build marker, so only FAILURES abort builds (naming the document); warnings and notices flow to the log, the end-of-build delta, and the build-page panel — visible but not blocking. Severity is display-and-gate taxonomy, not control flow; the reporters never alter execution. psErrThrow deliberately does NOT pre-report — presentation belongs to whoever catches it, or to the exception handler if nobody does.

Historical note: the naming is Paul's, chosen so autocomplete surfaces the whole family from 'psErr', and so throwing is visible in the name at every call site — the legacy error() defaulted everything to failure severity and could never alter control flow, two dishonesties this API retires. */


/* ============================================================================
THE THREE SYSTEMS — ERROR REPORTS vs TELEMETRY vs NOTIFICATIONS (policy settled July 18 2026, psErr* adoption pass)

The psErr* family is one of THREE deliberately separate systems, and choosing the right system matters more than choosing a severity:

1. PROBLEM REPORTS — the psErr* family (this file). For DEFECTS only: something wrong with the code or the content. Audience: the author. The severity ladder says how bad the problem is; context decides presentation (panel, build marker, log line).

2. EVENT RECORDS (telemetry) — quiet purpose-specific log files, never the error log. For events that are NOT defects: visitor behaviour (bots and curious customers probing CIDs and order numbers), procedural narratives (orders, emails sent). Audience: the operator, later — investigating a complaint, or scanning for aggregate patterns. Tools: logOrderDetails() (ecom--core.php) appends to /logs/order-details.txt, the per-customer ecommerce/login narrative — prefer it when the event belongs in a customer's story; logToFile('whatever-log.txt', $msg) (util--core.php) appends timestamped lines to any file in /logs/ — the generic channel-maker (e.g. email-log.txt). Append psErrLogSuffix() to the message manually when the [url:|doc:] attribution is worth having.

3. NOTIFICATIONS — deliberate interruptions, used sparingly: myReport($msg) (email--functions.php) emails Paul a note-to-self; notifyMe($msg, $appToken) (util--core.php) sends a Pushover push. Most notification traffic should come from the AUTOMATIC layer instead: tools/error-logs-daemon.php (prod cron, 10 min) watches the error logs and decides what is worth an interruption — fatal/failure/warning/unrecognized lines page immediately, deprecations and notices roll into a daily digest email. "Too minor to page about, too real to ignore" is therefore a DAEMON property, not a severity — there is deliberately no psErrTrivial, and the paging threshold lives in the notification layer where it can change without touching any call site.

The sorting rules in practice:

- Something is wrong with my code or content → psErr*, severity by how bad it is. If you need to hear about it from production, it's at least psErrWarn (notices are unlogged on live). The bar for the error log is CONSEQUENCE-BEARING OR ACTIONABLE, because the daemon makes that log a paging channel.

- Nothing is wrong, but the event deserves a record → telemetry: logOrderDetails() if it's part of a customer's story, a logToFile() channel otherwise. NOT a psErr* at any severity, no matter how minor.

- Paul must be interrupted directly, even though nothing landed in the error log → myReport()/notifyMe(), rare and deliberate (see checkoutError() in ecom--core.php for the canonical log+email combo).

WHY psErrNotice STAYS A NO-OP ON LIVE (the question that settled this policy): the ladder's audience is the author, and notice is its "only matters while I'm working" rung — production is the one context where that audience is guaranteed absent. Logging live notices anywhere would also let authoring FYIs on dynamically-rendered pages (member books, bibliography.php) churn a file by the thousands, and would end the fearless-sprinkling property: today a note-to-self can be dropped into any content with zero thought about production consequences. Telemetry superficially resembles "notices in production" (quiet, informational), but differs in audience and purpose — which is why it lives in system 2, and why logToFile/myReport/notifyMe keep their plain descriptive names rather than being dressed up as psErr-anything.

Precedents from the adoption pass (July 2026): account-login.php, fetch-code.php, and checkout/lookup.php — visitor telemetry demoted from psErr* to logOrderDetails(), each with a commented-out myReport() beside it as a resume-the-emails escape hatch; ecom--subscriptions.php — customer-degrading API failures stay psErrWarn, while failures the stale-cache fallback absorbs are deliberately not recorded at all: not every non-defect event deserves a record (a telemetry log of them was tried July–Sept 2026 and removed; see the cache docblock there). */


class PsError extends RuntimeException {}


/** returns @void: shared core for the psErr* reporting family — attributes the report to the nearest call site outside this file, mirrors checkout errors into the order-details log, and routes into psReport() with app origin (log line, panel, counters, build marker).  */
function psAppReport($severity, $msg) {
	$file = '(unknown file)'; $line = 0;
	foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5) as $frame) {
		if (($frame['file'] ?? '') !== __FILE__) { $file = $frame['file'] ?? '(unknown file)'; $line = $frame['line'] ?? 0; break; }
	}

	// legacy special case: errors during checkout also land in the order-details log (guarded: ecom--core.php isn't loaded in all contexts)
	global $_doc;
	if (($_doc ?? '') === 'checkout.php' && function_exists('logOrderDetails')) logOrderDetails("⚠ ERROR! $msg");

	psReport('app', $severity, $msg, $file, $line, "PainSci $severity: ");
}


/** returns @void: reports a failure-severity (red) problem and continues — see the psErr* family doc above  */
function psErrFail($msg) { psAppReport('failure', $msg); }


/** returns @void: reports a warning-severity (orange) problem and continues — see the psErr* family doc above  */
function psErrWarn($msg) { psAppReport('warning', $msg); }


/** returns @void: reports a notice-severity (blue) observation and continues — see the psErr* family doc above  */
function psErrNotice($msg) { psAppReport('notice', $msg); }


/** returns @never: throws a PsError — the only psErr* verb that alters control flow, and it says so in its name  */
function psErrThrow($msg) { throw new PsError($msg); }


/* The legacy error() function is GONE (deleted at the end of Phase 3, July 2026, after all ~40 call sites migrated to the psErr* family above). It dated to ~2010 and had two structural dishonesties: everything defaulted to failure severity unless a "sloppy args" token said otherwise, and it could never alter control flow. Its severity tokens rode inside the message string via parseSloppyData(), which meant a message that legitimately contained '---' would be silently split into phantom arguments — a latent bug the psErr* family retires along with the function. */


/* PS_TIMEZONE — the canonical timezone for ALL PainSci log timestamps (and everything else date-related). Log times should match Paul's wall clock. 'America/Los_Angeles' since 2026-09-23; it was 'America/Los_Angeles' (the IANA backward-compatibility link, chosen because PHP stamps every file-log line with the zone NAME and the short form was tidier) until Ubuntu 24.04 turned out to ship legacy links only in the optional tzdata-legacy package: on the new host date_default_timezone_set() rejected it with a notice and PHP silently ran in UTC. This file is the constant's home because it is the one file every PainSci context loads — env chain, family-blog loader, and standalone/CLI use. Contexts that run BEFORE or WITHOUT this file must hardcode the same value and are tagged for discovery: grep '#timezone' finds every site (env-bootstrap.php, checkout/session.php, bin/build-srcs-sqlite.php). Before July 2026, standalone contexts (CLI scripts, family-blog builds) inherited php.ini's date.timezone = UTC, producing mixed-timezone logs. #timezone */
if (!defined('PS_TIMEZONE')) define('PS_TIMEZONE', 'America/Los_Angeles');


/** returns @void: initializes error handling — full error reporting, the three hooks, the canonical timezone, and the collection state. Called at include time, below.  */
function errorsInit() {
	error_reporting(E_ALL);
	date_default_timezone_set(PS_TIMEZONE); // #timezone — idempotent re-set for the main path (env-bootstrap already did it), the FIX for blog/CLI contexts that never set one and stamped logs in UTC
	$GLOBALS['_psErrors'] = [];
	$GLOBALS['_psErrCounts'] = ['failure' => 0, 'warning' => 0, 'notice' => 0];
	$GLOBALS['_psErrLogTally'] = []; // per-unique-line log counts, for psErrLog()'s flood guard
	set_error_handler('psErrorHandler');
	set_exception_handler('psExceptionHandler');
	register_shutdown_function('psShutdown');
}

errorsInit();
