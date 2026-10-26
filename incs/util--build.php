<?php #CMS > #plumbing > utilities for the big build scripts

/* BUILD LOG AND CHANGES REPORT — the output layer of the big build scripts (make-ps-site.php, make-ps-blog.php, shared with the family blogs via the PubSys sync).

DESIGN (Oct 2026, build-pipeline Phases 1–2). The build log is PLAIN TEXT. In a browser the build script wraps it in a <pre> (buildHtmlHead/Foot); from the CLI (bin/build.php, Phase 2) the same text goes to stdout, with exit codes. Nothing in the build emits presentation markup any more — until Oct 2026 the log was rich HTML (numbered journal with per-step progress dots, clickable links, a 1.4MB sortable table of every post) that nobody read: the real audit of a build is the git diff of /stage, and the only things a human or a Claude session wants from the log are (1) that it finished, (2) warnings and aborts, and (3) WHAT CHANGED. The output is designed around those three, in that order of importance, and is deliberately short.

ORDER. The page leads with a one-line verdict (✅/⚠️, what changed, warnings, PHP errors, duration), then the header, then the summary sections — RESULTS, WARNINGS, CHANGED FILES, PHP ERRORS — and only then the step-by-step BUILD LOG. Everything after the verdict is known only when the build ends, so the log is streamed into an output buffer (buildMark() starts one for the header, buildLogStart() swaps to one for the log, buildFinish() prints it all in the right order). The cost is that nothing appears in the browser until the build finishes; a build that aborts or dies still shows everything, because PHP flushes open buffers at exit, after the shutdown handlers, so the BUILD FATAL banner and error panel land in order.

The one piece of intelligence here is the CHANGES REPORT, which gives something git does not: changed files sorted by size of change, with IDENTICAL changes batched. A one-word edit to an inclusion changes 300 pages identically; git shows 300 diffs, this shows one batch with a sample line and a count. The mechanism is a change SIGNATURE: the set of lines removed plus the set of lines added, as content only, ignoring position — array set-difference, not a diff algorithm, which is why it is cheap and why the same inclusion change at line 40 of one page and line 900 of another produces the same signature. A page that carries a batch's change PLUS its own edit (every site-wide change coincides with the post-count bump on the five index pages, for instance) is DECOMPOSED: if a page's sets are a superset of a batch's, the batch is subtracted and the remainder is classified on its own, so the page is counted in the batch and its own change appears separately; the remainders are regrouped, since several pages often share the same own change (those five index pages), and the pass repeats until nothing more decomposes (three passes at most). Known limitation: batching is by identical OUTPUT change, not identical cause; an inclusion whose changed line embeds per-page content (a title in a meta tag) differs per page and lands under "unique". The report is read-only — it can never alter /stage — so a bug in it costs a misleading summary, nothing more.

A lesson from the first week: once the report could be trusted, every "X unchanged, not writing" line in the log became redundant and was demoted to ?verbose — and once the log was short enough to actually read, a message that had been false on every build for years ("updating htaccess", comparing against a path that never existed for PainSci) was spotted within minutes. Keep the log short; the chatter was hiding things.

TIER 3, SCOPED DOWN (Oct 2026). The programme's third tier was a shared build kernel with PROCESS-ISOLATED rendering: every site page rendered in a process of its own, so that no document could see another's variables — the site build renders ~300 documents in one global scope, historically the source of most of its glitches (the header of make-ps-site.php), which env-defaults.php exists to contain. Measured before deciding: a fresh PHP process costs ~870 ms to bootstrap the environment and 6–36 ms to render a page, so a process per page is a non-starter (299 pages: minutes, against 7.5 s for a full build); pcntl_fork() after bootstrap costs ~10 ms per child with byte-identical output, and works, with one trap — a forked child that exits normally sends MySQL QUIT on the inherited socket and kills the parent's prod-DB session ("server has gone away"), so children would have to SIGKILL themselves after writing their output. Paul decided AGAINST forking (2026-10-26): it is CLI-only (Apache can't fork), so the browser build would have to become a wrapper around the CLI build or keep a second, in-process scheduler, and that divergence plus the novel machinery (self-killing workers, temp-file harvesting, parallel abort semantics, timeouts for stuck children) is not justified when no leak has actually been observed in years. What landed instead: the per-page render moved out of the site loop into the include fragment incs/render-one-site-page.php — an include, not an eval, so errors name the real file and line — with buildTidyRender() and buildErrorMarkers() below doing the post-processing for it and for the bib-page loop; and bin/render-one-site-page-standalone.php, which renders one page in a process of its own and diffs it against /stage. Run that with --all to census every page for order-dependent output: a page that renders differently in isolation is the observed failure that would reopen the fork question. The kernel also runs warnIfUnbalancedHtml() (below; moved here from PubSys.php so both builds share one check) on every rendered page — the first run exposed 416 unbalanced tags on 144 pages, cleaned the same day (shared includes by hand, the per-document backlog by parallel agents, the eleven books via full customer renders), so from now on it should only ever report new instances. PubSys was left alone on purpose: a post is Markdown turned into a string, so there is no file to include, and its scoping problem is between its two loops (the PubSys.php header), a different refactor.

Plumbing: saveAs() (util--core.php) is the single funnel every build artifact passes through, so it calls buildRecordChange() with the old and new contents; the deletion sites call buildRecordDelete(). Both are inert unless buildMark() ran, so standalone callers of saveAs() pay nothing. Content vs support is a path test: a .php/.html file under the stage dir is a content page; everything else (feeds, sitemaps, tag files, data files) is a support file, listed after.

Presentation: buildOut() is the only place markup exists, and only in web mode — a span around WARNING/ABORT/FATAL lines and bold on headings and the verdict, by line pattern, so the text is identical in both modes and the browser gets colour for free. Also here: buildTrackDoc/buildErrorsReport (June 2026, originally with buildErrorsMark, now folded into buildMark) — the build renders nearly every page, so it doubles as a test suite, but PHP problems land in logs/php-errors.log, invisible in the build log, and fatals inside eval()'d renders blame "eval()'d code" without naming the document. buildTrackDoc() records the document being processed (psShutdown() in util--errors.php names it in the BUILD FATAL banner); buildErrorsReport() prints a deduplicated summary of error-log entries generated during the build.

Contracts with make-all.command's log scans: the verdict line contains 'BUILD DONE'; warnings are lines beginning 'WARNING:' (via journal(); in web mode wrapped in a span, which the scan allows for); fatal markers are the literal strings 'ABORT!', 'BUILD FATAL', 'PainSci failure', '!!! ERROR !!!'. This file must stay blog-safe: no PS-environment dependencies beyond _ROOT and the optional STAGE constant, and no PHP newer than the family Macs run (hence buildPad() rather than mb_str_pad()). */

/** returns @void: starts the build log plumbing — the clock, the php-errors.log offset for buildErrorsReport(), the current-doc tracker, the changes collector, the ?verbose flag, and the header buffer (see buildLogStart)  */
function buildMark() {
	$GLOBALS['_buildStart'] = microtime(true);
	$GLOBALS['_buildErrLog'] = _ROOT . '/logs/php-errors.log';
	$GLOBALS['_buildErrLogOffset'] = (int) @filesize($GLOBALS['_buildErrLog']);
	$GLOBALS['_buildCurrentDoc'] = '';
	$GLOBALS['_buildChanges'] = [];
	$GLOBALS['_buildVerbose'] = strpos($_SERVER['QUERY_STRING'] ?? '', 'verbose') !== false;
	$GLOBALS['_buildHeader'] = '';
	$GLOBALS['_buildFinished'] = false;
	ob_start(); // the header lines the script prints next are captured, to be printed after the verdict
	register_shutdown_function('buildAbortFlush');
}

/** returns @void: shutdown hook for a build that never reached buildFinish() — an ABORT! exit or a fatal — so the page still leads with a verdict and keeps its header: prints '❌ BUILD ABORTED', the captured header, then the captured log (which by now holds the abort or BUILD FATAL banner: psShutdown() runs first, having been registered earlier), and closes the <pre>  */
function buildAbortFlush() {
	if (!empty($GLOBALS['_buildFinished'])) return;
	$log = ob_get_level() ? (string) ob_get_clean() : '';
	if (empty($GLOBALS['_buildLogBuffered'])) { $header = $log; $log = ''; } // died before buildLogStart(): the one buffer held the header
	else $header = $GLOBALS['_buildHeader'] ?? '';
	buildOut('❌ BUILD ABORTED — see the end of the log below — ' . date('Y-m-d H:i'));
	$panelAt = buildIsCli() ? false : strpos($log, "<style id='ps-err-css'>"); // in web mode the error panel (psRenderErrorPanel) has already landed at the end of the buffer; it belongs after the </pre>, not inside it
	if ($panelAt === false) { echo $header . $log; if (!buildIsCli()) echo "</pre>\n"; }
	else echo $header . substr($log, 0, $panelAt) . "</pre>\n" . substr($log, $panelAt);
}

/** returns @boolean: true when the build was asked for per-file detail (?verbose); journal() then prints its normally-silent messages too  */
function buildVerbose() {
	return !empty($GLOBALS['_buildVerbose']);
}

/** returns @boolean: true when running from the command line (bin/build.php) rather than a web request  */
function buildIsCli() {
	return PHP_SAPI === 'cli';
}

/** returns @void: prints one line of build log. Web mode escapes it for the <pre> and adds colour/bold by line pattern; CLI prints it raw. $html = true means the line already carries markup from buildLink() (the caller escapes nothing else, so keep such lines to paths and fixed strings)  */
function buildOut($line = '', $html = false) {
	if (buildIsCli()) { echo ($html ? html_entity_decode(strip_tags($line), ENT_QUOTES) : $line) . "\n"; return; }
	if (!$html) {
		$line = htmlspecialchars($line, ENT_NOQUOTES);
		if (preg_match('/^\s*WARNING:/', $line)) $line = "<span class='warn'>$line</span>";
		elseif (strpos($line, 'ABORT!') !== false || strpos($line, 'BUILD FATAL') !== false) $line = "<span class='fatal'>$line</span>";
		elseif (preg_match('/^── .* ──$/u', $line) || strpos($line, 'BUILD DONE') !== false) $line = "<strong>$line</strong>";
	}
	echo $line . "\n";
}

/** returns @void: in web mode, prints the minimal HTML page head that wraps the build log in a <pre>; nothing from the CLI  */
function buildHtmlHead($title) {
	if (buildIsCli()) return;
	echo '<!DOCTYPE html>' . "\n" . '<html lang="en">' . "\n" . '<head>' . "\n" . '<meta charset="utf-8">' . "\n" . '<title>' . htmlspecialchars($title) . '</title>' . "\n"
		. '<style>body {font-family: Menlo, Consolas, monospace; font-size: 13px; color: #333; margin: 2%;} pre {white-space: pre-wrap;} strong {color: #000;} .warn {color: #b00;} .fatal {color: #b00; font-weight: bold;}</style>' . "\n"
		. '</head><body>' . "\n" . '<pre>';
}

/** returns @void: in web mode, closes the HTML page (buildFinish() has already closed the <pre>); nothing from the CLI  */
function buildHtmlFoot() {
	if (!buildIsCli()) echo "</body>\n</html>\n";
}

/** returns @void: prints a section heading of the summary, e.g. "── RESULTS ──", preceded by a blank line  */
function buildHeading($title) {
	buildOut();
	buildOut("── $title ──");
}

/** returns @string: $text as a link to $href in web mode, or just $text in CLI mode — for use in buildOut(…, true) lines  */
function buildLink($href, $text) {
	if (buildIsCli()) return $text;
	return "<a href='" . htmlspecialchars($href, ENT_QUOTES) . "' target='_blank'>" . htmlspecialchars($text, ENT_NOQUOTES) . "</a>";
}

/** returns @string: $s padded with spaces to $width characters (multibyte-safe, unlike str_pad; mb_str_pad needs PHP 8.3, which the family Macs may not have); $left pads on the left  */
function buildPad($s, $width, $left = false) {
	$n = max(0, $width - mb_strlen((string) $s));
	return $left ? str_repeat(' ', $n) . $s : $s . str_repeat(' ', $n);
}

/** returns @string: "1 page" / "2 pages"  */
function buildPlural($n, $word, $plural = null) {
	return number_format($n) . ' ' . ($n == 1 ? $word : ($plural ?? $word . 's'));
}

/** returns @void: swaps the header buffer for the log buffer — from here on the step-by-step process log is captured, so that buildFinish() can print the summary BEFORE it; call after the header lines  */
function buildLogStart() {
	$GLOBALS['_buildHeader'] = (string) ob_get_clean();
	ob_start();
	$GLOBALS['_buildLogBuffered'] = true;
}

/** returns @void: records which source document the build is currently processing, so fatals can be blamed on it (see the header)  */
function buildTrackDoc($doc) {
	$GLOBALS['_buildCurrentDoc'] = $doc;
}

/** returns @string: a path relative to the project root  */
function buildRelPath($path) {
	return strpos($path, _ROOT . '/') === 0 ? substr($path, strlen(_ROOT) + 1) : $path;
}

/** returns @string: the stage root — _ROOT/stage when it exists (PainSci: both builds live under it), else the STAGE constant (the family blogs' html/)  */
function buildStageRoot() {
	if (is_dir(_ROOT . '/stage')) return _ROOT . '/stage';
	return defined('STAGE') ? STAGE : _ROOT . '/stage';
}

/** returns @boolean: is this output path a content page (a .php/.html file under the stage dir) rather than a support file?  */
function buildIsContentPath($path) {
	return strpos($path, buildStageRoot() . '/') === 0 && preg_match('/\.(php|html)$/', $path) === 1;
}

/** returns @string: how a changed file is named in the report — a content page as its site path (/articles/x.php, /blog/x.html, or /x.html on the family blogs), a support file as its project-relative path  */
function buildDisplayPath($rec) {
	if (!$rec['content']) return $rec['path'];
	$stageRel = buildRelPath(buildStageRoot());
	return strpos($rec['path'], $stageRel . '/') === 0 ? substr($rec['path'], strlen($stageRel)) : '/' . $rec['path'];
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
		$rec['removed'] = array_map('strval', $removed); // ordered lists (first occurrence); sizes, signature, and sample are derived from these at report time, because decomposition can shrink them
		$rec['added'] = array_map('strval', $added);
		buildMeasure($rec);
	}
	$GLOBALS['_buildChanges'][] = $rec;
}

/** returns @void: records a file deleted by the build for the changes report; inert unless buildMark() ran  */
function buildRecordDelete($path) {
	if (!isset($GLOBALS['_buildChanges'])) return;
	$GLOBALS['_buildChanges'][] = ['path' => buildRelPath($path), 'content' => buildIsContentPath($path), 'kind' => 'deleted'];
}

/** returns @string: the size-of-change column for one record — chars added/removed, compact: "+356/−1,042", or just "+32" / "−32" when one side is zero; "new" / "deleted" / "(ws)" for the other kinds  */
function buildDeltaChars($rec) {
	if ($rec['kind'] === 'new') return 'new';
	if ($rec['kind'] === 'deleted') return 'deleted';
	if (!empty($rec['ws'])) return '(ws)';
	$a = $rec['chars_add']; $r = $rec['chars_rem'];
	if ($a && $r) return '+' . number_format($a) . '/−' . number_format($r);
	return $a ? '+' . number_format($a) : '−' . number_format($r);
}

/** returns @string: the lines part of a change, for after the path: "(+1/−2 lines)", "(+1 line)", "(12,345 chars)" for a new file, "" otherwise  */
function buildDeltaLines($rec) {
	if ($rec['kind'] === 'new') return '(' . number_format($rec['chars']) . ' chars)';
	if ($rec['kind'] !== 'changed' || !empty($rec['ws'])) return '';
	$a = $rec['lines_add']; $r = $rec['lines_rem'];
	if ($a && $r) return "(+$a/−$r lines)";
	return $a ? '(' . buildPlural($a, 'line') . ' added)' : '(' . buildPlural($r, 'line') . ' removed)';
}

/** returns @void: (re)derives a changed record's signature, sizes, and sample lines from its removed/added line lists  */
function buildMeasure(&$rec) {
	$r = $rec['removed']; $a = $rec['added'];
	sort($r); sort($a); // order-independent signature: decomposition remainders must match each other regardless of where the lines sat
	$rec['sig'] = md5(implode("\n", $r) . "\0" . implode("\n", $a));
	$rec['lines_rem'] = count($rec['removed']);
	$rec['lines_add'] = count($rec['added']);
	$rec['chars_rem'] = strlen(implode('', $rec['removed']));
	$rec['chars_add'] = strlen(implode('', $rec['added']));
	$rec['sample'] = [trim((string) ($rec['removed'][0] ?? '')), trim((string) ($rec['added'][0] ?? ''))];
}

/** returns @array: the changes partitioned for reporting — unique, batches, wsGroups, new, deleted, support — plus the counts the headline and verdict need. Batches are groups of identical change; a page whose change is a superset of a batch's is decomposed into "member of that batch" plus its own remainder (see the header)  */
function buildChangesPartition() {
	$changes = $GLOBALS['_buildChanges'] ?? [];
	$size = fn ($rec) => ($rec['chars_add'] ?? 0) + ($rec['chars_rem'] ?? 0);
	$new = $deleted = $support = []; $items = []; $wsBySig = [];
	foreach ($changes as $rec) {
		if (!$rec['content']) { $support[] = $rec; continue; }
		if ($rec['kind'] === 'new') { $new[] = $rec; continue; }
		if ($rec['kind'] === 'deleted') { $deleted[] = $rec; continue; }
		if ($rec['ws']) { $wsBySig[$rec['sig']][] = $rec; continue; }
		$rec['memberOf'] = []; // signatures of batches this page was found to contain (by decomposition)
		$items[] = $rec;
	}
	$pagesChanged = count($items) + array_sum(array_map('count', $wsBySig));

	/* Decomposition, up to three passes: group by signature; for every group — a lone page or a batch — subtract any strictly larger batch whose removed AND added lines the group's change wholly contains, re-measure the remainders, and go again. Remainders often match each other (the index pages all carry the same post-count bump), forming new batches on the next pass; and a small group that shares a composite change (the site-wide edit plus the same own edit) is decomposed as a whole, so it is counted in the big batch and keeps its own batch with the distinguishing sample line. "Strictly larger" prevents two equal-sized groups subtracting each other. A page with nothing left after subtraction is simply a member of the batches it contained. */
	$batchSets = []; // sig => canonical record of a batch (its first member), kept across passes so later passes can subtract earlier batches
	for ($pass = 0; $pass < 3; $pass++) {
		$groups = [];
		foreach ($items as $i => $rec) if ($rec['removed'] || $rec['added']) $groups[$rec['sig']][] = $i;
		foreach ($groups as $sig => $idx) if (count($idx) > 1 && !isset($batchSets[$sig])) $batchSets[$sig] = $items[$idx[0]];
		uasort($batchSets, fn ($a, $b) => (count($groups[$b['sig']] ?? []) <=> count($groups[$a['sig']] ?? [])) ?: ($size($b) <=> $size($a)));
		$changed = false;
		foreach ($groups as $sig => $idx) {
			$first = $items[$idx[0]]; // every member of a group has the same sets, so test the first
			foreach ($batchSets as $bsig => $b) {
				if ($bsig === $sig || in_array($bsig, $first['memberOf'])) continue;
				if (count($groups[$bsig] ?? []) <= count($idx)) continue; // only a strictly larger batch
				if (array_diff($b['removed'], $first['removed']) || array_diff($b['added'], $first['added'])) continue; // not a superset
				foreach ($idx as $i) {
					$items[$i]['removed'] = array_values(array_diff($items[$i]['removed'], $b['removed']));
					$items[$i]['added'] = array_values(array_diff($items[$i]['added'], $b['added']));
					$items[$i]['memberOf'][] = $bsig;
					buildMeasure($items[$i]);
				}
				$first = $items[$idx[0]];
				$changed = true;
			}
		}
		if (!$changed) break;
	}

	// final grouping: pages (or remainders) sharing a signature are a batch; the rest are unique; decomposed members join their batches' counts
	$groups = [];
	foreach ($items as $i => $rec) if ($rec['removed'] || $rec['added']) $groups[$rec['sig']][] = $rec;
	$batches = []; $unique = [];
	foreach ($groups as $sig => $members) {
		$extra = array_filter($items, fn ($r) => in_array($sig, $r['memberOf']));
		if (count($members) + count($extra) > 1) $batches[] = ['rec' => $members[0], 'members' => $members, 'extra' => array_values($extra)];
		else $unique[] = $members[0];
	}
	foreach ($batchSets as $sig => $b) if (!isset($groups[$sig])) { // a batch whose every member was consumed by decomposition (rare: all of them also had own changes)
		$extra = array_values(array_filter($items, fn ($r) => in_array($sig, $r['memberOf'])));
		if (count($extra) > 1) $batches[] = ['rec' => $b, 'members' => [], 'extra' => $extra];
	}
	$count = fn ($bt) => count($bt['members']) + count($bt['extra']);
	usort($unique, fn ($a, $b) => $size($b) <=> $size($a));
	usort($batches, fn ($a, $b) => $count($b) <=> $count($a) ?: $size($b['rec']) <=> $size($a['rec']));
	$wsGroups = array_values($wsBySig);
	usort($wsGroups, fn ($a, $b) => count($b) <=> count($a));
	usort($support, fn ($a, $b) => strcmp($a['path'], $b['path']));
	$p = compact('unique', 'batches', 'wsGroups', 'new', 'deleted', 'support');
	$p['ws'] = array_sum(array_map('count', $wsGroups));
	$p['pages'] = $pagesChanged;
	$p['any'] = (bool) $changes;
	return $p;
}

/** returns @string: the changes headline, zero categories suppressed — e.g. "296 pages changed (2 batches, 1 unique), 1 new page, 1 page deleted; 3 support files" or "nothing changed"; $short drops the parenthetical breakdown for the verdict line  */
function buildChangesHeadline($p, $short = false) {
	if (!$p['any']) return 'nothing changed';
	$parts = [];
	if ($p['pages']) {
		$s = buildPlural($p['pages'], 'page') . ' changed';
		if (!$short) {
			$how = [];
			if ($p['batches']) $how[] = buildPlural(count($p['batches']), 'batch', 'batches') . ' of identical changes';
			if ($p['unique']) $how[] = count($p['unique']) . ' unique';
			if ($p['ws']) $how[] = "{$p['ws']} whitespace-only";
			if ($p['batches'] || $p['ws']) $s .= ' (' . implode(', ', $how) . ')';
		}
		$parts[] = $s;
	}
	if ($p['new']) $parts[] = buildPlural(count($p['new']), 'new page');
	if ($p['deleted']) $parts[] = buildPlural(count($p['deleted']), 'page') . ' deleted';
	$s = implode(', ', $parts);
	if ($p['support']) $s .= ($s ? '; ' : '') . buildPlural(count($p['support']), 'support file');
	return $s;
}

/** returns @void: prints the CHANGED FILES section — the headline, then content pages as unique / batched (identical changes, with a sample line) / whitespace-only / new / deleted, then support files; sizes lead each line, biggest first (see the header for the design)  */
function buildChangesReport($p) {
	$link = fn ($rec) => buildLink('/' . $rec['path'], buildDisplayPath($rec));
	$col = fn ($s) => buildPad($s, 14, true);
	buildHeading('CHANGED FILES');
	buildOut('  ' . buildChangesHeadline($p));
	if (!$p['any']) return;
	$sample = function ($rec) { // one removed line and one added line, so a batch explains itself
		foreach (['−' => 0, '+' => 1] as $sign => $i)
			if ($rec['sample'][$i] !== '') buildOut('                   ' . $sign . ' ' . mb_strimwidth($rec['sample'][$i], 0, 100, '…'));
	};
	$groupLine = function ($rec, $members, $extra = []) use ($link, $col) { // $rec: the batch's canonical change; $extra: pages that contain it plus their own change (decomposed), listed in verbose mode with a note
		$n = count($members) + count($extra);
		$lines = buildDeltaLines($rec);
		buildOut('  ×' . buildPad($n, 4) . $col(buildDeltaChars($rec)) . '  ' . $link($rec) . '  (+' . ($n - 1) . ' more' . ($lines ? '; ' . trim($lines, '()') . ' each' : '') . ')', true);
		if (buildVerbose()) {
			foreach (array_slice($members, 1) as $r) buildOut('                     ' . $link($r), true);
			foreach ($extra as $r) buildOut('                     ' . $link($r) . '  (+ own changes, listed separately)', true);
		}
	};
	$fileLine = function ($rec) use ($link, $col) {
		$lines = buildDeltaLines($rec);
		buildOut('       ' . $col(buildDeltaChars($rec)) . '  ' . $link($rec) . ($lines ? "  $lines" : ''), true);
	};
	if ($p['unique']) {
		buildOut(); buildOut('  Unique changes:');
		foreach ($p['unique'] as $rec) $fileLine($rec);
	}
	if ($p['batches']) {
		buildOut(); buildOut('  Batched changes (identical in every file of the batch):');
		foreach ($p['batches'] as $bt) { $groupLine($bt['rec'], $bt['members'], $bt['extra']); $sample($bt['rec']); }
	}
	if ($p['wsGroups']) {
		buildOut(); buildOut('  Whitespace-only changes:');
		foreach ($p['wsGroups'] as $group) {
			if (count($group) > 1) $groupLine($group[0], $group);
			else $fileLine($group[0]);
		}
	}
	if ($p['new']) {
		buildOut(); buildOut('  New pages:');
		foreach ($p['new'] as $rec) $fileLine($rec);
	}
	if ($p['deleted']) {
		buildOut(); buildOut('  Deleted pages:');
		foreach ($p['deleted'] as $rec) buildOut('       ' . $col('deleted') . '  ' . buildDisplayPath($rec));
	}
	if ($p['support']) {
		buildOut(); buildOut('  Support files:');
		foreach ($p['support'] as $rec) {
			$lines = buildDeltaLines($rec);
			buildOut('       ' . $col(buildDeltaChars($rec)) . '  ' . $rec['path'] . ($lines ? "  $lines" : ''));
		}
	}
}

/** returns @array|null: php-errors.log entries logged since buildMark(), grouped — ['total', 'serious' (total minus app-level warnings/notices), 'unique', 'breakdown' (strings like "3 Warning"), 'tallies' (message => count, most frequent first)]; null when the log can't be read  */
function buildErrorsTally() {
	$log = $GLOBALS['_buildErrLog'] ?? null;
	if (!$log or !file_exists($log)) return null;
	$offset = $GLOBALS['_buildErrLogOffset'] ?? 0;
	$size = filesize($log);
	if ($size < $offset) $offset = 0; // the log was truncated mid-build; report on all of it
	$tallies = [];
	if ($size > $offset) {
		// group identical messages: a single bad call in a shared include fires once per page, so one real problem can mean hundreds of identical lines
		$fh = fopen($log, 'r');
		fseek($fh, $offset);
		while (($line = fgets($fh)) !== false) {
			$line = trim($line);
			if ($line === '') continue;
			$msg = preg_replace('/^\[[^\]]*\]\s*/', '', $line); // strip the timestamp prefix so identical messages group
			$tallies[$msg] = ($tallies[$msg] ?? 0) + 1;
		}
		fclose($fh);
		arsort($tallies);
	}
	$severities = ['PHP Fatal' => 0, 'PHP Parse' => 0, 'PHP Warning' => 0, 'PHP Deprecated' => 0, 'PainSci failure' => 0, 'PainSci warning' => 0, 'PainSci notice' => 0, 'other' => 0]; // the PainSci ones are the psErr* family's log lines (util--errors.php)
	$total = 0;
	foreach ($tallies as $msg => $n) {
		$total += $n;
		$known = false;
		foreach ($severities as $sev => $x) {
			if (strpos($msg, $sev) === 0) { $severities[$sev] += $n; $known = true; break; }
		}
		if (!$known) $severities['other'] += $n;
	}
	$breakdown = [];
	foreach ($severities as $sev => $n) if ($n > 0) $breakdown[] = "$n " . str_replace('PHP ', '', $sev);
	$serious = $total - $severities['PainSci warning'] - $severities['PainSci notice']; // for the verdict: app-level warnings and notices are the permanent record of events the log already reports as WARNING: lines, so counting them again as errors double-counts (the missing-image check, Oct 2026)
	return ['total' => $total, 'serious' => $serious, 'unique' => count($tallies), 'breakdown' => $breakdown, 'tallies' => $tallies];
}

/** returns @void: prints the PHP ERRORS section — a deduplicated severity summary of php-errors.log entries generated during the build  */
function buildErrorsReport($t) {
	buildHeading('PHP ERRORS');
	if ($t === null) { buildOut('  (no error log on this site)'); return; }
	if ($t['total'] == 0) { buildOut('  none 👍'); return; }
	buildOut('  ' . number_format($t['total']) . ' new error-log entries (' . $t['unique'] . ' unique): ' . implode(', ', $t['breakdown']));
	foreach ($t['tallies'] as $msg => $n)
		buildOut('  ×' . $n . ' — ' . mb_strimwidth($msg, 0, 300, '…'));
}

/** returns @string: the one-line verdict that leads the page, e.g. "✅ BUILD DONE — 1 page changed — 0 warnings, 0 errors — 4.2s, 2026-10-19 14:02"; errors = PHP's own log entries plus app-level failures (see buildErrorsTally); 'BUILD DONE' is a contract with make-all.command  */
function buildVerdict($p, $nWarnings, $nErrors) {
	$icon = ($nWarnings || $nErrors) ? '⚠️' : '✅';
	$secs = isset($GLOBALS['_buildStart']) ? round(microtime(true) - $GLOBALS['_buildStart'], 1) : 0;
	$flag = $nWarnings ? ' WITH ' . strtoupper(buildPlural($nWarnings, 'warning')) : '';
	return "$icon BUILD DONE$flag — " . buildChangesHeadline($p, true) . ' — ' . buildPlural($nWarnings, 'warning') . ', ' . buildPlural($nErrors, 'error') . " — {$secs}s, " . date('Y-m-d H:i');
}

/** returns @void: ends the build — prints the verdict, the captured header, the summary (the caller's result lines, warnings hoisted out of the log, the changes report, the PHP errors), then the captured process log, then closes the <pre>; the error panel, if any, renders after it at shutdown  */
function buildFinish($resultLines = []) {
	$GLOBALS['_buildFinished'] = true;
	$log = '';
	if (!empty($GLOBALS['_buildLogBuffered'])) {
		$log = (string) ob_get_clean();
		$GLOBALS['_buildLogBuffered'] = false;
	}
	$header = $GLOBALS['_buildHeader'] ?? '';
	$p = buildChangesPartition();
	$t = buildErrorsTally();
	preg_match_all('/^(?:<span[^>]*>)?[ \t]*WARNING:.*$/m', $header . $log, $m); // the header can carry a warning too (the site build's prod-DB check)
	$warnings = $m[0];

	buildOut(buildVerdict($p, count($warnings), $t['serious'] ?? 0));
	echo $header;
	buildHeading('RESULTS');
	foreach ($resultLines as $line) buildOut('  ' . $line);
	if ($warnings) {
		buildHeading('WARNINGS (' . count($warnings) . ')');
		foreach ($warnings as $w) echo '  ' . trim($w) . "\n"; // copied verbatim from the log, which is already output-ready (escaped and styled in web mode), so not via buildOut(); make-all.command's warning scan dedupes these copies
	}
	buildChangesReport($p);
	buildErrorsReport($t);
	buildHeading('BUILD LOG');
	buildOut('  (the process, step by step — everything that matters is above)');
	echo $log;
	if (!buildIsCli()) echo "</pre>\n";
}

/** returns @string: a rendered page tidied for /stage — runs of blank lines and of spaces collapsed, so trivial whitespace churn never shows in a diff, and the period added after "et al" where the citation templates leave none (CLAUDE.md § Source vs. rendered output)  */
function buildTidyRender($html) {
	$html = mb_ereg_replace("\r?\n{2,20}", "\n\n", $html);
	$html = mb_ereg_replace(' {2,10}', ' ', $html);
	$html = preg_replace('|et\&nbsp;al</em>([^\.])|', 'et&nbsp;al.</em>$1', $html); // adds a period to MOST instances of "et al" that don't already have one, but misses ones with other HTML in there, like </a>
	return str_replace('al.</em></a>.', 'al</em></a>.', $html); // fix the exceptions that rule creates
}

/** returns @void: gentle heads-up journal warning when a post's source content has unbalanced block-level HTML (unclosed <p>, missing </ul>, etc.). $srcFile is the source document path as known to the caller (used for the log line and the panel edit link); $srcLabel is the human-readable version; $rawLine is the micropost's raw source line, used to locate its true line number in the POSTS file for the edit link. */
function warnIfUnbalancedHtml($content, $srcLabel, $srcFile = null, $rawLine = null) {
	/* Why this matters: MarkdownExtra's HTML-block hashing needs balanced block-level tags. Browsers silently auto-close bad nesting, so hand-written HTML that "looks fine" on an article page can explode when the same markup goes through a PubSys build — mildly (stray litter like "</ul></p>") or spectacularly (tags escaped to visible text: "<p>&lt;</p>"). Real incident: the 2026-07-10 pillow post, with markup copied from §crick where the imbalance had been invisible for who knows how long.

	Deliberately gentle and cheap: warn and continue, never abort — the family blogs (especially ephemeral) contain plenty of imperfect hand-written HTML that renders fine in browsers, and a build must tolerate it. Count-based only, so crossed nesting with matching counts (e.g. a stray </p> compensating for an unclosed <p> elsewhere) slips through; in practice crossed pairs almost always travel with a count imbalance somewhere in the same post. */
	/* Strip comment-only PHP blocks FIRST — the house format for private/AI draft content (see CLAUDE.md), and they routinely wrap draft HTML that carries its own inner PHP tags. The general strip below is non-greedy, so it would pair such a block's opening delimiter with an INNER closing delimiter and leave the rest of the commented block (e.g. a dangling closing blockquote tag) exposed to the counter — a real false positive (2023-03-10 "can the mind create pain?"). Matching through to the comment's closing star-slash is safe because PHP block comments do not nest, so the first one is always the true end of the block. */
	$content = preg_replace('|<\?php\s*/\*.*?\*/\s*\?>|s', '', $content);
	$content = preg_replace('|<\?php.*?\?>|s', '', $content); // ignore remaining (non-comment) PHP blocks — tags inside code aren't part of the source HTML structure
	$content = preg_replace('|<!--.*?-->|s', '', $content); // ignore HTML comments, which can legitimately contain commented-out markup
	$content = preg_replace('|```.*?```|s', '', $content); // ignore fenced code blocks and backtick code spans, where example tags are content, not structure
	$content = preg_replace('|`[^`' . "\n" . ']*`|', '', $content);
	$unbalanced = [];
	foreach (['p', 'ul', 'ol', 'li', 'blockquote', 'div'] as $tag) {
		$opens = preg_match_all('|<' . $tag . '[\s>]|i', $content); // opening pattern allows attributes; [\s>] keeps <p from matching <pre or <pq
		$closes = preg_match_all('|</' . $tag . '>|i', $content);
		if ($opens != $closes) {
			$unbalanced[] = "$tag {$opens}:{$closes}";
		}
	}
	if ($unbalanced) {
		$where = buildLocateUnbalancedHtml($content); // only when the counts are off, so clean pages pay nothing (Oct 2026): the counts say a page is broken, this says where
		$msg = "unbalanced HTML in [$srcLabel] (open:close counts — " . implode(', ', $unbalanced) . ') … browsers repair it silently, and Markdown may mangle a post'
			. ($where ? '. Where: ' . implode('; ', array_slice($where, 0, 3)) . (count($where) > 3 ? '; +' . (count($where) - 3) . ' more' : '') : '');
		journal("warning: $msg", 2, true); // inline on the build page, at the point in the flow where it happened
		/* Also on the permanent record via the unified error pipeline: log line + panel + severity counter. Markers became failure-only in July 2026 (this detector was one of the two cases that forced that policy), so psErrWarn($msg) would no longer abort and COULD replace this pair — but the hand-rolled psErrLog+psCollect stays deliberately, for the attribution finesse below: it points the panel's edit link at the post source file, where generic psErrWarn would attribute to this detector's own call site. */
		/* Attribute the report to the post source document, not to this detector — that's what the panel's edit link should open. (The panel's own doc-substitution only rescues eval pseudo-paths, correctly; a real file passed here is linked as-is.) psDocPath resolves against both possible roots (painscience /blog and the family-blog roots); the posts/ prefix candidate matters because getMacroPost basenames $fn early (line ~597), so the caller may pass either form. Line 1 because the check is whole-post — a known limitation. */
		$file = __FILE__; $line = __LINE__;
		foreach ($srcFile ? [$srcFile, 'posts/' . basename($srcFile)] : [] as $cand) {
			if ($abs = psDocPath($cand)) { $file = $abs; $line = 1; break; }
		}
		if ($rawLine !== null && $line === 1) { // micropost: find the post's true line number in the POSTS file, so the edit link jumps straight to it. Lazy — one file read, only when a warning actually fires. array_search needs the raw line verbatim; two byte-identical posts in one file would fool it, which is no realistic loss.
			$idx = array_search($rawLine, file($file, FILE_IGNORE_NEW_LINES));
			if ($idx !== false) $line = $idx + 1;
		}
		psErrLog('PainSci warning: ', $msg, $file, $line);
		psCollect('app', 'warning', $msg, $file, $line);
	}
}

/** returns @array: where the block-level HTML of $content (already stripped of PHP, comments, and code, as warnIfUnbalancedHtml() does) goes wrong — one line per problem, "line N: <p> never closed (closed over by </li>) …context…", in document order. $strictP also applies HTML's implicit-close rule for <p>, which flags block elements inside a paragraph — a stricter class than the builds check, for ad hoc sweeps only  */
function buildLocateUnbalancedHtml($content, $strictP = false) {
	/* The counts in warnIfUnbalancedHtml() say THAT a page is unbalanced; this says WHERE, which is what the author needs — "p 209:210" in a 12,000-line book is not a finding, "line 4342, after 'experience with them:'" is. It walks the same six tags in order with a stack, the way a browser's parser would: a close with no matching open is stray; a close that matches an open further down the stack has closed over everything above it, so those are reported as never closed; whatever is left open at the end is reported too. Line numbers and context are positions in the RENDERED page (the only text this function sees), so for a site page they are greppable in the source; for a post they are positions in the rendered post content. Built in the Oct 2026 cleanup, when a scratch version of exactly this found the 416 problems the counts could only tally. Crossed nesting that happens to leave the counts equal (an unclosed <p> early plus a stray </p> late) escapes the counts, and so escapes this — it runs only when the counts are off. */
	$tags = ['p', 'ul', 'ol', 'li', 'blockquote', 'div'];
	preg_match_all('#<(/?)(' . implode('|', $tags) . ')(?=[\s>/])[^>]*>#i', $content, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
	$stack = []; $problems = [];
	foreach ($m as $t) {
		$close = $t[1][0] === '/'; $tag = strtolower($t[2][0]); $pos = $t[0][1];
		if (!$close) {
			if ($strictP && $stack && end($stack)[0] === 'p' && ($tag === 'p' || in_array($tag, ['ul', 'ol', 'blockquote', 'div']))) {
				$open = array_pop($stack);
				$problems[] = ["<p> never closed (a <$tag> opened inside it; browsers close the <p> there)", $open[1]];
			}
			$stack[] = [$tag, $pos];
			continue;
		}
		for ($i = count($stack) - 1; $i >= 0; $i--) if ($stack[$i][0] === $tag) break;
		if ($i < 0) { $problems[] = ["stray </$tag>", $pos]; continue; }
		foreach (array_splice($stack, $i) as $k => $open) if ($k > 0) $problems[] = ["<{$open[0]}> never closed (closed over by </$tag>)", $open[1]];
	}
	foreach ($stack as $open) $problems[] = ["<{$open[0]}> never closed (end of page)", $open[1]];
	usort($problems, fn ($a, $b) => $a[1] <=> $b[1]);
	$out = [];
	foreach ($problems as [$what, $pos]) {
		$line = substr_count(substr($content, 0, $pos), "\n") + 1;
		$out[] = "line $line: $what …" . preg_replace('/\s+/', ' ', substr($content, max(0, $pos - 60), 120)) . '…';
	}
	return $out;
}

/** returns @array: the messages of every "!!! ERROR !!! … -->" marker in a rendered page — the build marker psErrFail() leaves (psErrMarker() in util--errors.php; the format is a contract), meaning broken content that must not ship  */
function buildErrorMarkers($html) {
	preg_match_all('|!!! ERROR !!! (.+?)-->|', $html, $m);
	return $m[1];
}
