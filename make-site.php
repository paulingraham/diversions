<?php #pubsys > build procedure

/* PubSys, © 2014–2023 by Paul Ingraham
A simple content management system focused on easy, simple data entry and management and robustly simple HTML output. PubSys reads text files that contain Markdown and other Markdown-like shorthands and turns them into website files for upload. PubSys runs in a browser locally. Most of the pubsys code is in 'pubsys-functions.php'.

Development notes (extremely out of date as of 2023):
craftdocs://open?blockId=EF2F7C6B-F8BB-46AA-A75E-281CC2F2CE3F&spaceId=bc7d854c-3e5b-a34e-4850-a6d2f31a1a59

Manual (also extremely out of date):
~/Sites/painscience/incs/pubsys-manual.pages

*/

error_reporting(0);
// error_reporting(E_ALL ^ E_NOTICE);

// ENVIRONMENT AND CONTEXT
// Where is PubSys running?
// the identifier `path is used whereever most key paths are defined, as a troubleshooting aid

$root_dev = $root_true = $_SERVER['DOCUMENT_ROOT'];
$stage = $root_true 	. "/html";
$root_parent = substr_replace($root_true, '', strrpos($root_dev, "/")); // to get the parent of the doc root dir, trim everything after from the rightmost slash

// if (basename($_SERVER['PHP_SELF']) == "MAKE-SITE.php") exit("This is canonical source code that is not meant to be run directly. Only copies of it should be run, because they derive environmental variables from their location. Run /tools/make-site.php instead.");

// special case the source and output directories for PS
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

// Copy the canonical PubSys build script and other resources to other blog folders as needed. It’s mostly only needed when I build Writerly. More exactly: only do it if this is NOT the main (PS) blog and (importantly) *I* am the one running the build. This routine must ONLY run on MY Mac, because it’s basically just automation of a chore I need to do to keep my parents blogs’ running (plus my own).
if (!$ps AND stripos(ROOT_DEV, "paul") !== false) {
	// special handling of THIS script, because we may be copying over it!
	$makesite_canonical_fn = "$root_parent/painscience/bin/make-ps-blog.php"; // the canonical code
	$makesite_target_fn = ROOT_DEV . "/make-site.php"; // this file in the current site filter (might be writerly, diversions, etc)

if (file_get_contents($makesite_canonical_fn) !== file_get_contents($makesite_target_fn)) {
		copy ($makesite_canonical_fn, "{$root_parent}/writerly/make-site.php");
		copy ($makesite_canonical_fn, "{$root_parent}/diversions/make-site.php");
		copy ($makesite_canonical_fn, "{$root_parent}/ephemeral/make-site.php");
		echo "build script updated, please to reload!";
		exit;
		}
	// now for all the other stuff — nothing fancy, no optimization, no diffing … just copy over the destinations
	$rsrc_fns = array ("pubsys-functions.php", "misc-functions.php", "tag-engine.php", "table-sort.js", "table-sort-setup.js", "synonyms-pubsys-shorthands.txt", "synonyms-post-metadata.txt", "synonyms-image-options.txt", "easy-img.php","css-pubsys.css","lazyload-imgs.js");
	$target_dirs = array ("writerly", "diversions", "ephemeral");
	$target_dirs = array ("writerly");
	foreach ($rsrc_fns as $rsrc_fn)
		foreach ($target_dirs as $target_dir)
			if (!copy ("{$root_parent}/painscience/incs/$rsrc_fn", "{$root_parent}/$target_dir/incs/$rsrc_fn"))
				echo "failed to copy $rsrc_fn :-(";			
	} 

chdir (ROOT_DEV); // execute script as if running in a specific site folder
define('CODE_BASE', 'pubsys');

if ($ps) {
	if (require_once($_SERVER['DOCUMENT_ROOT'] . "/incs/environment.php")) {
		$env = true; // #psmod: LOTS of extra code for PainSci!
		}
	}

$GLOBALS['pubsys'] = true;  #PHP8_pubsys, moved this from above the environment inclusion to after.  Why?  Because the main webdev environment now now initializes this variable as false (necessary for everything else)… but we want to proceed with it as true for this build script only.

if (!$ps)  { // without the PS environment, we need at least Composer installed classes (chiefly php-markdown)
	require_once __DIR__ . '/incs/vendor/autoload.php';
	}

// load code libraries
set_include_path(".:$root_true/incs:$root_true/incs/snippets:$root_dev/guts:$root_dev/incs:$root_dev/guts/incs");
require_once('pubsys-functions.php'); // functions for blogging, currently used by either Writerly or PainScience.com
require_once('tag-engine.php'); // tag management functions
require_once('easy-img.php'); // a large function for handling image markup, so it gets its own file
require_once('misc-functions.php'); // many functions originally written for PainScience.com, but most are generic

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
<p>You are running here: <code><?php echo $root_dev ?></code></p>
<p>You are putting HTML files here: <code><?php echo STAGE ?></code></p>

<p>View local preview: <a href="<?php echo $urlbase_stage ?>/index.html?rand=<?php echo mt_rand(1,10000) ?>" target="_blank"><?php echo $urlbase_stage ?></a><br>
View live site: <a href="<?php echo $urlbase_prod ?>" target="_blank"><?php echo $urlbase_prod ?></a></p>





<?php

// logToFile("/Users/paul/Sites/logs/builds.log.txt", "Make $sitename $blog_str, PHP version " . phpversion());

// !!! PUBSYS BUILD ==========================
// PubSys reads post files in the "posts" folder into a big array of posts, which contains ALL post metadata and content, aptly called $posts, plus a much lighter one, $index.  PubSys uses the posts array to render and save posts in the HTML folder, just the three most recent in a fast "prep mode", and all of them otherwise, plus a bunch of other stuff: homepage, RSS feed, sitemap, and more. It wraps up with a bit of file management.

if ($_qry == "prep") { // #prepMode a much simpler, faster build of render initiate #postPrep mode, whihc builds just a single post
	$prepMode = true;
	echo "<h2>Make Log: Prep Mode</h2><div style='font-size:.8em'>";
	$tags = getTags(); 			// read tags into array as usual
	$posts = getPosts(); 		// read posts into array as usual
	auditPsids();					// aborts for missing psids & generates one (common with new posts)
	makeWebVersions(); 			// render 3 most recent posts into web-ready HTML5 (it aborts early when $prepMode==true)
	echo "<p>Skipping many additional steps! Not making a variety of indexes, the RSS feeds, sitemap, member post lists, and more. Not rendering a mighty table of all historical posts (just a few for recent context). For a full build, <a href='http://ps.test/tools/make-ps-blog.php'>drop the 'prep' parameter.</a></p>";
	make_post_matrix (); 	// make table of all data about several recent posts
	echo "<br><h2>Raw post data for current post</h2>";
	printArrTable2(array_slice($posts, 0, 5)); 			// print the first 3 posts in the post array
	// the day after implementing these exec() calls, they stopped working…
		// exec("open -a Safari '$urlPreview'"); // this opens the preview in a new tab in Safari automagically, which is probably what I want the majority of the time (and of course the make-site output is available to switch to)
		// exec("echo '$urlProd' | pbcopy");
		// exec("open -a BBEdit ~/Desktop/current-post-text-version.txt"); //not sure if this is useful, because most renders I probably don’t want it opening up
	}
else {
	echo "<h2>Make Log: All Posts</h2><div style='font-size:.8em'>";
	$tags = getTags(); 			// read tags into array
	$psidsArr = array();
	$posts = getPosts(); 		// read posts into array
	if (!$ps) makeTagUsageList($posts); // save every post tag to a list, to be ingested and tallied by the tag-engine (PainSci does this in it’s own special way)
	if ($ps) auditPsids();					// aborts for missing psids & generates one (common with new posts)
	makeWebVersions(); 		// make all post files (web-ready HTML5); do this before updating tag database!
	updateTags();				// add tags found in posts, make tag index files; this canonicalizes the tag database file … possible this should actually be at the end?  or … just should not change $tags
	makeTagIndexes ();
	remove_old_files($posts);
	makeHomepage();			// make the home page (index.html)
	make_sy_indexes(); 		// generate several custom post indexes
	list_urls();
	makeRSS(); 					// make the #RSS feeds (main and members-only)
	if ($ps) makePodcast(); 		// make the #podcast feed
	makeSitemap ();			// make the sitemap
	file_management ();		// make some aliases, symlinks, etc
	makeMemberPostLists();
	make_post_matrix (); 	// make huge table of all post data

	echo "<br><h2>Raw post data</h2>"; printArrTable2($posts); 			// print array of post metadata /**/
	}

?>

</body>
</html>
