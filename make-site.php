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

	$filenames = array ("PubSys.php", "util--core.php", "util--build.php", "content--tags.php", "table-sort.js", "table-sort-setup.js", "synonyms-pubsys-shorthands.txt", "synonyms-post-metadata.txt", "synonyms-image-options.txt", "easy-img.php","css-pubsys.css","lazyload-imgs.js");
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
	require_once "$root_parent/painscience/incs/performance-timing.php"; // #timing_code — stubs when off; no-ops for non-PS blogs that don't have their own .env timing flag
	}


// load code libraries
set_include_path(".:$root_true/incs:$root_true/incs/content--library:$root_dev/guts:$root_dev/incs:$root_dev/guts/incs");
require_once('PubSys.php'); // functions for blogging, currently used by either Writerly or PainScience.com
require_once('content--tags.php'); // tag management functions
require_once('easy-img.php'); // a large function for handling image markup, so it gets its own file
require_once('util--core.php'); // many functions originally written for PainScience.com, but most are generic
require_once('util--build.php'); // build-error tracking/reporting (buildErrorsMark & co.); PS already has it via env-runtime.php (require_once dedupes), but the blogs get it only from this line — it is NOT loaded by anything else outside the PS environment



// load site settings
$settings = get_settings(); extract($settings); // a selection of fairly straightforward sitewide variables, parsed from a simple text file

$md_syns = getArrFromFile("synonyms-post-metadata.txt",true);
		
// OK, that’s setup. Time for a little output.
// !!! HEADER OUTPUT ====================
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Make <?php echo $ps ? "BLOG" : "{$sitename} {$blog_str}"; ?></title>
<link rel="stylesheet" type="text/css" 	href=	"/incs/css-tools.css" />
<link rel="stylesheet" type="text/css" 	href=	"/incs/css-matrix.css" />
<link rel="stylesheet" type="text/css" 	href=	"/incs/css-matrix-tools.css" />

<script src="//code.jquery.com/jquery-2.1.4.min.js"></script>
<script src="/incs/table-sort.js"></script>
<script src="/incs/table-sort-setup.js"></script>
<script>
function copy(obj) { /* primary #copy function, invoked by onclick of an inline element with HTML contents eg <span class='copyable' onClick="copy(this)"> */
	var text = obj.textContent; // get the contents of the tapped object, assumed to be a simple string
	var el = document.createElement('textarea'); // create a textarea element that we'll put the text in
	el.value = text; // get the text from the copies object, while replacing the sciccors icon; regex because simple replace does not gracefully handle delims
	el.setAttribute('readonly', ''); // make it a readonly textarea 
	el.style.position = 'absolute';
	el.style.left = '-9999px'; // set a position waaaay outside the window
	document.body.appendChild(el); // add textarea to the DOM
	el.select(); // select textarea contents
	document.execCommand('copy'); // copy textarea contents
	document.body.removeChild(el); // remove textarea from the DOM
	tellUser('COPIED!'); // confirm the copy: briefly show a blue box with the confirmation message 
	console.log('User copied to clipboard: "' + text + '"');
	};
</script>
</head></body>
<h1>Make <?php echo $sitename . " " . $blog_str; ?></h1>
<?php $isFullBuild = strpos($_SERVER['QUERY_STRING'] ?? '', 'full') !== false; ?>
<p><?php if ($isFullBuild): ?>This is a <strong>full</strong> build (<a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) ?>">run a quick build</a>.)<?php else: ?>This is a <strong>quick</strong> build (<a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) ?>?full">run a full build</a>.)<?php endif; ?></p>

<p>You are here: <code><?php echo $root_dev ?></code></p>
<p>Rendered posts (HTML) will go here: <code><?php echo STAGE ?></code></p>

<p>View local preview: <a href="<?php echo $urlbase_stage ?>/index.html?rand=<?php echo mt_rand(1,10000) ?>" target="_blank"><?php echo $urlbase_stage ?></a><br>
View live site: <a href="<?php echo $urlbase_prod ?>" target="_blank"><?php echo $urlbase_prod ?></a></p>

<?php if (is_array($shared_drift)) { // shared-code status for the non-PS blogs: provenance stamp + drift notice (see the sync block near the top of this script)
	$stamp_line = trim((string) (@file(ROOT_DEV . '/incs/shared-code-version.txt')[0] ?? 'No version stamp yet — run a ?sync build to create one.'));
	if ($shared_drift) {
		echo "<p style='border:2px solid #c66; padding:.5em'>⚠️ <strong>Shared-code drift:</strong> " . count($shared_drift) . " file(s) differ from canonical painscience (" . implode(", ", $shared_drift) . "). This build uses the local copies as-is — <a href='make-site.php?sync'>sync &amp; rebuild</a> to ingest canonical.</p>";
		}
	else echo "<p style='opacity:.6'>Shared code in sync with canonical painscience. {$stamp_line}</p>";
	} ?>





<?php

// logToFile("/Users/paul/Sites/logs/builds.log.txt", "Make $sitename $blog_str, PHP version " . phpversion());

// !!! PUBSYS BUILD ==========================
// PubSys reads post files into a big array ($posts) containing all post metadata and rendered content. The cache makes getPosts() fast on unchanged posts; add ?full to the URL to bypass the cache and re-parse everything. After loading posts, PubSys renders HTML files, then regenerates indexes, RSS, sitemap, and more.

buildErrorsMark(); // start tracking php-errors.log so buildErrorsReport() can summarize errors generated by this build, and register the fatal-culprit shutdown reporter (see util--build.php); PubSys calls buildTrackDoc() as it processes each post

echo "<h2>Make Log: All Posts</h2><div style='font-size:.8em'>";
tstart('getTags'); $tags = getTags(); tstop('getTags'); // #timing_code read tags into array
$psidsArr = array();
tstart('getPosts'); $posts = getPosts(); tstop('getPosts'); // #timing_code read posts into array
if (!$ps) makeTagUsageList($posts); // save every post tag to a list, to be ingested and tallied by the tag-engine (PainSci does this in it's own special way)
if ($ps) { tstart('auditPsids'); auditPsids(); tstop('auditPsids'); } // #timing_code aborts for missing psids & generates one (common with new posts)
tstart('makeWebVersions'); makeWebVersions(); tstop('makeWebVersions'); // #timing_code make all post files (web-ready HTML5); do this before updating tag database!
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
tstart('make_post_matrix'); make_post_matrix(); tstop('make_post_matrix'); // #timing_code make huge table of all post data

echo "<br><h2>Raw post data</h2>";
//	printArrTable2(array_slice($posts, 0, 50)); 			// print the first 50 posts in the post array
//	printArrTable2($posts); 			// print entire array of post metadata /**/

buildErrorsReport(); // journal a deduped summary of PHP errors logged during this build (both modes)

echo tRenderInline(); // #timing_code — shows timing block inline when ?_timing=1 or PERFORMANCE_TIMING=on

?>

</body>
</html>
