<?php #pubsys > build procedure

/* PubSys, © 2014–2026 by Paul Ingraham
A "simple" (ha ha) content management system focused on easy, simple data entry and management and robustly simple HTML output. PubSys reads text files that contain Markdown and other Markdown-like shorthands and turns them into website files for upload. PubSys runs in a browser locally. Core PubSys code is in 'PubSys.php'.  It is used primarily by this build script, or a default build script make-site.php.  When those scripts run, the constant MODE_PUBSYS is true.

THIS build script uses PubSys to build the PainScience.com blog with many special cases. In this context, $ps is true, and PubSys has a lot of "if ($ps) {painsci stuff}".

Development notes (extremely out of date as of 2023):
craftdocs://open?blockId=EF2F7C6B-F8BB-46AA-A75E-281CC2F2CE3F&spaceId=bc7d854c-3e5b-a34e-4850-a6d2f31a1a59

Manual (also extremely out of date):
~/Sites/painscience/incs/pubsys-manual.pages

*/

// ENVIRONMENT AND CONTEXT
// Where is PubSys running?
// the identifier `path is used whereever most key paths are defined, as a troubleshooting aid

date_default_timezone_set('America/Vancouver'); // #timezone — keep in sync with the canonical PS_TIMEZONE constant in util--errors.php (hardcoded: this runs BEFORE the loader block requires util--errors.php, and the sync block below writes provenance stamps that were coming out in UTC)

$root_dev = $root_true = $_SERVER['DOCUMENT_ROOT'];
$stage = $root_true 	. "/html";
$root_parent = substr_replace($root_true, '', strrpos($root_dev, "/")); // to get the parent of the doc root dir, trim everything after from the rightmost slash

// if (basename($_SERVER['PHP_SELF']) == "MAKE-SITE.php") exit("This is canonical source code that is not meant to be run directly. Only copies of it should be run, because they derive environmental variables from their location. Run /tools/make-site.php instead.");

// special case the source and output directories for PS
$ps = false; $blog_str = ""; // defaults for the non-PS blogs, overridden in the ps.test case below
if ($_SERVER['HTTP_HOST'] == "ps.test") {
	$ps = true;
	$blog_str = "blog";
	$stage = $root_true 	. "/stage/blog";
	$root_dev = $root_true 		. "/blog";
	}

// and now make named constants, because these get used EVERYwhere
define('ROOT_TRUE', $root_true);
define('_ROOT', $root_true);
define('ROOT_DEV', $root_dev);
define('ROOT_PARENT', $root_parent);
define('STAGE', $stage);



/* ⚠️ THIS NEXT PART ONLY HAPPENS WHEN I BUILD WRITERLY, EPHEMERAL, DIVERSIONS, VERY IDIOSYNCRATIC AND WEIRD BUILD STEP!!!

The next ~50 lines manage the copies of the canonical PubSys build script and other shared resources in the other project folders. Only relevant if NOT the main (PS) blog and (importantly) *I* am the one running the build — this routine must ONLY run on MY Mac, because it’s basically just automation of a chore I need to do to keep my parents’ blogs running (plus my own).

As of June 2026, copying is EXPLICIT, not automatic: a routine build uses the committed local copies as-is, and just reports drift from canonical in the page header. Add ?sync to the URL to ingest the current canonical code (copies go to ALL three blogs, change-gated to avoid churn, and each blog gets a provenance stamp in incs/shared-code-version.txt recording the painscience commit it was synced from). Rationale: publishing a blog post shouldn't be hostage to absorbing untested painscience changes — stale is safer than fresh for these sites, and integration should happen deliberately, with attention to spare. */

$shared_drift = null; // null = not applicable (PS build, or not my Mac); array = local shared files that differ from canonical painscience (empty = in sync)
if (!$ps AND stripos(ROOT_DEV, "paul") !== false) {

	$canonical_dir = "$root_parent/painscience";
	$makesite_canonical_fn = "$canonical_dir/bin/make-ps-blog.php"; // the canonical code
	$makesite_target_fn = ROOT_DEV . "/make-site.php"; // this file in the current site folder (might be writerly, diversions, etc)

	$filenames = array ("PubSys.php", "util--core.php", "util--build.php", "util--errors.php", "css-errors.css", "content--tags.php", "table-sort.js", "table-sort-setup.js", "synonyms-pubsys-shorthands.txt", "synonyms-post-metadata.txt", "synonyms-image-options.txt", "easy-img.php","css-pubsys.css","lazyload-imgs.js", "PUBSYS-GUIDE.md"); // PUBSYS-GUIDE.md: user documentation, imported by the family blogs' CLAUDE.md files (Sept 2026)
	$target_dirs = array ("writerly", "diversions", "ephemeral");

	if (stripos($_SERVER['QUERY_STRING'] ?? '', 'sync') === false) {

		// NOT SYNCING (the routine case): no copying at all — just measure drift for the header notice, so I know untested canonical changes exist without silently ingesting them
		$shared_drift = array();
		if (@md5_file($makesite_canonical_fn) !== @md5_file($makesite_target_fn)) $shared_drift[] = 'make-site.php';
		foreach ($filenames as $filename)
			if (@md5_file("$canonical_dir/incs/{$filename}") !== @md5_file(ROOT_DEV . "/incs/{$filename}")) $shared_drift[] = $filename;
		}

	else {

		// SYNCING: special handling of THIS script first, because we may be copying over it!
		if (file_get_contents($makesite_canonical_fn) !== file_get_contents($makesite_target_fn)) {
			copy ($makesite_canonical_fn, "{$root_parent}/writerly/make-site.php");
			copy ($makesite_canonical_fn, "{$root_parent}/diversions/make-site.php");
			copy ($makesite_canonical_fn, "{$root_parent}/ephemeral/make-site.php");
			echo "build script updated from canonical — <a href='make-site.php?sync'>reload with ?sync</a> to copy the rest of the shared files and build. (A reload within ~2 seconds may execute the stale script via opcache; wait a beat.)";
			exit;
			}

		// provenance for the version stamp: which painscience commit is this sync from, and were there uncommitted changes to the shared files at the time?
		$gitdir = escapeshellarg($canonical_dir);
		$sync_sha = trim((string) @shell_exec("/usr/bin/git -C $gitdir rev-parse --short HEAD 2>/dev/null")) ?: 'unknown';
		$dirty_files = trim((string) @shell_exec("/usr/bin/git -C $gitdir status --porcelain -- bin/make-ps-blog.php " . implode(' ', array_map(fn ($f) => escapeshellarg("incs/$f"), $filenames)) . " 2>/dev/null"));
		$provenance = "Shared PubSys code synced from painscience @ {$sync_sha}" . ($dirty_files ? " (PLUS uncommitted changes to shared files)" : "") . " on " . date("Y-m-d H:i:s")
			. "\nFiles: make-site.php, " . implode(", ", $filenames)
			. "\nGenerated by the ?sync step in make-site.php (canonical: painscience/bin/make-ps-blog.php). Do not edit.\n";

		// copy changed files to all three blogs; change-gated so unchanged files keep their mtimes and the version stamp below stays honest
		foreach ($target_dirs as $target_dir) {
			$copied = 0;
			foreach ($filenames as $filename) {
				$from = "$canonical_dir/incs/{$filename}";
				$to = "{$root_parent}/$target_dir/incs/{$filename}";
				if (@md5_file($from) === @md5_file($to)) continue;
				if (copy ($from, $to)) $copied++;
				else echo "failed to copy {$from} to {$to}<br>";
				}
			// version-stamp the blog if it received changes, has no stamp yet, or was stamped under a different canonical commit (covers the make-site.php-only sync, which lands on the run before this one)
			$manifest_fn = "{$root_parent}/$target_dir/incs/shared-code-version.txt";
			$stamped_sha = preg_match('/@ (\S+)/', (string) @file_get_contents($manifest_fn), $m) ? $m[1] : '';
			if ($copied or $stamped_sha !== $sync_sha) file_put_contents($manifest_fn, $provenance);
			}

		$shared_drift = array(); // in sync by definition now; the header reports it
		}
	}

chdir (ROOT_DEV); // execute script as if running in a specific site folder

if ($ps) require_once($_SERVER['DOCUMENT_ROOT'] . "/incs/environment.php");

if (!$ps)  { // without the PS environment, we need at least Composer installed classes (chiefly php-markdown)
	require_once __DIR__ . '/incs/vendor/autoload.php';

	/* #timing_code — no-op stubs for this script's own tstart/tstop/tRenderInline instrumentation calls. On PS the real functions arrive via environment.php; the blogs get these stubs, mirroring the stub branch of painscience's performance-timing.php. Declared inline rather than required from the painscience folder because blog code may not reference ANYTHING outside its own folder — the family Macs have no /painscience/, and the old cross-folder require fataled their builds (July 2026). Declarations inside this if-block are registered at runtime only, so they can't collide with the real definitions on the PS path. */
	function tstart(string $label): void {}         // #timing_code
	function tstop(string $label): void {}          // #timing_code
	function tmark(string $label): void {}          // #timing_code
	function tastart(string $label): void {}        // #timing_code
	function tastop(string $label): void {}         // #timing_code
	function tflush(): void {}                      // #timing_code
	function tRenderInline(): string { return ''; } // #timing_code
	}


// load code libraries
set_include_path(".:$root_true/incs:$root_true/incs/content--library:$root_dev/guts:$root_dev/incs:$root_dev/guts/incs");
require_once('util--errors.php'); // error handlers + the psErr* reporting family; must load before util--core.php and PubSys.php so the reporters exist before any shared code can call them; PS already has it via env-runtime.php (require_once dedupes); before July 2026 the blogs had NO app-level error reporting at all, so shared-code error() calls fatalled
require_once('PubSys.php'); // functions for blogging, currently used by either Writerly or PainScience.com
require_once('content--tags.php'); // tag management functions
require_once('easy-img.php'); // a large function for handling image markup, so it gets its own file
require_once('util--core.php'); // many functions originally written for PainScience.com, but most are generic
require_once('util--build.php'); // the build log, changes report, and error tracking (buildMark & co.); PS already has it via env-runtime.php (require_once dedupes), but the blogs get it only from this line — it is NOT loaded by anything else outside the PS environment



// load site settings
$settings = get_settings(); extract($settings); // a selection of fairly straightforward sitewide variables, parsed from a simple text file

$md_syns = getArrFromFile("synonyms-post-metadata.txt",true);
		
// OK, that’s setup. Time for a little output.
// !!! HEADER OUTPUT ====================
// The build log is plain text: in a <pre> from a browser, on stdout from the CLI (bin/build.php, PainSci only); design note in util--build.php
$isFullBuild = strpos($_SERVER['QUERY_STRING'] ?? '', 'full') !== false;
buildHtmlHead('Make ' . ($ps ? 'BLOG' : trim("$sitename $blog_str"))); // the HTML wrapper, web mode only
buildMark(); // start the build-log plumbing: php-errors.log offset, current-doc tracker, changes collector, ?verbose flag
buildOut('Make ' . trim("$sitename $blog_str") . ' — ' . ($isFullBuild ? 'FULL build (post cache bypassed)' : 'quick build (add ?full to bypass the post cache)') . (buildVerbose() ? ', verbose' : '; add ?verbose for per-file detail'));
buildOut("Source: $root_dev");
buildOut('Output: ' . STAGE);
buildOut('Preview: ' . buildLink("$urlbase_stage/index.html", $urlbase_stage) . '   Live: ' . buildLink($urlbase_prod, $urlbase_prod), true);

if (is_array($shared_drift)) { // shared-code status for the non-PS blogs: provenance stamp + drift notice (see the sync block near the top of this script)
	$stamp_line = trim((string) (@file(ROOT_DEV . '/incs/shared-code-version.txt')[0] ?? 'No version stamp yet — run a ?sync build to create one.'));
	if ($shared_drift) buildOut('Shared-code drift: ' . count($shared_drift) . ' file(s) differ from canonical painscience (' . implode(', ', $shared_drift) . '). This build uses the local copies as-is; add ?sync to the build URL to ingest canonical and rebuild.');
	else buildOut("Shared code in sync with canonical painscience. {$stamp_line}");
}

buildLogStart(); // from here on the step-by-step log is captured; buildFinish() prints the summary first, then the log

// !!! PUBSYS BUILD ==========================
// PubSys reads post files into a big array ($posts) containing all post metadata and rendered content. The cache makes getPosts() fast on unchanged posts; add ?full to the URL to bypass the cache and re-parse everything. After loading posts, PubSys renders HTML files, then regenerates indexes, RSS, sitemap, and more.

tstart('getTags'); $tags = getTags(); tstop('getTags'); // #timing_code read tags into array
$psidsArr = array();
tstart('getPosts'); $posts = getPosts(); tstop('getPosts'); // #timing_code read posts into array
if (!$ps) makeTagUsageList($posts); // save every post tag to a list, to be ingested and tallied by the tag-engine (PainSci does this in it's own special way)
if ($ps) { tstart('auditPsids'); auditPsids(); tstop('auditPsids'); } // #timing_code aborts for missing psids & generates one (common with new posts)
tstart('makeWebVersions'); makeWebVersions(); tstop('makeWebVersions'); // #timing_code make all post files (web-ready HTML5); do this before updating tag database!
buildTrackDoc(''); // the steps below are site-wide, not building any one post, so stop the error panel blaming whatever post makeWebVersions() rendered last (until Sept 2026 it did, e.g. for makeTagIndexes() warnings); makeRSS() tracks its own posts, then clears the doc again
tstart('updateTags'); updateTags(); tstop('updateTags'); // #timing_code add tags found in posts, make tag index files
tstart('makeTagIndexes'); makeTagIndexes(); tstop('makeTagIndexes'); // #timing_code
tstart('remove_old_files'); remove_old_files($posts); tstop('remove_old_files'); // #timing_code
tstart('makeHomepage'); makeHomepage(); tstop('makeHomepage'); // #timing_code make the home page (index.html)
tstart('make_sy_indexes'); make_sy_indexes(); tstop('make_sy_indexes'); // #timing_code generate several custom post indexes
tstart('list_urls'); list_urls(); tstop('list_urls'); // #timing_code
tstart('makeRSS'); makeRSS(); tstop('makeRSS'); // #timing_code make the #RSS feeds (main and members-only)
if ($ps) { tstart('makePodcast'); makePodcast(); tstop('makePodcast'); } // #timing_code make the #podcast feed
tstart('makeSitemap'); makeSitemap(); tstop('makeSitemap'); // #timing_code make the sitemap
tstart('file_management'); file_management(); tstop('file_management'); // #timing_code make some aliases, symlinks, etc
tstart('makeMemberPostLists'); makeMemberPostLists(); tstop('makeMemberPostLists'); // #timing_code

buildFinish([buildPlural(count($posts), 'post')]); // the summary (results, warnings, the changes report, the error-log delta), then the captured step-by-step log (util--build.php)

echo tRenderInline(); // #timing_code — shows timing block inline when ?_timing=1 or PERFORMANCE_TIMING=on
buildHtmlFoot();
