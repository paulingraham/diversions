<?php #plumbing > error handling: detection, logging, dev display, build markers

/* util--errors.php — PainSci error handling, July 2026. Replaces incs/debugging.php (2010–2026), which was mostly dead machinery around a small working core (its set_error_handler registration had been commented out for years, so PHP-engine errors never actually flowed through it).

WHAT THIS FILE DOES:

1. Registers all three PHP hooks — set_error_handler, set_exception_handler, register_shutdown_function — so every kind of problem is detected: engine errors (warnings, notices, deprecations), uncaught exceptions, and true fatals (via the shutdown check).

2. Owns the writing of error-log lines. The handler returns true (suppressing PHP's own duplicate line) and writes a line in a format byte-compatible with PHP's native prefixes ("PHP Warning:  … in FILE on line N"), because three downstream consumers parse those prefixes: buildErrorsReport() in util--build.php, tools/view-log-errors.php, and tools/error-logs-daemon.php. Each line gets a context suffix — " [doc: …]" during builds (from $GLOBALS['_buildCurrentDoc'], set by buildTrackDoc()) or " [url: …]" for web requests — which finally attributes non-fatal errors inside eval()'d content to the source document instead of "eval()'d code".

3. Collects errors during dev page views and renders a floating error panel at shutdown: a fixed badge with per-severity counts, expandable per-error reports, and backtrace tables with x-bbedit:// edit links. Dev only, HTML responses only, never checkout endpoints. Styles live in css-errors.css (same dir).

4. Echoes the legacy HTML-comment error markers in build contexts, at error time, so they land inside the per-document output buffers that the build scripts scan and abort on. The marker format is a three-consumer contract — make-ps-site.php (preg for "!!! ERROR !!! (.+?)-->"), PubSys.php (same, requires the space before -->), and check-ps-output.sh (ack for "error #") — do not change it without changing all three.

5. Provides error(), the legacy app-level reporting function, as a thin shim over the same core, preserving its "sloppy args" signature for the ~54 existing callers. New code should still call error() for now; a cleaner API (psFail/psWarn + exceptions) is planned (Phase 3 of the July 2026 error-handling plan).

CONTEXTS: behaviour branches on a three-way context — see psErrContext(). 'dev' collects and displays; 'build' logs and marks; 'live' only logs (the error-logs-daemon is the sole production notification channel — email/Pushover on log changes, every 10 min). The default when no MODE constants exist is 'build', which is correct for the family blogs (writerly/ephemeral/diversions): this file is on the shared-code list in make-ps-blog.php and must run standalone, without the PainSci environment — no dependencies outside this file except function_exists-guarded courtesy calls.

Canonical documentation of WHERE ERRORS GO is in env-bootstrap.php, above its ini_set block. This file changes none of that plumbing; it adds detection, context, and display on top. */



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
	psErrLog('PHP Fatal error:  ', $msg . "\nStack trace:\n" . $e->getTraceAsString(), $e->getFile(), $e->getLine());

	$GLOBALS['_psErrCounts']['failure']++;
	$context = psErrContext();

	if ($context === 'build') {
		$doc = $GLOBALS['_buildCurrentDoc'] ?? '';
		echo "<h2 class='warning' style='color:red'>☠️ BUILD FATAL (uncaught " . get_class($e) . ")" . ($doc ? " while processing: " . htmlentities($doc) : "") . "</h2><p>" . htmlentities($e->getMessage()) . " ({$e->getFile()}:{$e->getLine()})</p>";
	}

	psCollect('exception', 'failure', $msg, $e->getFile(), $e->getLine(), $e->getTrace(), true);
	if ($context === 'dev') psRenderErrorPanel(); // render now; shutdown will still run but the rendered flag prevents a double panel (in build context, shutdown does the rendering, after the FATAL banner above)

	if ($context === 'live' && !headers_sent()) http_response_code(500);
}


/** returns @void: shutdown hook. Supplements true fatals (which PHP already logged natively) with a context line, and renders the dev error panel if anything was collected and not yet rendered.  */
function psShutdown() {
	$e = error_get_last();
	if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
		/* in builds, buildErrorsMark()'s own shutdown reporter already names the doc and echoes a FATAL line — don't duplicate (Phase 2 consolidates these) */
		if (psErrContext() !== 'build') error_log('FATAL CONTEXT:' . (psErrLogSuffix() ?: ' (no url/doc context)'));
		psCollect('php', 'failure', $e['message'], $e['file'], $e['line']);
	}

	// flood-guard summaries: one line per diagnostic that exceeded psErrLog()'s 3-per-request cap
	foreach ($GLOBALS['_psErrLogTally'] ?? [] as $line_str => $n)
		if ($n > 3) error_log('PainSci flood guard: suppressed ' . ($n - 3) . ' repeats of: ' . $line_str);

	psRenderErrorPanel();
}


/** returns @void: the shared reporting core used by the PHP handler and the error() shim — logs, counts, collects for the dev panel, and echoes the build marker for app-level errors.  */
function psReport($origin, $severity, $msg, $file, $line, $logPrefix) {
	$GLOBALS['_psErrCounts'][$severity]++;
	$n = array_sum($GLOBALS['_psErrCounts']);

	psErrLog($logPrefix, $msg, $file, $line);

	$context = psErrContext();

	/* Markers: app-level errors only. Engine warnings/notices/deprecations are common enough during builds that marking them would abort every build; they're tallied by buildErrorsReport()'s log delta instead. But every error() call is a deliberate app-level signal (bad pubdate, missing audio file…), rare and worth stopping a build for — this is the "no more silent build errors" behaviour change: previously error() during a site build was invisible (the old marker echo was gated on $GLOBALS['ps'], i.e. PubSys only). */
	if ($context === 'build' && $origin === 'app') psErrMarker($origin, $severity, $n, $msg);

	if ($context !== 'live') {
		$bt = debug_backtrace(0, 25);
		while (!empty($bt) && ($bt[0]['file'] ?? '') === __FILE__) array_shift($bt); // drop this file's own frames (psReport, the handler, error()) so the trace starts at the call site
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
function psErrLog($prefix, $msg, $file, $line) {
	if (defined('_ROOT') && strpos($file, _ROOT) === 0) $file = substr($file, strlen(_ROOT));
	$line_str = "{$prefix}{$msg} in {$file} on line {$line}";
	$n = $GLOBALS['_psErrLogTally'][$line_str] = ($GLOBALS['_psErrLogTally'][$line_str] ?? 0) + 1;
	if ($n > 3) return; // suppressed; psShutdown logs the final tally
	error_log($line_str . psErrLogSuffix());
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


/** returns @void: legacy app-level error reporting — a shim over psReport() preserving the 2010 "sloppy args" signature for ~54 callers, e.g. error('msg'), error('warning---msg'), error('msg---notice'). $php_err_data is dead legacy (its feeder, phpErrs, was never registered) and is accepted and ignored. The legacy 'email' token is also accepted and ignored: per-request error email is retired; the error-logs-daemon is the production notification channel.  */
function error($user_input = false, $php_err_data = false) {
	$severity = 'failure';
	$msg = '';

	if (function_exists('parseSloppyData')) $items = parseSloppyData($user_input);
	else $items = (is_string($user_input) && $user_input !== '') ? [$user_input] : false; // util--core.php not loaded (shouldn't happen — it's on both load paths — but degrade gracefully)

	if (is_array($items)) {
		foreach ($items as $item) {
			if ($item === 'warning' || $item === 'notice') $severity = $item;
			elseif ($item === 'email') continue;
			else $msg = trim($msg . ' ' . $item); // anything that isn't a keyword is message text; multiple segments concatenate
		}
	}
	if ($msg === '') $msg = 'error() called without a message';

	// attribute to the call site, not this file
	$bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
	$file = $bt[0]['file'] ?? '(unknown file)';
	$line = $bt[0]['line'] ?? 0;

	// legacy special case: errors during checkout also land in the order-details log (guarded: ecom--core.php isn't loaded in all contexts)
	global $_doc;
	if (($_doc ?? '') === 'checkout.php' && function_exists('logOrderDetails')) logOrderDetails("⚠ ERROR! $msg");

	psReport('app', $severity, $msg, $file, $line, "PainSci $severity: ");
}


/** returns @void: initializes error handling — full error reporting, the three hooks, and the collection state. Called at include time, below.  */
function errorsInit() {
	error_reporting(E_ALL);
	$GLOBALS['_psErrors'] = [];
	$GLOBALS['_psErrCounts'] = ['failure' => 0, 'warning' => 0, 'notice' => 0];
	$GLOBALS['_psErrLogTally'] = []; // per-unique-line log counts, for psErrLog()'s flood guard
	set_error_handler('psErrorHandler');
	set_exception_handler('psExceptionHandler');
	register_shutdown_function('psShutdown');
}

errorsInit();
