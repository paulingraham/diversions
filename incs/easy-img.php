<?php

# Install PSR-0-compatible class autoloader
spl_autoload_register(function($class){
	require preg_replace('{\\\\|_(?!.*\\\\)}', DIRECTORY_SEPARATOR, ltrim($class, '\\')).'.php';
});
# Get Markdown class
use \Michelf\Markdown;

function easyImg($user_input = false) {

$args = parseSloppyData($user_input);

$img_opt_syns = getArrFromFile("synonyms-image-options.txt",true);

// Initialize variables
$alt = '';
$caption = '';
$foundFile = false;
$position = '';
$containerType = "div";

/* FIND THE FILE */
/* First important job is to see if we can find a file! If there's no file, there's no much point in processing the rest of the user data. The punchline will be to say that $foundFile is either true or false, and to set the value of $src if we found a file. */

/* Get the image data. We assume that a filename or citekey is in the first slot. */
$filename = trim($args[0]);

/* check for citekey and make a filename from it if found (assumption: any image I try to get using a citekey will have a filename based on the citekey; in general this is the case only for book covers images in the books subdir) */
	
$path = STAGE . "/imgs/$filename"; // absolute `path to the images
$src = "imgs/$filename"; // IMGS is set in location.php and is the absolute host file system path to the filename

if (!file_exists($path)) return "  !!! IMG FILE '$path' NOT FOUND !!! ";

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


foreach ($args as $arg) {
	if ($n++ < 1) continue; // skip the 1st arg (it's the image filename)

	if (in_array($arg, $img_opt_syns["shadow"]))
		$imgClasses .= " ds";

	if (in_array($arg, $img_opt_syns["centre"]))
		$position .= "centre";

	if ($arg == "inline") $containerType = "span"; // the default container type is a div; change it to a span only if the image is used inline
	
	// if the argument is not a compound and a long string, assume it’s a caption
	if (strpos($arg, "|") === false and strlen($arg) > 15)
		{ $caption = $arg; continue;}

	// if it’s not a compound argument and it’s very short, skip it (already dealt with above)
	if (strpos($arg, "|") === false and strlen($arg) < 15) continue;

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