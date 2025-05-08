<?php // test

error_reporting(0);
// error_reporting(E_ALL ^ E_NOTICE);


/* PubSys [working title]
© 2014 by Paul Ingraham
A simple content management system focused on easy, simple data entry and management and robustly simple HTML output. PubSys reads text files that contain Markdown and other Markdown-like shorthands and turns them into website files for upload. PubSys runs in a browser locally. Most of the pubsys code is in 'pubsys-functions.php'. 

Development notes:
evernote:///view/523333/s4/8c1bc00a-92ea-45a2-aac4-ec4bc9d35d17/8c1bc00a-92ea-45a2-aac4-ec4bc9d35d17/

Manual:
~/Dropbox/shared/family folder/pubsys manual.pages

*/

// !!! ENVIRONMENT AND CONTEXT
// Where is PubSys running?
// the identifier `path is used whereever most key paths are defined, as a troubleshooting aid

$root_dev = $root_true = $_SERVER['DOCUMENT_ROOT'];
$stage = $root_true 	. "/html";
$root_parent = substr_replace($root_true, NULL, strrpos($root_dev, "/")); // to get the parent of the doc root dir, trim everything after from the rightmost slash

if (basename($_SERVER['PHP_SELF']) == "MAKE-SITE.php") exit("This is canonical source code that is not meant to be run directly. Only copies of it should be run, because they derive environmental variables from their location. Run /tools/make-site.php instead.");

// special case the source and output directories for PS
if ($_SERVER['HTTP_HOST'] == "ps.test") { 
	$ps = true;
	$blog_str = "blog";
	$stage = $root_true 	. "/stage/blog";
	$root_dev = $root_true 		. "/blog";
	}

// and now make named constants, because these get used EVERYwhere
define('ROOT_TRUE', $root_true);
define('ROOT_DEV', $root_dev);
define('ROOT_PARENT', $root_parent);
define('STAGE', $stage);
	
// copy the canonical make-site.php script and other resources to other blog folders as needed
// do it when I run a make of Writerly
// more exactly: do it if this is NOT the main (PS) blog and (importantly) *I* am the one running the build
// this routine must ONLY run on MY Mac, because it’s basically just automation of a chore I need to do to keep my parents blogs’ running (plus my own)
		
if (!$ps AND stripos(ROOT_DEV, "paul") !== false) {
	// special handling of THIS script, because we may be copying over it!
	$makesite_canonical_fn = "$root_parent/painscience/tools/MAKE-SITE.php"; // the canonical code
	$makesite_target_fn = ROOT_DEV . "/MAKE-SITE.php"; // this file in the current site filter (might be writerly, diversions, etc)
	// #2do perhaps I should also check for the existence of the target files
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

$GLOBALS['pubsys'] = true;

if (!$ps)  { // without the PS environment, we need at least Composer installed classes (chiefly php-markdown
	require_once __DIR__ . '/incs/vendor/autoload.php';
	}

// load code, function libraries
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
<title>Make <?php echo $sitename . " " . $blog_str; ?></title>
<link rel="stylesheet" type="text/css" 	href=	"/incs/css-tools.css" />
<link rel="stylesheet" type="text/css" 	href=	"/incs/css-matrix.css" />
<link rel="stylesheet" type="text/css" 	href=	"/incs/css-matrix-tools.css" />

<script src="//code.jquery.com/jquery-2.1.4.min.js"></script>
<script src="/incs/table-sort.js"></script>
<script src="/incs/table-sort-setup.js"></script>

</head></body>
<h1>Make <?php echo $sitename . " " . $blog_str; ?></h1>
<p>You are running here: <code><?php echo $root_dev ?></code></p>
<p>You are putting HTML files here: <code><?php echo STAGE ?></code></p>

<p>View local preview: <a href="<?php echo $urlbase_stage ?>/index.html?rand=<?php echo mt_rand(1,10000) ?>" target="_blank"><?php echo $urlbase_stage ?></a><br>
View live site: <a href="<?php echo $urlbase_prod ?>" target="_blank"><?php echo $urlbase_prod ?></a></p>

<h2>Make Log</h2>

<div style='font-size:.8em'>

<?php 

// logToFile("/Users/paul/Dropbox/Sites/logs/builds.log.txt", "Make $sitename $blog_str, PHP version " . phpversion());

// !!! PUBSYS BUILD ==========================
// PubSys reads post files in the "posts" folder into a big array of posts, which contains ALL post metadata and content, aptly called $posts, plus a much lighter one, $index.  PubSys uses the array and or index to render and save posts in the HTML folder, and then some other important files like the homepage, RSS feed, and sitemap. It wraps up with a bit of file management.

$tags = getTags(); 		// read tags into array
	// printArr($tags); exit;
$posts = getPosts(); 		// read posts into array
	// printArr($tags); exit;
updateTags();				// add tags found in posts, make tag index files
makePostFiles(); 			// make all post files (web-ready HTML5)
makeHomepage();			// make the home page (index.html)
make_sy_indexes(); 		// generate several custom post indexes
makeRSS(); 					// make the RSS feeds (main and members-only)
makeSitemap ();			// make the sitemap
file_management ();		// make some aliases, symlinks, etc
makePremiumPostList();
make_post_matrix (); 	// make large table of all post data


echo "<br><h2>Raw post data</h2>";
printArr($posts); 			// print array of post metadata /**/
?>

</body>
</html>
