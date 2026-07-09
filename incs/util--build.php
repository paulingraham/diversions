<?php #CMS > #plumbing > utilities for the big build scripts

/* BUILD ERROR REPORTING — three helpers used by the big build scripts (make-ps-site.php, make-ps-blog.php). The builds render nearly every page, so they double as a de facto test suite — but PHP problems land in logs/php-errors.log, invisible in the build journal, and fatals inside eval()'d page renders blame "eval()'d code" without naming the document being rendered. buildErrorsMark() starts tracking at build start; buildTrackDoc() records which document is being processed; a shutdown reporter names that document if a fatal kills the build; buildErrorsReport() journals a deduplicated summary of all error-log entries generated during the build. (June 2026, build-pipeline review item #3.)

This file is also the intended home for future build-related utilities, as part of the slow migration of functions out of the util--core.php grab-bag into subject files. */

/** returns @void: starts build error tracking — records the php-errors.log offset for buildErrorsReport(). (This function formerly also registered a shutdown reporter naming the doc a fatal killed; that job moved to psShutdown() in util--errors.php, July 2026, which sees the same $GLOBALS['_buildCurrentDoc'] and echoes the same BUILD FATAL banner.)  */
function buildErrorsMark() {
	$GLOBALS['_buildErrLog'] = _ROOT . '/logs/php-errors.log';
	$GLOBALS['_buildErrLogOffset'] = (int) @filesize($GLOBALS['_buildErrLog']);
	$GLOBALS['_buildCurrentDoc'] = '';
}

/** returns @void: records which source document the build is currently processing, so fatals can be blamed on it (see buildErrorsMark)  */
function buildTrackDoc($doc) {
	$GLOBALS['_buildCurrentDoc'] = $doc;
}

/** returns @void: journals a deduplicated severity summary of php-errors.log entries generated since buildErrorsMark()  */
function buildErrorsReport() {
	$log = $GLOBALS['_buildErrLog'] ?? null;
	if (!$log or !file_exists($log)) return;
	$offset = $GLOBALS['_buildErrLogOffset'] ?? 0;
	$size = filesize($log);
	if ($size < $offset) $offset = 0; // the log was truncated mid-build; report on all of it
	journal('PHP error-log delta for this build', 1, true);
	if ($size == $offset) {
		journal('no new PHP errors logged during this build 👍', 2, true);
		return;
	}
	// read everything logged since the build started, and group identical messages (a single bad call in a shared include fires once per page, so one real problem can mean hundreds of identical lines)
	$fh = fopen($log, 'r');
	fseek($fh, $offset);
	$tallies = [];
	while (($line = fgets($fh)) !== false) {
		$line = trim($line);
		if ($line === '') continue;
		$msg = preg_replace('/^\[[^\]]*\]\s*/', '', $line); // strip the timestamp prefix so identical messages group
		$tallies[$msg] = ($tallies[$msg] ?? 0) + 1;
	}
	fclose($fh);
	$severities = ['PHP Fatal' => 0, 'PHP Parse' => 0, 'PHP Warning' => 0, 'PHP Deprecated' => 0, 'other' => 0];
	$total = 0;
	foreach ($tallies as $msg => $n) {
		$total += $n;
		$known = false;
		foreach ($severities as $sev => $x) {
			if (strpos($msg, $sev) === 0) {
				$severities[$sev] += $n;
				$known = true;
				break;
			}
		}
		if (!$known) $severities['other'] += $n;
	}
	$breakdown = [];
	foreach ($severities as $sev => $n) if ($n > 0) $breakdown[] = "$n " . str_replace('PHP ', '', $sev);
	journal("<strong>$total new PHP error-log entries</strong> (" . count($tallies) . ' unique): ' . implode(', ', $breakdown), 2, true);
	arsort($tallies);
	foreach ($tallies as $msg => $n)
		journal('×' . $n . ' — ' . htmlentities(mb_strimwidth($msg, 0, 300, '…')), 2, true);
}
