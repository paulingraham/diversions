<?php #CMS > #plumbing > utilities for the big build scripts

/* BUILD LOG AND CHANGES REPORT — the output layer of the big build scripts (make-ps-site.php, make-ps-blog.php, shared with the family blogs via the PubSys sync).

DESIGN (Oct 2026, build-pipeline Phase 1). The build log is PLAIN TEXT. In a browser the build script wraps it in a <pre>; from the CLI (Phase 2) the same text goes to stdout. Nothing in the build emits presentation markup any more — until Oct 2026 the log was rich HTML (numbered journal with per-step progress dots, clickable links, a 1.4MB sortable table of every post) that nobody read: the real audit of a build is the git diff of /stage, and the only things a human or a Claude session wants from the log are (1) that it finished, (2) warnings and aborts, and (3) WHAT CHANGED. The output is designed around those three, in that order of importance, and is deliberately short.

The one piece of intelligence here is the CHANGES REPORT, which gives something git does not: changed files sorted by size of change, with IDENTICAL changes batched. A one-word edit to an inclusion changes 300 pages identically; git shows 300 diffs, this shows one batch with a sample line and a count. The mechanism is a change SIGNATURE: the set of lines removed plus the set of lines added, as content only, ignoring position — array set-difference, not a diff algorithm, which is why it is cheap and why the same inclusion change at line 40 of one page and line 900 of another produces the same signature. Known limitation: batching is by identical OUTPUT change, not identical cause; an inclusion whose changed line embeds per-page content (a title in a meta tag) differs per page and lands under "unique". A greedy superset decomposition (a page carrying the batch change PLUS its own edit) is feasible with the same sets and deferred until the first cut has been judged on real builds. The report is read-only — it can never alter /stage — so a bug in it costs a misleading summary, nothing more.

Plumbing: saveAs() (util--core.php) is the single funnel every build artifact passes through, so it calls buildRecordChange() with the old and new contents; the deletion sites call buildRecordDelete(). Both are inert unless buildMark() ran, so standalone callers of saveAs() pay nothing. Content vs support is a path test: a .php/.html file under the stage dir is a content page; everything else (feeds, sitemaps, tag files, data files) is a support file, listed after.

Also here: buildTrackDoc/buildErrorsReport (June 2026, originally with buildErrorsMark, now folded into buildMark) — the build renders nearly every page, so it doubles as a test suite, but PHP problems land in logs/php-errors.log, invisible in the build log, and fatals inside eval()'d renders blame "eval()'d code" without naming the document. buildTrackDoc() records the document being processed (psShutdown() in util--errors.php names it in the BUILD FATAL banner); buildErrorsReport() prints a deduplicated summary of error-log entries generated during the build.

Contracts with make-all.command's log scans: warnings are lines beginning 'WARNING:' (via journal()); fatal markers are the literal strings 'ABORT!', 'BUILD FATAL', 'PainSci failure', '!!! ERROR !!!'; the changes headline begins 'CHANGED FILES'. This file must stay blog-safe: no PS-environment dependencies beyond _ROOT and the optional STAGE constant. */

/** returns @void: starts the build log plumbing — the php-errors.log offset for buildErrorsReport(), the current-doc tracker, the changes collector, and the ?verbose flag  */
function buildMark() {
	$GLOBALS['_buildErrLog'] = _ROOT . '/logs/php-errors.log';
	$GLOBALS['_buildErrLogOffset'] = (int) @filesize($GLOBALS['_buildErrLog']);
	$GLOBALS['_buildCurrentDoc'] = '';
	$GLOBALS['_buildChanges'] = [];
	$GLOBALS['_buildVerbose'] = strpos($_SERVER['QUERY_STRING'] ?? '', 'verbose') !== false;
}

/** returns @boolean: true when the build was asked for per-file detail (?verbose); journal() then prints its normally-silent messages too  */
function buildVerbose() {
	return !empty($GLOBALS['_buildVerbose']);
}

/** returns @boolean: true when running from the command line rather than a web request (the Phase 2 CLI entry; today always false in practice)  */
function buildIsCli() {
	return PHP_SAPI === 'cli';
}

/** returns @void: prints one line of build log. Web mode escapes it for the <pre>; CLI prints it raw. $html = true means the line already carries markup from buildLink() (the caller escapes nothing else, so keep such lines to paths and fixed strings)  */
function buildOut($line = '', $html = false) {
	if (buildIsCli()) echo ($html ? html_entity_decode(strip_tags($line), ENT_QUOTES) : $line) . "\n";
	else echo ($html ? $line : htmlspecialchars($line, ENT_NOQUOTES)) . "\n";
}

/** returns @string: $text as a link to $href in web mode, or just $text in CLI mode — for use in buildOut(…, true) lines  */
function buildLink($href, $text) {
	if (buildIsCli()) return $text;
	return "<a href='" . htmlspecialchars($href, ENT_QUOTES) . "' target='_blank'>" . htmlspecialchars($text, ENT_NOQUOTES) . "</a>";
}

/** returns @void: starts capturing the step-by-step process log, so that buildFinish() can print the summary BEFORE it; call after the header lines  */
function buildLogStart() {
	/* The log is streamed as the build runs (journal() echoes as it goes), but what matters — counts, warnings, what changed — is only known at the end. Output buffering (PHP's ob_start) collects the log so the page can lead with the summary and relegate the process to the bottom. The cost is that nothing appears in the browser until the build finishes; a build that aborts or dies still shows everything, because PHP flushes open buffers at exit (after the shutdown handlers, so the BUILD FATAL banner and error panel land in order). The CLI entry (Phase 2) may prefer to stream and print the summary last; that's a one-line switch here. */
	ob_start();
	$GLOBALS['_buildLogBuffered'] = true;
}

/** returns @void: ends the build log — prints the summary (the caller's result lines, warnings hoisted out of the log, the changes report, the PHP error-log delta), then the captured process log, then closes the <pre>; the error panel, if any, renders after it at shutdown  */
function buildFinish($resultLines = []) {
	$log = '';
	if (!empty($GLOBALS['_buildLogBuffered'])) {
		$log = (string) ob_get_clean();
		$GLOBALS['_buildLogBuffered'] = false;
	}
	buildOut(); buildOut('RESULTS');
	foreach ($resultLines as $line) buildOut('  ' . $line);
	if (preg_match_all('/^[ \t]*WARNING:.*$/m', $log, $m)) {
		buildOut(); buildOut('WARNINGS (' . count($m[0]) . '):');
		foreach ($m[0] as $w) echo '  ' . trim($w) . "\n"; // copied verbatim from the log, which is already output-ready (escaped in web mode), so not via buildOut(); make-all.command's warning scan dedupes these copies
	}
	buildChangesReport();
	buildErrorsReport();
	buildOut(); buildOut('BUILD LOG (the process, step by step — everything that matters is above)');
	echo $log;
	if (!buildIsCli()) echo "</pre>\n";
}

/** returns @void: records which source document the build is currently processing, so fatals can be blamed on it (see the header)  */
function buildTrackDoc($doc) {
	$GLOBALS['_buildCurrentDoc'] = $doc;
}

/** returns @string: a path relative to the project root, for the changes report  */
function buildRelPath($path) {
	return strpos($path, _ROOT . '/') === 0 ? substr($path, strlen(_ROOT) + 1) : $path;
}

/** returns @boolean: is this output path a content page (a .php/.html file under the stage dir) rather than a support file?  */
function buildIsContentPath($path) {
	$stage = defined('STAGE') ? STAGE : _ROOT . '/stage';
	return strpos($path, $stage . '/') === 0 && preg_match('/\.(php|html)$/', $path) === 1;
}

/** returns @void: records a file written by the build for the changes report — $old is the previous contents (null for a new file). Called by saveAs(); inert unless buildMark() ran  */
function buildRecordChange($path, $old, $new) {
	if (!isset($GLOBALS['_buildChanges'])) return;
	if (strpos($path, '/.cache/') !== false) return; // the build's own bookkeeping, not output
	if ($old === $new) return;
	$rec = ['path' => buildRelPath($path), 'content' => buildIsContentPath($path)];
	if ($old === null) {
		$rec['kind'] = 'new';
		$rec['chars'] = strlen($new);
	} else {
		$rec['kind'] = 'changed';
		$rec['ws'] = preg_replace('/\s+/', '', $old) === preg_replace('/\s+/', '', $new);
		// the change signature: lines present only in the old version, and only in the new, as sets (see the header)
		$o = array_fill_keys(explode("\n", $old), true);
		$n = array_fill_keys(explode("\n", $new), true);
		$removed = array_keys(array_diff_key($o, $n));
		$added = array_keys(array_diff_key($n, $o));
		$rec['sig'] = md5(implode("\n", $removed) . "\0" . implode("\n", $added));
		$rec['lines_rem'] = count($removed);
		$rec['lines_add'] = count($added);
		$rec['chars_rem'] = strlen(implode('', $removed));
		$rec['chars_add'] = strlen(implode('', $added));
		$rec['sample'] = [trim((string) ($removed[0] ?? '')), trim((string) ($added[0] ?? ''))];
	}
	$GLOBALS['_buildChanges'][] = $rec;
}

/** returns @void: records a file deleted by the build for the changes report; inert unless buildMark() ran  */
function buildRecordDelete($path) {
	if (!isset($GLOBALS['_buildChanges'])) return;
	$GLOBALS['_buildChanges'][] = ['path' => buildRelPath($path), 'content' => buildIsContentPath($path), 'kind' => 'deleted'];
}

/** returns @string: the compact size-of-change string for one changed-file record, e.g. "Δ +18/−2 lines, +856/−256 chars"  */
function buildDelta($rec) {
	if ($rec['kind'] === 'new') return number_format($rec['chars']) . ' chars';
	if ($rec['kind'] !== 'changed') return '';
	return "Δ +{$rec['lines_add']}/−{$rec['lines_rem']} lines, +" . number_format($rec['chars_add']) . '/−' . number_format($rec['chars_rem']) . ' chars';
}

/** returns @void: prints the changes report — a one-line headline, then content pages grouped as unique / batched (identical changes) / whitespace-only / new / deleted, then support files (see the header for the design)  */
function buildChangesReport() {
	$changes = $GLOBALS['_buildChanges'] ?? [];
	$link = fn ($rec) => buildLink('/' . $rec['path'], $rec['path']);
	$size = fn ($rec) => ($rec['chars_add'] ?? 0) + ($rec['chars_rem'] ?? 0);

	$new = $deleted = $support = []; $bySig = []; $wsBySig = [];
	foreach ($changes as $rec) {
		if (!$rec['content']) { $support[] = $rec; continue; }
		if ($rec['kind'] === 'new') { $new[] = $rec; continue; }
		if ($rec['kind'] === 'deleted') { $deleted[] = $rec; continue; }
		if ($rec['ws']) $wsBySig[$rec['sig']][] = $rec;
		else $bySig[$rec['sig']][] = $rec;
	}
	$unique = []; $batches = [];
	foreach ($bySig as $group) {
		if (count($group) > 1) $batches[] = $group;
		else $unique[] = $group[0];
	}
	usort($unique, fn ($a, $b) => $size($b) <=> $size($a));
	usort($batches, fn ($a, $b) => count($b) <=> count($a) ?: $size($b[0]) <=> $size($a[0]));
	$wsGroups = array_values($wsBySig);
	usort($wsGroups, fn ($a, $b) => count($b) <=> count($a));
	usort($support, fn ($a, $b) => strcmp($a['path'], $b['path']));
	$wsCount = array_sum(array_map('count', $wsGroups));
	$batchedCount = array_sum(array_map('count', $batches));
	$pagesChanged = count($unique) + $batchedCount + $wsCount;

	buildOut();
	if (!$changes) { buildOut('CHANGED FILES: none'); return; }
	buildOut("CHANGED FILES: $pagesChanged content pages changed (" . count($unique) . ' unique, ' . $batchedCount . ' in ' . count($batches) . ' batches, ' . $wsCount . ' whitespace-only), ' . count($new) . ' new, ' . count($deleted) . ' deleted; ' . count($support) . ' support files');

	$sample = function ($rec) { // one removed line and one added line, so a batch explains itself
		foreach (['−' => 0, '+' => 1] as $sign => $i)
			if ($rec['sample'][$i] !== '') buildOut('        ' . $sign . ' ' . mb_strimwidth($rec['sample'][$i], 0, 110, '…'));
	};
	$groupLine = function ($group, $withDelta) use ($link) {
		$n = count($group);
		$line = '  ×' . str_pad($n, 4) . $link($group[0]) . ' (+' . ($n - 1) . ' more)' . ($withDelta ? '  ' . buildDelta($group[0]) : '');
		buildOut($line, true);
		if (buildVerbose()) foreach (array_slice($group, 1) as $rec) buildOut('        ' . $link($rec), true);
	};

	if ($unique) {
		buildOut(); buildOut('Unique changes:');
		foreach ($unique as $rec) buildOut('  ' . $link($rec) . '  ' . buildDelta($rec), true);
	}
	if ($batches) {
		buildOut(); buildOut('Batched changes (identical in every file of the batch):');
		foreach ($batches as $group) { $groupLine($group, true); $sample($group[0]); }
	}
	if ($wsGroups) {
		buildOut(); buildOut('Whitespace-only changes:');
		foreach ($wsGroups as $group) {
			if (count($group) > 1) $groupLine($group, false);
			else buildOut('  ' . $link($group[0]), true);
		}
	}
	if ($new) {
		buildOut(); buildOut('New files:');
		foreach ($new as $rec) buildOut('  ' . $link($rec) . '  ' . buildDelta($rec), true);
	}
	if ($deleted) {
		buildOut(); buildOut('Deleted files:');
		foreach ($deleted as $rec) buildOut('  ' . $rec['path']);
	}
	if ($support) {
		buildOut(); buildOut('Support files:');
		foreach ($support as $rec) buildOut('  ' . $rec['path'] . ($rec['kind'] === 'deleted' ? '  (deleted)' : '  ' . buildDelta($rec)));
	}
}

/** returns @void: prints a deduplicated severity summary of php-errors.log entries generated since buildMark()  */
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
	journal("$total new PHP error-log entries (" . count($tallies) . ' unique): ' . implode(', ', $breakdown), 2, true);
	arsort($tallies);
	foreach ($tallies as $msg => $n)
		journal('×' . $n . ' — ' . mb_strimwidth($msg, 0, 300, '…'), 2, true);
}
