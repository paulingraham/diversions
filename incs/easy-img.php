<?php

# Install PSR-0-compatible class autoloader
spl_autoload_register(function($class){
	require preg_replace('{\\\\|_(?!.*\\\\)}', DIRECTORY_SEPARATOR, ltrim($class, '\\')).'.php';
});
# Get Markdown class
use \Michelf\Markdown;

function easyImg($user_input = false) {

if ($GLOBALS['ps']) return; // easyImg() is a no-op for PainSci. The easyImg delimiters for an <<image>> are not only not used on the PainSci blog, they are reserved for the much more important purpose of denoting xrefs e.g. <<smith etal>>. 

$args = parseSloppyData($user_input);

$img_opt_syns = getArrFromFile("synonyms-image-options.txt",true);

// Initialize variables
$alt = '';
$caption = '';
$foundFile = false;
$position = '';
$containerType = "div";
$imgClasses = '';
$containerClasses = '';

/* FIND THE FILE */
/* First important job is to see if we can find a file! If there's no file, there's no much point in processing the rest of the user data. The punchline will be to say that $foundFile is either true or false, and to set the value of $src if we found a file. */

/* Get the image data. We assume that a filename or citekey is in the first slot. */
$filename = trim($args[0]);

/* check for citekey and make a filename from it if found (assumption: any image I try to get using a citekey will have a filename based on the citekey; in general this is the case only for book covers images in the books subdir) */

global $settings; extract($settings);
$path = STAGE . "/{$imgs_dir}/$filename"; // absolute `path to the images
$src = "{$imgs_dir}/$filename"; // IMGS is set in location.php and is the absolute host file system path to the filename

if (!file_exists($path)) { // missing image: failure-severity by the visible-gibberish rule (the placeholder below ships to readers), so builds abort rather than publish it — this was the last unreported placeholder path after img() got its reporter in July 2026 (psErr* audit item 7); blog-safe: make-ps-blog.php loads util--errors.php before any shared code
	psErrFail("easy-img: image file '$filename' not found — the '!!! IMG FILE NOT FOUND !!!' placeholder would ship");
	return "  !!! IMG FILE '$filename' NOT FOUND !!! "; // report just the filename, not $path: the full path churns between machines (this runs on two Macs with different home dirs), producing spurious rendered-output diffs on every cross-machine build
	}

// So if we didn't find anything, that's the end of it. But if we did find a file, well then by golly we just keep on truckin' ...

/* GET SOME IMAGE META DATA */
		
$imgFileSizeB = filesize($path);
$imgFileSizeKB = round($imgFileSizeB/1000,1);

$imagedata = @getimagesize($path); // get data about the image
$w = $imagedata[0];
$h = $imagedata[1];

/* INTERPRET ARGUMENTS */
/* Now that we know there's a file, let's find out what (if anything) the user has said about it. */

// look for arguments that are just simple flags

$synonyms = array("right","ri","r","rside");
	if (array_intersect($synonyms,$args))
		$position = "right";

$synonyms = array("left","le","l","lside");
	if (array_intersect($synonyms,$args))
		$position = "left";

/* Look for compound arguments of the form “arg:data” */


$n = 0;
foreach ($args as $arg) {
	if ($n++ < 1) continue; // skip the 1st arg (it's the image filename)

	if (in_array($arg, $img_opt_syns["shadow"]))
		$imgClasses .= " ds";

	if (in_array($arg, $img_opt_syns["centre"]))
		$position .= "centre";

	if (substr($arg,-2) == 'px') { // if an arbitrary pixel width is declared ("300px"), the actual image width will be replaced, and browser image scaling is used to render the image at that width (generally great quality these days)
		$resizeToWidth = str_replace("px", '', $arg);
		if ($w > 0) $h = (int) round($h * ($resizeToWidth / $w)); // scale the height to preserve the aspect ratio (same fix as img() in content--images.php); must happen before $w is overwritten ($w > 0 guards against a failed getimagesize)
		$w = $resizeToWidth;
//		exit("{$w}px");
	}
	
	if ($arg == "inline") $containerType = "span"; // the default container type is a div; change it to a span only if the image is used inline
	
	// any other plain (non-compound) argument is the caption; until Sept 2026 only plain arguments of 15+ characters counted, and shorter captions ("Uncle Bill") were silently dropped
	if (strpos($arg, "|") === false) {
		$option_words = array_merge($img_opt_syns["right"], $img_opt_syns["left"], $img_opt_syns["shadow"], $img_opt_syns["centre"], array("inline")); // plain arguments already handled above
		if ($arg !== '' and !in_array($arg, $option_words) and substr($arg,-2) != 'px') $caption = $arg;
		continue;
	}

	// extract the arg
	$halves = explode("|", $arg, 2);
	$arg = strtolower($halves[0]);  // convert to lowercase to reduce case confusion
	$data = $halves[1];
	
	$synonyms = array('c','cap','caption');
	if (in_array($arg,$synonyms)) $caption = $data;

	if ($arg == "alt") $alt = $data;
	
	$synonyms = array('css','style','styles');
	if (in_array($arg,$synonyms)) $arbitraryCSS = $data;
	
	} // end the loop through the array of user data

	
/* Now that we're done stepping through all the pieces of user data and identifying key variables, we need to set defaults for important variables if no user-defined values were supplied. Most of these are redundant, but I'm being thorough and clear! */

if (!$position) $position = "right"; // default position is right floated...
if ($w > 380) $position = "centre"; // ...or centred for images wider than 380px, regardless of request
$containerClasses .= "$position";

if ($caption) {
	$caption = Markdown::defaultTransform($caption);
	$caption = str_replace("<p>", "", $caption);
	$caption = str_replace("</p>", "", $caption);
	$caption = trim($caption);
	$caption = "<{$containerType} class='caption'>{$caption}</{$containerType}>";
}



/* BUILD MARKUP */
$img = <<<IMG
<{$containerType} class='img_container {$containerClasses}' style='position:relative;width:{$w}px;'>
<img src='{$src}' width='{$w}' height='{$h}' class='{$imgClasses}' alt='{$alt}' loading='lazy'>{$caption}</{$containerType}>
IMG;

if ($img) return $img;

}

?>