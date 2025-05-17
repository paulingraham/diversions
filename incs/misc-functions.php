<?php

/* VERY SMALL FUNCTIONS 
A bunch of 1-3 liners that do miscalleneous handy things.  Useful to group them together for reference. */

function readingtime($word_count) {return roundDown($word_count/220); /* word count divided by average slow-ish adult reading speed, then rounded down */ }

function makeTd($value) {return "\t<td>{$value}\n";}

/* captures the rendered contents of a PHP file as data */
function includeAsData ($file) { ob_start(); include($file); return ob_get_clean(); }

function implodeAssocArr ($arr, $glue = ", ") { // implodes a simple associate array to a prettified string
	foreach($arr as $key=>$val) {
		if ($val===null) $val = 'null';
		if (is_string($val) and $val==='') $val = 'empty str';
		if (is_bool($val) and $val==='') $val = printBool($val);
		$key = str_replace(':', NULL, $key);
		$arr_tmp[] = $key . "=" . $val;
		}
	return implode($glue, $arr_tmp);
}

function reScale($num, $oldmax, $newmax) {return floor(($num/$oldmax)*$newmax);}

/** returns @string: reduces runs of any whitespace chars to 1 plain space */
function compressSpaces ($str) {return preg_replace("/\s\s+/u", " ", $str);}

/** returns @boolean: detects associative array */
function isAssoc($array) { return (bool)count(array_filter(array_keys($array), 'is_string')); }

/** returns @boolean: checks for a string in another string */
function inStr($needle, $haystack) {
	/* this is a more useable boolean-only function to replace the awkard strpos(haystack,needle)!==false */
	if ($haystack !== null and mb_strpos($haystack, $needle) !== false) return true; else return false; #PHP8_prep_null_params
	}

function inRange($num, $min, $max) {
	if ($num >= $min and $num <= $max) return true; else return false; }

/** returns @number, word count: formats rounded-down word count of a string  */
function wordCounter($str) { return number_format(roundDown(count(explode(" ",$str)))); }

/** returns @boolean: true if a file is fresh if it’s been modded within x days */
function fileFresh($fn,$days_leeway = 20) {  if ((time()-filemtime($fn))/60/60/24 < $days_leeway) return true; }

/* DATES & TIMES ===================================== 
Function names start with the type of value they return, so tstamp_whatever returns a tstamp, weeks_whatever returns a number of weeks, and date_whatever returns a formatted DATE (like the date function).

There are a couple of other date utility functions: parseDate (for converting 2018-07-03 to a timestamp), and flexibleDate, which returns differently formatted dates depending on how long ago they are.

*/

/** returns @timestamp: gets date from x days ago; reverse of daysSinceTstamp */
function tstampXdaysAgo ($days) { return time()-60*60*24*$days;}

/** returns @number, days: gets # of days since a timestamp; reverse of tstampXdaysAgo */
function minsSinceTstamp ($tstamp) { 		return floor((time()-$tstamp)/60); }

/** returns @number, days: gets # of days since a timestamp; reverse of tstampXdaysAgo */
function hoursSinceTstamp ($tstamp) { 	return floor((time()-$tstamp)/60/60); }

/** returns @number, days: gets # of days since a timestamp; reverse of tstampXdaysAgo */
function daysSinceTstamp ($tstamp) { 		return floor((time()-$tstamp)/60/60/24); }

/** returns @number, days: gets # of days since a timestamp; reverse of tstampXdaysAgo */
function weeksSinceTstamp ($tstamp) { 	return floor((time()-$tstamp)/60/60/24/7); }

/** returns @number, days: gets # of days since a timestamp; reverse of tstampXdaysAgo */
function monthsSinceTstamp ($tstamp) { 	return floor((time()-$tstamp)/60/60/24/12); }

/** returns @number, days: gets # of days since a timestamp; reverse of tstampXdaysAgo */
function yearsSinceTstamp ($tstamp) { 	return floor((time()-$tstamp)/60/60/24/365); }

/** returns @string, date: formats date for standard readability and sorting (eg 2013-07-27; I use this format very widely)  */
function dateStd ($tstamp=null) {$tstamp = $tstamp ?: time(); return date("Y-m-d", $tstamp); }

function time12 ($tstamp=null) {$tstamp = $tstamp ?: time();  return date("h:i:s A", $tstamp); }

function time24 ($tstamp=null) {$tstamp = $tstamp ?: time(); return date("H:i:s", $tstamp); }

/** returns @string, date: formats most compact readable mon-day-year date (eg Jul 27, 13) */
function dateTime24 ($tstamp=null) {$tstamp = $tstamp ?: time(); return date('Y-m-d @ H:i:s', $tstamp); }

/** returns @string, date: formats default user-facing short date (eg Jul 27, 13) */
function dateClear ($timestamp) {return str_replace(" ", "&nbsp;", date('M j, y', $timestamp));}

/** returns @string, date: formats date leading with day of week, good for emhasizing currency (eg Tuesday, April 14th) */
function dateDay () {return date('l, F jS', time());}

/** returns @string, date: formats MOST compact possible month-year date (eg Jul '13) */
function dateClearer ($timestamp) {return str_replace(" ", "&nbsp;", date('M \'y', $timestamp));}

/* returns @float, days: number of days expired this week so far, two decimal places (eg Tue at noon is day 2.50) */
function dayOfWeekPrecise() { 
	$day = date("w", time())+1; // day number of week, Sun = 0, as reckoned by PHP, add one to match my bookkeeping
	$percent_of_day = date("G", time())/24; // percentage of the current day that has passed
	return round($day-(1-$percent_of_day),2);
	}

/** returns @string: replaces common Unicode punctuation replaced with ASCII */
function asciifyPunctuation ($string) {
	$find = explode("\t","’|‘|“|”|—|–|…");
	$replace = explode("|", "|'|'|\"|\"|--|-|…");
	return str_replace($find,$replace,$string); }

/** returns @array: merges two indexed arrays A,B,C+1,2,3=A,1,B,2,C,3  */
function arrayInterleave ($arr1, $arr2) {
$x=$y=0;
	if (count($arr2) == 0 and count($arr1) > 0) return $arr1;
	if (count($arr1) == 0 and count($arr2) > 0) return $arr2;
	foreach ($arr1 as $a1) {$arr1new[++$x-1] = $a1; $x++;}
	foreach ($arr2 as $a2) {$arr2new[++$y] = $a2; $y++;}
	// printArr($arr1x); printArr($arr2y);
	$merged = $arr1new+$arr2new;
/*
echo "arr1new:<br>"; printArr($arr1new);
echo "arr2new:<br>"; printArr($arr2new);
echo "merged:<br>"; printArr($merged); */

	ksort($merged);
	return $merged;
	}

/** returns @string: replaces common Unicode punctuation replaced with NUMERIC (not named) html entities (for RSS/XML) */
function numericEntities ($str) { /* Maddeningly, RSS feeds require titles with numeric html entities, but PHP's entity functions (htmlentities and htmlspecialchars) only convert to NAMED html entities. PHP.net user-contributed functions are all advanced general purpose functions when I just wanted a very straightforward damned list of common replacements!  So, here ...*/
	$find = explode(" ","& “ ” ‘ ’ — … ★ ☆");
	$change = explode(" ",	"&#38; &#8220; &#8221; &#8216; &#8217; &#8212; &#8230; &#9733; &#9734;");
	return str_replace($find, $change, $str); }

/** returns @string, url: gets the first URL from given HTML or Markdown */
function extract1stUrl($content) {
	if (preg_match("/.*?<a href\s*=\s*['\"](http[s]{0,1}:.*?)['\"]/", $content, $matches)) return $matches[1];
	// detect Markdown url [link text](url)
	elseif (preg_match("/.*?\[.{1,50}\]\((http[s]{0,1}:.*?)\).*/i", $content, $matches)) return $matches[1];
	else return null;
	}
	
/** returns @file: saves a string to a file */
function saveAs($str, $fn, $mode='w') {
// sleep(1);
	if ($str == NULL) $str = '';
	$fp = @fopen($fn, $mode);
	if (!$fp) {
		echo "<br><strong>warning</strong>: could not write file '$fn'<br>";	
		return;
		}
	// default w mode: open for writing only; truncate file to zero; create if needed
	fwrite($fp, $str);
	fclose($fp);
	}

/** returns @file: checks string against file, saves only if different */
function checkSave($output_str, $file, $label="", $jrnl=false) {
	if ($label !== "") $label .= " ";
	if (fileExistsNoChange($output_str, $file)) {
		if ($jrnl) journal("{$label}file has not changed, <em>not</em> writing file: $file", 2);
		return;
		}
	else {
		if ($jrnl) journal("<strong>{$label}file has changed</strong>, writing file: $file", 2, true);
		saveAs($output_str, $file);
		}
	}
	
/** returns @boolean: checks string against file, returns true if a file exists AND is the same as a str (the condition for a pointless save) */
function fileExistsNoChange($str, $file) {
	if (file_exists($file) and $str == @file_get_contents($file)) return true; // use @ to suppress error if the file doesn't exist
	}

/** returns @boolean: true if a search term matches EITHER a specific key in an array OR a value associated with that key */
function isKeyOrVal ($key, $search, $array) {
	if ($key == $search or in_array($search, $array[$key])) return true;
	else return false;
	}

/*  LARGER FUNCTIONS */
	
/** returns @string, html url: formats given URL for tidy and pretty display */
function prettifyURL($url) {
	extract(parseURL($url));
	if ($domain_main == "PainScience") $url_path = str_replace(".php", "", $url_path);
	if ($domain_sub === "www" or !$domain_sub) // if it was there to begin with, or if I added www
		$domain_sub = "<span class='subdomain'>www</span>"; // add some markup
	if ($url_path_abbr) $url_path = str_replace("…", "<span style='color:#666'>&nbsp;—SNIP—&nbsp;<XXXspan>", $url_path_abbr);
	$url_path = rtrim($url_path,"/");
	$url_path = str_replace("/", "<span style='margin:0 .2em'>/</span>", $url_path);
	$url_path = str_replace("XXX", "/", $url_path);
	$url_str = "<span class=\"pretty_url\">{$domain_sub}.<strong>$domain_main.$domain_tld</strong>$url_path</span>";
	return $url_str;
}

/** returns @array, url parts: returns an array of the parts of given URL, mostly for input to prettifyURL() */
function parseURL($url) {
/* testing url
	 $url = "http://lol.cats.painscience.com/articles/yknow/whatevs/reality-checks/epsom-salts.php"; */
	 
	preg_match("@http[s]*://(.*?)(/.*|$)@", $url, $match); // match the domain and any path
	$domain_full = $match[1];
	$url_path = $match[2];
	$domain_parts = array_reverse(explode(".", $domain_full)); // break the domain into an array split on periods; 
	$domain_tld = $domain_parts[0]; // after reverse sorting, we can safely assume the TLD (.com, .org, etc) is the 1st part
	$domain_main = $domain_parts[1]; // … and the main domain is 2nd

	// subdomains are tricky: there might be one or two of them … I had never actually ever noticed a 2-part subdomain until I was trying to automatically markup a bunch of domains :-) … the third one is overkill (pretty sure they don't exist), but included for completeness
	if ($domain_parts[2]) $domain_sub = $domain_parts[2];
	if ($domain_parts[3]) $domain_sub = $domain_parts[3] . "." . $domain_sub; // add the next subdomain, if it exists
	if ($domain_parts[4]) $domain_sub = $domain_parts[4] . "." . $domain_sub; // add the next subdomain, if it exists

	// now for the path: long paths are common and often inconvenient for display, so this function returns an abbreviated version of any path longer than 50 chars; the snipped section is marked by an ellipsis
	if (strlen($url_path) > 40):
		// #2do: this could be smarter, and try to split the path into chunkier chunks
		$url_path_slice = substr($url_path, 20, -10);
		$url_path_abbr = str_replace($url_path_slice, "…", $url_path);
		$url_path_abbr = str_replace("…-", "…", $url_path_abbr);
	endif;
	
	$domain_final['domain_full'] = $domain_full;
	$domain_final['url_path'] = $url_path;	
	$domain_final['url_path_abbr'] = $url_path_abbr;
	$domain_final['domain_sub'] = $domain_sub;
	$domain_final['domain_main'] = $domain_main;
	$domain_final['domain_tld'] = $domain_tld;
	
	return $domain_final;
}

/** returns @number, word count: rounds down word count in a way that's right for word counts, or other specified multiple */
function roundDown($theNumber, $nearest = false) {
	if ($theNumber == '') $theNumber = 0;
   if ($nearest > 0) { // if a “nearest” multiple to round down to (i.e. the nearest multiple of 25) is specified, round down to that
	return floor($theNumber/$nearest)*$nearest;
    } else { /* round down according to an algorithm suitable for rounding down word counts */
		switch ($theNumber) {
		case ($theNumber >= 100000):
			$theNumber = floor($theNumber/2500)*2500;
			break;
		case ($theNumber >= 10000):
			$theNumber = floor($theNumber/1000)*1000;
			break;
		case ($theNumber >= 5000):
			$theNumber = floor($theNumber/500)*500;
			break;
		case ($theNumber >= 2500):
			$theNumber = floor($theNumber/250)*250;
			break;
		case ($theNumber >= 1000):
			$theNumber = floor($theNumber/100)*100;
			break;
		case ($theNumber >= 500):
			$theNumber = floor($theNumber/50)*50;
			break;
		case ($theNumber >= 250):
			$theNumber = floor($theNumber/25)*25;
			break;
		case ($theNumber >= 100):
			$theNumber = floor($theNumber/10)*10;
			break;
		case ($theNumber >= 10):
			$theNumber = floor($theNumber/5)*5;
			break; 
		case ($theNumber >= 1):
			$theNumber = intval($theNumber);
			break; 
		}
    }
    return $theNumber;
}

/** returns @array: gets an array of trimmed words from given string delimited by spaces (default) or other delimiter, an upgrade of explode() */
function arraynge ($str, $delim=" ") { // it’s simple and doesn’t do much more than php’s explode, but it’s handy that it trims, filters, and defaults to spaces
		if (!isset($str) or trim($str) == "") return array(); // if there's no data to arraynge, return an empty array
		$array = explode($delim, $str);
		$array = array_map("trim", $array);
		return array_filter($array);
	}

/** returns @string: converts numerals <101 to words */
function wordifyNumbers($number) { 
	$number = ceil($number);
	if ($number == "1" ) return "one";	if ($number == "2" ) return "two";	if ($number == "3" ) return "three";	if ($number == "4" ) return "four";	if ($number == "5" ) return "five";	if ($number == "6" ) return "six";	if ($number == "7" ) return "seven";	if ($number == "8" ) return "eight";	if ($number == "9" ) return "nine";	if ($number == "10" ) return "ten";	if ($number == "11" ) return "eleven";	if ($number == "12" ) return "twelve";	if ($number == "13" ) return "thirteen";	if ($number == "14" ) return "fourteen";	if ($number == "15" ) return "fifteen";	if ($number == "16" ) return "sixteen";	if ($number == "17" ) return "seventeen";	if ($number == "18" ) return "eighteen";	if ($number == "19" ) return "nineteen";	if ($number == "20" ) return "twenty";	if ($number == "21" ) return "twenty-one";	if ($number == "22" ) return "twenty-two";	if ($number == "23" ) return "twenty-three";	if ($number == "24" ) return "twenty-four";	if ($number == "25" ) return "twenty-five";	if ($number == "26" ) return "twenty-six";	if ($number == "27" ) return "twenty-seven";	if ($number == "28" ) return "twenty-eight";	if ($number == "29" ) return "twenty-nine";	if ($number == "30" ) return "thirty";	if ($number == "50" ) return "fifty";	if ($number == "51" ) return "fifty-one";	if ($number == "52" ) return "fifty-two";	if ($number == "53" ) return "fifty-three";	if ($number == "54" ) return "fifty-four";	if ($number == "55" ) return "fifty-five";	if ($number == "56" ) return "fifty-six";	if ($number == "57" ) return "fifty-seven";	if ($number == "58" ) return "fifty-eight";	if ($number == "59" ) return "fifty-nine";	if ($number == "60" ) return "sixty";	if ($number == "61" ) return "sixty-one";	if ($number == "62" ) return "sixty-two";	if ($number == "63" ) return "sixty-three";	if ($number == "64" ) return "sixty-four";	if ($number == "65" ) return "sixty-five";	if ($number == "66" ) return "sixty-six";	if ($number == "67" ) return "sixty-seven";	if ($number == "68" ) return "sixty-eight";	if ($number == "69" ) return "sixty-nine";	if ($number == "70" ) return "seventy";	if ($number == "71" ) return "seventy-one";	if ($number == "72" ) return "seventy-two";	if ($number == "73" ) return "seventy-three";	if ($number == "74" ) return "seventy-four";	if ($number == "75" ) return "seventy-five";	if ($number == "76" ) return "seventy-six";	if ($number == "77" ) return "seventy-seven";	if ($number == "78" ) return "seventy-eight";	if ($number == "79" ) return "seventy-nine";	if ($number == "80" ) return "eighty";	if ($number == "81" ) return "eighty-one";	if ($number == "82" ) return "eighty-two";	if ($number == "83" ) return "eighty-three";	if ($number == "84" ) return "eighty-four";	if ($number == "85" ) return "eighty-five";	if ($number == "86" ) return "eighty-six";	if ($number == "87" ) return "eighty-seven";	if ($number == "88" ) return "eighty-eight";	if ($number == "89" ) return "eighty-nine";	if ($number == "90" ) return "ninety";	if ($number == "91" ) return "ninety-one";	if ($number == "92" ) return "ninety-two";	if ($number == "93" ) return "ninety-three";	if ($number == "94" ) return "ninety-four";	if ($number == "95" ) return "ninety-five";	if ($number == "96" ) return "ninety-six";	if ($number == "97" ) return "ninety-seven";	if ($number == "98" ) return "ninety-eight";	if ($number == "99" ) return "ninety-nine";	if ($number == "100" ) return "one hundred";
	return $number; // ... or return the original number
	}

function wordifyNumbersOrdinals($number) {
    $number = ceil($number);

    $units = [
        "", "first", "second", "third", "fourth", "fifth", "sixth", "seventh", "eighth", "ninth"
    ];
    $teens = [
        "tenth", "eleventh", "twelfth", "thirteenth", "fourteenth", "fifteenth", 
        "sixteenth", "seventeenth", "eighteenth", "nineteenth"
    ];
    $tens = [
        "", "", "twentieth", "thirtieth", "fortieth", "fiftieth", 
        "sixtieth", "seventieth", "eightieth", "ninetieth"
    ];
    $tensPrefix = [
        "", "", "twenty", "thirty", "forty", "fifty", 
        "sixty", "seventy", "eighty", "ninety"
    ];

    if ($number <= 0 || $number > 100) {
        return "out of range";
    }

    if ($number == 100) {
        return "one hundredth";
    } elseif ($number <= 9) {
        return $units[$number];
    } elseif ($number <= 19) {
        return $teens[$number - 10];
    } else {
        $tensPart = floor($number / 10);
        $unitsPart = $number % 10;

        if ($unitsPart == 0) {
            return $tens[$tensPart];
        } else {
            return $tensPrefix[$tensPart] . "-" . $units[$unitsPart];
        }
    }
}


/** returns @timestamp: gets a timestamp from structured date like 2013-03-09 (default); does not parse hours, mins, secs */
function parseDate($date, $format = "Y-m-d") { #936491636 no longer need % prefixes in default format, e.g. "%Y-%m-%d"
/* What a fine use of a function this is, because it requires two tricky steps to get a timestamp out of a random date every damned time, so it’s a terrific wheel not to have to reinvent. Why there isn’t a PHP function that already does this is beyond me. How it works ...

Tricky step 1: the php function STRPTIME (string parsed to time) compares a string with a date format and (if it can) returns a strange array of values.  Matching a format to the input date can be hellacious. We default to my own very commonly used date format (2013-06-18), but the function will take arbitrary formats.

Tricky step 2: the php function mktime (make time) builds a Unix timestamp from the values in the weird array that we got from strptime. Hours, minutes, and seconds are hard-coded to 0.  This is now easier and clearer than it used to be with strptime, fortunately. 

 */

	// tricky step 1
	
	$tmp = date_parse_from_format ($format, $date); #date_parse_from_format__replaces__striptime, change 936491636 on 2024-06-24. This is the main place the change affects, with a handful of other files affected.  Differences are: the new function flips the parameter order, doesn't need % to mark format shorthands, and has less cryptic output that doesn't need to be modified, see details below in the $tmp array passed to mktime.

	if ($tmp === false) {
		error("Sorry, but “{$date}” cannot be parsed from the format “{$format}”.");
		return false;
		}
		
	// tricky step 2
		$timestamp = mktime( 0,0,0,
			 $tmp['month'],  #936491636 clearer name, no longer need to add 1
			 $tmp['day'], #936491636 clearer name
			 $tmp['year'] ); #936491636 clearer name, no longer need to add 1900
	return $timestamp;
}


/** returns a formatted date string based on the age of a given timestamp (less specific for older dates)  */
function flexibleDate($timestamp) {
/* either a more detailed format for a recent dates, or a less detailed one for older dates, and both are readable but compact formats used throughout the site */
	if ($timestamp < time()-500*24*60*60)
		return date("Y", $timestamp); 
	else
		return date("M jS, Y", $timestamp); 
	}

function printUpd ($date) {
	if ($date === "") error("empty \$pubdate string");
	if ($date === false) error("\$pubdate string is boolean false");	
	global $updated, $paid;
	$updatedStamp = parseDate($updated);
	if ($paid or MODE_DEV) // for non-G-indexable document states
		return date('M j, Y', parseDate($date)); 
	// for all indexable content, it is necessary to NOT spell out full dates, because Google is bizarrely unable to resist using them as doc modified dates, and this had been true for years the last time I tested it ~2017 *eyeroll*
	if ($date == $updated and daysSinceTstamp($updatedStamp) < 400)
		return date('M j, Y',parseDate($date)); // handle the most recent update differently: print the full date (but only if it's in the same year)
	if (inStr(YEAR,$date)) // if the current year is in the $date...
		return date("F",parseDate($date)); // print just the month, F = full month, eg January or March, not Jan, Mar
	else
		return date("Y",parseDate($date));
}

/** returns @string, content: evaluates PHP with the eval function and returns the output, may be very complex */
function evald ($string) {
	ob_start(); // start buffering so eval produces no output
	eval("?>$string"); // On the use of eval in the PainSci CMS: craftdocs://open?blockId=D8BB4DEF-66B7-4395-9020-1CACBE6BBFC4&spaceId=bc7d854c-3e5b-a34e-4850-a6d2f31a1a59
	$newString = ob_get_contents(); // assign the output of eval to $newString
	ob_end_clean(); // purge the buffer
	return $newString;
}		

/** returns @string: adds non-breaking spaces to the end of a string to prevent orphans  */
function preventOrphans($str) {
	if (strlen($str) < 30 or strpos(" ",$str) == false) return $str; // leave the function without doing anything if it’s a short string, or a string without any spaces
	$last_space_pos = strrpos($str, " ");
	$str = substr($str,0,$last_space_pos) . "&nbsp;" . trim(substr($str,$last_space_pos));
	return $str;
}

/** returns @print, var contents: reports boolean-ness of var regardless of value (is_bool with output) */
function printBool($boolean) {
	if ($boolean === null) { echo "NULL"; return;}
//	if (!is_bool($boolean)) { echo "NOT BOOLEAN: "; var_dump ($boolean); return;}
	else if ($boolean == true) echo "TRUE"; else echo "FALSE";
	}

/** return @print, var values including explicitly identified nulls and empty strings **/
function printVar($var) {
	if (!isset($var)) return 'NULL';
	if (is_bool($var)) return printBool($var);
	if (is_string($var) and $var === '') return '[empty string]';
	return $var;
}

/** returns @print, array contents: light formatting of print_r output, a slight upgrade of print_r */
function printArr($var, $size = "0.9em") {
	global $_istool;
	if (MODE_LIVE and !MODE_BUILD and !$_istool) return; // suppress output in production, except for tool scripts (in tools dir)
	$bt = debug_backtrace();
	$caller = array_shift($bt);
	$file = basename($caller['file']);
//	print_r($caller);
	echo "<pre class='color_soft_red' style='font-size:{$size}'>"; // add a size manually; otherwise the size comes from the 'boogers' class
		echo "<p><strong>printArr() called by {$file}[{$caller['line']}], " . count($var) . " items</strong></p>";
		print_r($var);
	echo  '</pre>';
	}

function print_array_prettier($array, $depth = 0, $header = true) {
	global $_istool;
	if (MODE_LIVE and !MODE_BUILD and !$_istool) return; // suppress output in production, except for tool scripts (in tools dir)
	$bt = debug_backtrace();
	$caller = array_shift($bt);
	$file = basename($caller['file']);
	
	if ($header) { // the header can be suppressed by a parameter for scenarios when it should be EVEN PRETTIER
		echo '<div style="font-family: Avenir Next; font-size:15pt; margin:2em 0">';
		echo "<p><strong>print_array_prettier() called by {$file}[{$caller['line']}], " . count($array) . " items</strong></p>";
		}

	if ($depth > 5) {echo "too deep!"; return;}
	foreach ($array as $field=>$value) {
		$colour = '#' . ($depth*2) . ($depth*2) . ($depth*2); 
		if ($depth == 0) $marginTop = '2em';
		if ($depth == 1) $marginTop = '.4em';
		if ($depth == 2) $marginTop = '.2em';
		if ($depth == 3) $marginTop = '.1em';
		if ($depth == 4) $marginTop = '0';
		$field = str_replace('_', '<span style="color:#DDD">_</span>', $field);
		echo "<div style='margin-left:" . ($depth*.7) . "em; margin-top:{$marginTop}; font-size: " . (1-$depth/20) . "em; color:{$colour}'>";
		$narrow = null;
		if (strlen($field) > 20) $narrow = ' class="font_narrow"';
		echo "<span{$narrow}>";
		if ($depth < 3) echo "<strong>{$field}</strong></span> <span style='color:#BBB'>→</span> ";
		else
		echo "{$field}</span> <span style='color:#BBB'>→</span> ";
		if (is_array($value)) {
//			echo 'array (' . count($value) . '):';
			print_array_prettier($value, $depth+1, false); // false to suppress the header on nested calls
			}
		else {
			$narrow = '';
			if (is_string($value) and strlen($value) > 30) $narrow = ' class="font_narrow"';
			echo "<span class='{$narrow}'>";
			if ($value === null) echo "<small style='color:#999'>NULL</small>";
			if ($value === true) echo "<small style='color:#81AD81'>TRUE</small>";
			if ($value === false) echo "<small style='color:#c66'>FALSE</small>";
			if (is_string($value)) echo $value;
			if (is_integer($value)) echo "<code>$value</code>";
			if (is_string($value) and $value == '') echo "<small style='color:#999'>EMPTY</small>";
//			if (is_bool($value)) printBool($value); 
			// echo "<br>";var_dump($field, $value); echo "<br>";
			echo "</span>";
			}
		
		echo "</div>";
	}
		if ($header) echo "</div>";	
}


/** returns @print, Json contents: basically just slightly fine-tuned output of json_encode() */
function printJson ($var) {
 return gettype($var) . ' ' . json_encode(
   $var,
   JSON_UNESCAPED_SLASHES | 
   JSON_UNESCAPED_UNICODE | 
   JSON_PRETTY_PRINT | 
   JSON_PARTIAL_OUTPUT_ON_ERROR | 
   JSON_INVALID_UTF8_SUBSTITUTE 
 ); 
}

/** returns @echo, array contents: prints array as a list with item count */
function printArrOl($arr, $size = "0.9em") {
	if (!is_array($arr)) { echo "That’s no array! That’s …<br> ";  var_dump($arr); return;}
	// suppress output in production, except for tools
	global $istool;
	if (MODE_LIVE and !MODE_BUILD and !$istool) return; // suppress output in production, except for tool scripts (in tools dir)
	echo count($arr) . " items in array:<br>";
	echo "<ol start=0 class='boogers' style='font-size:{$size}'>"; // add a size manually; otherwise the size comes from the
	foreach ($arr as $key=>$val)
		if (isAssoc($arr))
			echo "<li><strong>$key</strong>: $val</li>";
		else
			echo "<li>$val</li>";
	echo  '</ol>';
	}

/** returns @echo|string, array: coverts array of keys and values to a table view to print or return; "1" is a reference to the depth; see also the printArrTable2 */
function printArrTable1($arr, $return=false, $title=null, $size="large", $headKey=false, $headVal=false) { /*
	This function is mostly a dev-tool, not for producing user-facing tables of data… though it could do that, if the data was clean enough.  I’ve provided several some cosmetic configuration parameters for that reason: optional title (caption), size class, headers…
		return = to return the string data instead of echo
		title = adds title in <caption> element
		size = small|medium|large class for <table>
		headKey/headVal = <th> contents for key and value columns
	Example data: $testArr = array("field1" => "data1", "field2" => "data2", "field3" => "data3"); */
	if (!is_array($arr)) { echo "That’s no array! That’s …<br> ";  var_dump($arr); return;}
	$table .= "<table class='$size' style='font-family:\"Avenir Next Condensed\"; margin:2em 0;border-spacing:10px 0'>"; 
	if ($title) $table .= "<caption>$title</caption>";
	// $table .= '<caption>Simple associative array with count ' . count($arr) . ' fields';
	if ($headKey and $headVal) $table .= "<tr><th>$headKey</th><th>$headVal</th></tr>";
	foreach ($arr as $field => $value) {
		$value = htmlentities($value, ENT_QUOTES, "UTF-8");
		if (strlen($value) > 5000) $value = substr($value,0,500) . " <strong> … [truncated to 500 chars]</strong>";
		if (!isset($value)) $value = "<span style='color:#AAA'>unset</span>"; 
		if ($value === '') $value = "<span style='color:#AAA'>empty</span>";
		if (is_null($value)) $value = "<span style='color:#AAA'>null</span>";
		//var_dump($field, $value); echo "<br><br>";
		if (is_bool($value)) {
			if ($value === true) $value = "true";
			else $value = "false";
			}
		$table .= "<td style='vertical-align:top;color:#888'><strong>$field</strong></td><td><span class='copythis2' onClick='copy(this)'>$value</span></td></tr>"; // to style the copyable text, add the "copyable" class
		}
	$table .= "</table>";
	if ($return) return $table;
		echo $table;
	}

/** returns @echo, array: prints array of arrays as a table (e.g. multiple records), "2" is a reference to depth, see also printArrTable1 */
function printArrTable2($arr, $return=false) { /* basic database structure: an array of records, each one container an array of fields and data, like so:
				array(
				"record1" => array (
					"field1" => "data",
					"field2" => "data",
					"field3" => "data"),
				"record2" => array (
					"field1" => "data",
					"field2" => "data",
					"field3" => "data")); */

if (!is_array($arr)) { echo "That’s no array! That’s …<br> ";  var_dump($arr); return;}
	$table .= '<table class="large" style="font-family:\'Avenir Next Condensed\'; margin:2em 0;border-spacing:10px 0">';
//	echo "<caption>" . count($arr) . " posts:</caption>";

	foreach ($arr as $key => $value) {
	unset($fieldNo);
	$fieldCount = count($value);
		foreach ($value as $field => $subvalue) {
		if (is_array($subvalue)) $subvalue = implode(' • ', $subvalue); // many field values are simple array themselves, and many of them can be trivially and meaningfully imploded into a string (rather than getting into the complexity of another layer of hierarchy
		$subvalue = htmlentities($subvalue, ENT_QUOTES, "UTF-8");
		if ($field == "content" and strlen($subvalue) > 1000) $subvalue = substr($subvalue,0,1000) . " <strong> … [truncated]</strong>";
		if ($field == "html") continue; // special case for my cms
		if (++$fieldNo == 1)
			$table .= "<tr><td colspan=3 style='border-bottom:1px solid #AAA;'>&nbsp;</td></tr><tr><td rowspan=$fieldCount style='vertical-align:top;color:#AAA'>$key</td>";
		else
			$table .= "<tr>";
		$table .= "<td style='vertical-align:top;font-weight:strong;color:#888'>$field</td><td>$subvalue</td></tr>";
		}
	}
	
	$table .= "</table>";
	if ($return) return $table;
	else echo $table;
	}

/** returns @string, content: evaluates PHP with the eval function and returns the output */
function renderPhpStr ($string) {
	/* Note: renderPhpStr(file_get_contents(file)) === renderPhpFile(file,true) */
	if (!inStr("<?php", $string)) return $string; // is there PHP in the string?
	ob_start();
	eval("?>$string"); // On the use of eval in the PainSci CMS: craftdocs://open?blockId=D8BB4DEF-66B7-4395-9020-1CACBE6BBFC4&spaceId=bc7d854c-3e5b-a34e-4850-a6d2f31a1a59
	$string = ob_get_contents();
	ob_end_clean();
//	if (inStr("XXX", $string)) exit("<pre>`". htmlentities($string) . "`</pre>");
	return $string;
}


/** returns @string, content: evaluates PHP in a file with the eval function and returns the output or saves, may be very complex 
	executes a PHP file and returns the output as string (default), or write the output to the same filename (but with an html extension instead of php); assumes you will pass in a valid filename, and that you want file output in the same location with a different name
	Eg: 	renderPhpFile("beautiful.php") → writes 'beautiful.html'
			renderPhpFile("beautiful.php", true) → returns the output in a string 		
	Note: renderPhpStr(file_get_contents(file)) === renderPhpFile(file,true) */
function renderPhpFile ($file, $new_name = false, $return = false) {
	$file_contents = file_get_contents($file);
	$rendered_contents = renderPhpStr($file_contents);
	if ($return) return $rendered_contents;
	if (!$new_name) $html_fn = str_replace("php", "html", $file);
		else $html_fn = $new_name . ".html";
	$fp = fopen($html_fn, "w"); // w = create a new file
	fwrite($fp, $rendered_contents);
	fclose($fp);
	}

/** returns @array, arguments: converts "sloppy" strings of entered data into tidy array of arguments
By sloppy, I mean that it tolerates relatively imprecise delimiters, specifically: tabs, 3 or more double-hyphens, or by 1 or more line feeds (\n). This allows for low-friction data entry in a variety of contexts. Seemed like a good idea at the time anyway. */
function parseSloppyData($user_input) { 

	if (is_array($user_input)) return $user_input; // Sometimes a function is handed data that has already been parsed (i.e. turned into an array). If so, there's no need to parse it again, is there?
	
	// To start, we snip off any whitespace.  If there is ONLY whitespace (i.e. just a space would be the most likely such error), this will result in an empty variable, which will trigger an error report.
	$user_input = trim($user_input); 
	
	 // Every function that uses parseSloppyData has the responsibility of reporting an error if there is no user input, if that's relevant to the function.  This function only returns a false value if there's no user input.  It does not assume an error, because it might not be an error.  And even if it did assume an error, it doesn't have information about which function generated the error. 
	if (!$user_input || $user_input === "") return false;

	// mask nested delimeters (a tricky, important step): sometimes this function is given a string to process that contains nested calls to itself; that is, PHP statements calling functions that ALSO use parseSloppyUserInput; those must be masked, or they will also be split up into arguments; this line passes the contents of every PHP statement to another function that replaces runs of -- with ###; these must then be changed back later
	$user_input = preg_replace_callback("/<\?.*?\?>/uis", 'maskNestedDelims', $user_input);
	// same thing applies to shorthands for PHP...
	$user_input = preg_replace_callback("/<<.*?>>/uis", 'maskNestedDelims', $user_input);

	if (strpos($user_input,"\n") > 0 || strpos($user_input,"\r") > 0 || strpos($user_input,"\t") > 0) {
		// If there are any CRs or tabs, convert them to triple-hyphens.
		$user_input = preg_replace("/[\n\r\t]+/","---",$user_input);  
		}		
		
	// convert 3 or more hyphens to triple-asterisks
	$user_input = preg_replace("/-{3,}/","***",$user_input);
	
	$user_input = str_replace("Phone=--", "Phone=",$user_input);
	
/*	// are there any hyphens left?
	if (strpos($user_input,"--") !== false) {
			$user_input = preg_replace("/--/","***",$user_input);
			$warning = "notice---There is a double-hypen in the user input. This is probably intended to be a triple-hyphen, and the user input parser went ahead with that assumption. However, if you wanted to use an actual dash, then use it.";
			if (function_exists("error")) error($warning); // checking for the error function before using it is helpful when running PubSys, which does not use it
			}	*/

	// are there any spaces?
	if (strpos($user_input," *") !== false or strpos($user_input,"* ") !== false) {
			$user_input = preg_replace("/\h+\*\*\*\h+/","***",$user_input);
			}	

	$user_input = trim($user_input,"*");
			
	$parsedInput = explode("***",$user_input);

	$parsedInput=array_map("mopParsedUserInput",$parsedInput);

	return $parsedInput;

	} // EOFn

/** returns @string, php: masks delimiters for parseSloppyData()
Looks through PHP statement contents for all runs of --- and replaces them with ### to be subsequently removed by mopParsedUserInput;  */
function maskNestedDelims($match) { return preg_replace('/(-{3,6}|\t)/', '%%%', $match[0]); }

function mopParsedUserInput ($arg) {
		$arg = trim($arg); // get rid of any extraneous spaces around each item of userinput in an array
		$arg = str_replace("%%%", "---", $arg); // restore previously masked delimiters
		return $arg;
		}

/** returns @array: gets an associative array from file with a simple, readable, editable structure */
function getArrFromFile($filename, $synonyms = false, $simple = false) {
/* reads a text file from somewhere in the include path
text file consists of lines of related terms, 
	fruit: apples, oranges, pears
	veggies: asparagus, broccoli, peas
ignore all lines except those beginning with a marker: * • —
splits each line into an array, delimited by tabs, commas or colons (NOT SPACES)
converts the first item to a key, and uses the remainder as an array of values
returns an array with this structure
	fruit => array(apples, oranges, pears)
what about synonyms? 	for synonyms, it's convenient and nice to do a single check off the values with in_array, which requires the date to look like this:
	fruit => array(fruit, apples, oranges, pears) */
	$lns = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES | FILE_USE_INCLUDE_PATH);
	foreach($lns as $ln) {
		$pattern = "/^\s*[\*•—]\s*(\w)/u";
		if (preg_replace($pattern, "$1", $ln) === $ln) continue; // if no data marker is found (line is unchanged by replacing it), skip this line
		$ln = preg_replace($pattern, "$1", $ln); // strip out the data marker
		
//		echo $ln . "<br>";
		if ($simple) {
			$subarr = preg_split("/[\t]+/", $ln);
			$key = trim($subarr[0]);
			$array[$key] = trim($subarr[1]);
			}
		else {
			$subarr = preg_split("/[\t,:]+/", $ln);
			foreach ($subarr as &$value) $value = trim($value);
			if (count($subarr) == 2) $array[$subarr[0]] = $subarr[1]; // if there’s only 2 items, then this is a key=value pair
			elseif ($synonyms) 
				$array[$subarr[0]] = $subarr;
			else 
				$array[$subarr[0]] = array_splice($subarr, 1, count($subarr));
			}
		}
	return $array;
}

/** returns @string, content: truncates content to the last complete word before max chars */
function truncateToWord ($str, $max) {
/* see also wordwrap(), chunk_split() */
		$str = mb_substr($str,0,$max); // trim to the absolute character limit 
		$end_puncs = array("!",		"?",		"."); 
		foreach ($end_puncs as $punc) {
			$end_punc_pos = mb_stripos($str, $punc);
			if ($end_punc_pos === false or $end_punc_pos < $max*.85) continue;
			$str = mb_substr($str,0,$end_punc_pos+1); // truncate string at the ending punctuation
			return $str;
			}
		$arr = explode(" ", $str);
		array_pop($arr); // die, last word, die
		$str = implode(" ", $arr);
		$str = rtrim($str, ",—’:;"); // trim awkwardly trailing punctuation
		if (!in_array(mb_substr($str,-1), $end_puncs)) $str .= "…"; // if the content segment doesn’t have concluding punctuation, append ellipsis
		return $str;
}

/** returns @string, content: capitalizes short runs of EM or STRONG text */
function emUpperizer ($str, $max=40) {
	$str = str_replace("&nbsp;", " ", $str); // strip non-breaking space entitites
	$str = str_replace("<em>et al</em>", "et al", $str); // strip <em> from et al., because 
	$str = preg_replace_callback("@(<em>|<strong>)(.{2,$max})(</em>|</strong>)@ui", 
		function ($matches) {return mb_strtoupper($matches[2]); }, $str); 
	$str = preg_replace_callback("@[\*_]{1,2}(.{2,20})[\*_]{1,2}@ui", 
		function ($matches) {return mb_strtoupper($matches[1]); }, $str);
	return $str;
		}

/** returns @echo, log: echoes a step in a script and adds it to a log */
function journal($msg, $depth, $echo = false) {
//	if (!MODE_DEV) return; // journalling is for dev only
	$x = 0; $msg_class = '';
	global $jrnl, $a, $b, $c;
	$msg = str_replace("posts/","",$msg);
	if (strpos($msg, "warning") === 0) { $warning = true; $msg_class = "warning"; }
		else $warning = false;
	$msg = ucfirst($msg);
	$time = microtime(true);
	if (!isset($jrnl)) { // first time here?  initialize!
		$jrnl = array();
		$a = 0; $b = 0; $c = 0;
		}
	if ($depth == 1) {
		++$a; $no = $a; $b = 0; $c = 0;
		$jrnl[$a]['msg'] = $msg;
		$jrnl[$a]['time'] = $time;
		if ($a > 1 and $b > 0) $jrnl[$a]['elapsed'] = $time - $jrnl[$a][$b-1]['time'];
		}
	if ($depth == 2) {
		++$b; $no = "$a.$b"; $c = 0;
		$jrnl[$a][$b]['msg'] = $msg;
		$jrnl[$a][$b]['time'] = $time;
		if ($b > 1) $jrnl[$a][$b]['elapsed'] = $time - $jrnl[$a][$b-1]['time'];
		}		
	if ($depth == 3) {
		++$c; $no = "$a.$b.$c";
		$jrnl[$a][$b][$c]['msg'] = $msg;
		$jrnl[$a][$b][$c]['time'] = $time;
		if ($c > 1) $jrnl[$a][$b][$c]['elapsed'] = $time - $jrnl[$a][$b][$c-1]['time'];
		}		
	if ($echo or $depth == 1 or $warning) {
			if ($depth == 1) echo "<br><br>";
				else echo "<br>";
			while (++$x <= $depth) echo "&nbsp;&nbsp;";
			echo "<span class='$msg_class'>$no → $msg</span> ";
			}
	else {
		echo "<span class='step'>&thinsp;•</span>";
		}
	}



/** returns @string: simplifies a string for easier matching */
function simplify($str, $stripDiacriticals = false) {
	$find =	 explode(" ","- * _ — – . , … & ! ’ ‘ “ ” \" ' : ; ? / · % ( ) ="); $find[] = " "; // add space
	$str = str_replace($find, '', $str); // strip spaces and punctuation
	$str = strtolower($str); // lowerize
	if ($stripDiacriticals) $str = stripDiacriticals($str); // optional diacritical stripping
	return $str;
	}

/** returns @string: strip common diacriticals to ASCII equivalents */
function stripDiacriticals($str) {
	// this function does not deal with case; so far I only need to transliterate for the purposes of simplifying strings for comparison, so everything is lowerized before transliteration
	// thus far I think this function is only called optionally by simplify()
	$find = explode			("\t", "¡	¿	á	à	â	å	ä	ã	æ	ç	ð	é	è	ê	ë	í	ì	î	ï	ñ	ó	ò	ô	ö	õ	ø	œ	ß	ú	ù	û	ü	ý	ÿ");
	$replace = explode	("\t", "!	?	a	a	a	a	a	a	ae	c	th	e	e	e	e	i	i	i	i	n	o	o	o	o	o	o	oe	sz	u	u	u	u	y	y");
	$str = str_replace($find, $replace, $str);
	return $str;
	}

/** returns @string: converts punctuation marks to spaces or other */
function puncMarksToSpace($str, $sub = " ") {
	$puncs = explode(" ","- * _ — – . , … & ! ’ ‘ “ ” \" ' : ; ? / · % ( ) = [ ] { } < > ≥ ≤");
	return str_replace($puncs, $sub, $str); // strip spaces and punctuation
	}

/** returns @string, title: simplified and hyphenated, ready for use as filename */
function titleToFn ($title) {
// this function is primarily used by PubSys, but I imported it here because it is probably going to be useful for other projects, especially static bib pages, #binreno
	$title = strip_tags($title);
	$title = strtolower($title);
	// #2do, general solution for simplifying punctuation
	$title = str_replace(" ", "-", $title); // spaces to hyphens
	$title = unaccent($title); // 2015-08-09, warning! unaccent’s output is dependent on PHP’s intl extension, which may differ dev/prod; however, the only difference I’ve noticed is that it will or won’t convert ellipsis to three-dots, which has implications in the next step
	// punctuation to remove or replace
	$find =		array("—",	"–",	",",	"--",	"---",	"...",	"…",	".",	"&amp;", 	"&",		"!",	"’",	"‘",	"“",	"”",	"\"",	"'",		":",	"?",	"★",	"☆", "/", "·", "%");
	$change =	array("--",	"-",	"",		"-",	"--",	"--",	"--",	"",		"and", 		"and",	null,	null,	null,	null,	null,	null,	null,	"-",	"",		"*", 	"",	"-", "-", "percent");
	$title = str_replace($find, $change, $title);
	$title = rtrim($title, "-");
	$verboten_1st_words = array ("the", "an","a");
	foreach ($verboten_1st_words as $word) 
		$title = preg_replace("/^" . preg_quote($word) . "-/u","",$title);
	return $title;
}

/** returns boolean: compares two simple arrays, returns true if identical, false if different, built for checking input data to whitelist */
function array_same($arrayA, $arrayB) {
	// array_diff() annoyingly misses extra elements comparing one way but not the other, so this function does what I wish array_diff did: identifies ANY different between two 1d arrays
	sort($arrayA); 
	sort($arrayB); 
	return $arrayA == $arrayB; // true if same, false if 
	} 

/** returns string: transliterates accented characters to ASCII equivalents */
function unaccent($string) {
	if (extension_loaded('intl') === true) {
		$string = Normalizer::normalize($string, Normalizer::FORM_KD);
		}
	if (strpos($string = htmlentities($string, ENT_QUOTES, 'UTF-8'), '&') !== false)	{
		$string = html_entity_decode(preg_replace('~&([a-z]{1,2})(?:acute|caron|cedil|circ|grave|lig|orn|ring|slash|tilde|uml);~i', '$1', $string), ENT_QUOTES, 'UTF-8');
		}
	return $string;
	}


function embed_youtube ($yt_id, $yt_title='Watch on YouTube', $yt_duration=null, $echo=true) {
	// The 'embed_youtube' function versus 'embed_youtube_db' pseudofield are identical; they just get their metadata (id, title, duration) from different sources. The function version gets data "manually" from parameters, and the pseudofield gets it from the database... and then requests the markup from the function version. 
	// The manual  method is useful for quick, ad hoc video embeds where I don’t really care if there’s a duration shown and don’t need any more information.  The xref method is for more permanent/important videos where presentation detail is more important.
	
	if ($yt_duration) $yt_duration = " <small>$yt_duration</small>";

	// This is the one template for YT embeds. It uses an interesting lazy loading technique for YouTube embeds, kind of a weird hack, but seems to work: TBT scores fall from >2000ms to 100ms. Sheesh. https://css-tricks.com/lazy-load-embedded-youtube-videos/
	// Also, this is nice: dead easy to remove tracking BS just by linkin to youtube-nocookie.com!  So great. See https://dri.es/how-to-remove-youtube-tracking
$yt_html= <<<HTML

		<div class="ytebox"><!-- yte = ytemb = #youtube-embed -->
			<div class="ytemb">
				<iframe
				src="https://www.youtube.com/embed/$yt_id"
				srcdoc="<style> *{padding:0;margin:0;overflow:hidden}html,body{height:100%}img,span{position:absolute;width:100%;top:0;bottom:0;margin:auto}span{height:1.5em;text-align:center;font:48px/1.5 sans-serif;color:white;text-shadow:0 0 0.5em black}</style><a href='https://www.youtube-nocookie.com/embed/$yt_id?autoplay=1'><img src='https://img.youtube.com/vi/$yt_id/hqdefault.jpg'><span>▶</span></a>"
				frameborder="0"
				allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"
				allowfullscreen
				title="$yt_title"
				loading="lazy"
				></iframe>
			</div>
			
			<p class="css_caption font_accent"><a href="//www.youtube.com/watch?v=$yt_id" title="$yt_title">$yt_title&ensp;<img class="lazyimg" data-src="/imgs/icon-youtube--land4-180x40-4k.png" width="72" height="16" style="border:0;vertical-align:-1px" alt="" border="0" loading="lazy">$yt_duration</a></p>
		</div>

HTML;
	if ($echo) echo $yt_html;
	else return $yt_html;
	}



/**
 * Generate an MD5 hash string from the contents of a directory.
 *
 * @param string $directory
 * @return boolean|string
 */
function hashDir($directory)
{
    if (! is_dir($directory))
    {
        return false;
    }
 
    $files = array();
    $dir = dir($directory);
 
    while (false !== ($file = $dir->read()))
    {
        if ($file != '.' and $file != '..')
        {
            if (is_dir($directory . '/' . $file))
            {
                $files[] = hashDir($directory . '/' . $file);
            }
            else
            {
                $files[] = md5_file($directory . '/' . $file);
            }
        }
    }
 
    $dir->close();
 
    return md5(implode('', $files));
}


/** returns @object, record: gets a record from $sources via citekey, with some input sanitization */
function getRecord($citekey) {
	if (!checkCitekey($citekey)) return false;
	global $sources;
	$record = $sources->safeGet($citekey); // try to get the record
	if ($record->isValid() == false) return false; // check the record
	return $record;
}

/** returns @boolean: checks string for validity as a citekey candidate */
function checkCitekey($citekey) {
	if (strlen($citekey) > 50) return false; // refuse long citekey
	if (is_numeric($citekey)) return false; // refuse numeric arg
	return true;
	}

/** returns @null: unsets each variable previously extracted from an array */
function unsetExtractions($array) {
	if (!is_array($array)) return false;
	if (count($array) == count($myarray, COUNT_RECURSIVE)) return false; // returns the array if it’s not mult-dimensional
	foreach ($array as $key=>$value) unset($GLOBALS[$key]); // unset will not work directly on global vars, must use $globals
	}

/** returns @array, record data: an array of field and pseudofield data, converted from a record object */
function recordObjToArr($record_obj) {
	global $sources;
	$record_arr = get_object_vars($record_obj);
	foreach ($record_arr as $key=>$value) {
		$record_raw_tmp[$key] = $value;
		$key_clean = str_replace("-", '', $key);
		$record_arr[$key_clean] = $record_obj->get($key);
		}
	$record_arr = addCommonPseudofields($record_arr, $record_obj);
	ksort($record_arr);
	$record_arr['raw'] = $record_raw_tmp; 
	return $record_arr;
	}

/** returns @array, record data: adds an array of common pseudofields to an array of field data for a record */
function addCommonPseudofields($record_arr, $record_obj) {
	$select_pseudofields = explode(" ", "surnameetal surnameoffirst fullnameoffirst biburl basetitle titlesmartperiod pmedurl longtags shorttags citedbynum maxsummary keywords allenhancedtags_short noindex titlecustom wcannote wcabstract titletocolon best_ext_url countcitedbys dateadded datemodded related biburlfilename");
	foreach ($select_pseudofields as $fieldname)  $record_arr[strtolower($fieldname)] = $record_obj->get($fieldname);
	return $record_arr;
	}

/** returns @string, title: converts a title to title case, leaving small words uncapitalized */
function ucwordsSmart ($title) { // #bibreno #vansys
	// Split the string into separate words
	$words = explode(' ', $title);
	foreach ($words as $key => $word) {
		// If this word is the first, or it's not one of our small words, capitalise it with ucwords().
		if ($key == 0 or !in_array($word, explode(" ", $GLOBALS['smallwords'])))
		$words[$key] = ucwords($word);
		}
	// Join the words back into a string
	return implode(' ', $words);
	}

/** returns @print, HTML: print link or script elements for css or js resources */
function inclood($resource_URL, $sniff = false) {
	$sniff1 = $sniff2 = '';
	if ($sniff) { $sniff1 = "<!--[{$sniff}]>"; $sniff2 = "<![endif]-->"; }
	if (!MODE_BUILD) { // this is a half-arsed way of detecting a customer without doing a login; the only consequence is the method of linking to stylesheets, so the stakes are low; for any valid customer, this will be fine; for invalid requests, it won't matter
		$cid = false; // default
		if (isset($_GET['id'])) {
			$cid = true;
			}
		}
	if (inStr('.css', $resource_URL) and inStr('//', $resource_URL)) { // normal link to external stylesheets
		echo "<link rel='stylesheet' href='{$resource_URL}'>";
		}
	elseif (inStr('.css', $resource_URL)) { // in front of the paywall, use preload for all non-critical stylesheets
		if (!inStr('//', $resource_URL)) // if there's no absolute URL, then add the incs directory to the URL
		$resource_URL = "/incs/". $resource_URL;
		echo "$sniff1<link rel='preload' as='style' href='{$resource_URL}' onload=\"this.onload=null; this.rel='stylesheet'\">\n";
		echo "<noscript><link rel='stylesheet' href='{$resource_URL}'></noscript>$sniff2";
		}
		
	if (inStr('.js', $resource_URL)) {
		if (!inStr('//', $resource_URL)) // if there's no absolute URL, then add the incs directory to the URL
		$resource_URL = "/incs/". $resource_URL;
		if (inStr('zoom', $resource_URL))
			echo "$sniff1<script async src='$resource_URL' ></script>$sniff2";
		elseif (inStr('popups', $resource_URL))
			echo "$sniff1<script async src='$resource_URL' ></script>$sniff2";
		elseif (inStr('footnotes', $resource_URL))
			echo "$sniff1<script async src='$resource_URL' ></script>$sniff2";
		elseif (inStr('pull-quotes', $resource_URL))
			echo "$sniff1<script async src='$resource_URL' ></script>$sniff2";
		elseif (inStr('shortcuts', $resource_URL))
			echo "$sniff1<script async src='$resource_URL' ></script>$sniff2";
		elseif (inStr('bookmarks-ui', $resource_URL))
			echo "$sniff1<script defer src='$resource_URL' ></script>$sniff2";
		else
			echo "$sniff1<script src='$resource_URL' ></script>$sniff2";
		}
	echo "\n\n";
	}
	

/** returns @string: truncates string after a colon; primitive version of strToPunc */
function strToColon($str) {
	$colon = strpos($str, ':');
	if ($colon) return substr($str, 0, $colon);
	else return $str;
	}

/** returns @string, title: truncates a string after punctuation, usually a title but anything  (eg used by TitleToColon pseudofield)  */
function strToPunc ($str) {
// this next line CHOKES on unicode strings that begin with a multibyte char; the clunky code below works around that in a clunky but fairly bulletproof fashion
//	if (preg_match("@^(.*?[:!?])\s*(.+?)$@u", $str, $parts)) return trim($parts[1]," :;—"); // strips off punc that usually shouldn’t terminate a title
	if (mb_strpos($str, "!") > 0) $str = mb_substr($str, 0, mb_strpos($str, "!")+1);
	if (mb_strpos($str, ":") > 0) $str = mb_substr($str, 0, mb_strpos($str, ":"));
	if (mb_strpos($str, "?") > 0) $str = mb_substr($str, 0, mb_strpos($str, "?")+1);
	return $str;
	}
	

function ogimg ($img_filename) {
	$pageimg_file = _ROOT . '/imgs/' . $img_filename;
//	exit($img_filename . " > " . $pageimg_file);

	if (!file_exists($pageimg_file)) {
	echo "<!-- missing featured image: $img_filename -->"; return; }

	$imagedata = getimagesize($pageimg_file);
	$w = $imagedata[0];
	$h = $imagedata[1];
	$card_type = 'summary'; // default card type
	if ($w > 750 and $w > $h*1.5) // if the image is big and format is landscape or close enough;
		$card_type = 'summary_large_image'; // change the card type to tell twitter I want to use a big image

	$image_mime = image_type_to_mime_type(exif_imagetype($pageimg_file));

	$ogoutput = <<<OGPAGEIMG
<meta property='og:image' 				content='https://www.painscience.com/imgs/{$img_filename}'>
	<meta property='og:image:type' 	content='{$image_mime}'>
	<meta property='og:image:width' 	content='{$w}'>
	<meta property='og:image:height' 	content='{$h}'>

<meta name='twitter:card' 			content='{$card_type}'>
OGPAGEIMG;
//	echo "<pre>" .  htmlspecialchars($ogoutput) . "</pre>";
return $ogoutput;
}

function printOgImg ($img_filename) {
echo ogimg($img_filename);
}

function minifyCSS ($filename) {
$css = file_get_contents($filename, true); // Remove comments; true = USE_INCLUDE_PATH
$css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css); // Remove space after colons
$css = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $css);
$css = str_replace(', ', ',', $css); // Remove whitespace
$css = str_replace(': ', ':', $css); // Remove whitespace
$css = str_replace(' {', '{', $css); // Remove whitespace
$css = str_replace('{ ', '{', $css); // Remove whitespace
$css = str_replace('} ', '}', $css); // Remove whitespace
$css = str_replace(';}', '}', $css); // Remove final semi-colons
return $css;
}

function printArrXML($arr,$first=true) {
/* header('Content-Type: text/xml; charset=UTF-8');
echo printArrXML($arr); */
  $output = "";
  if ($first) $output .= "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<data>\n";
  foreach($arr as $key => $val) { 
    if (is_numeric($key)) $key = "arr_".$key; // <0 is not allowed
    switch (gettype($val)) { 
      case "array":
        $output .= "<".htmlspecialchars($key)." type='array' size='".count($val)."'>".
          printArrXML($val,false)."</".htmlspecialchars($key).">\n"; break;
      case "boolean":
        $output .= "<".htmlspecialchars($key)." type='bool'>".($val?"true":"false").
          "</".htmlspecialchars($key).">\n"; break;
      case "integer":
        $output .= "<".htmlspecialchars($key)." type='integer'>".
          htmlspecialchars($val)."</".htmlspecialchars($key).">\n"; break;
      case "double":
        $output .= "<".htmlspecialchars($key)." type='double'>".
          htmlspecialchars($val)."</".htmlspecialchars($key).">\n"; break;
      case "string":
        $output .= "<".htmlspecialchars($key)." type='string' size='".strlen($val)."'>".
          htmlspecialchars($val)."</".htmlspecialchars($key).">\n"; break;
      default:
        $output .= "<".htmlspecialchars($key)." type='unknown'>".gettype($val).
          "</".htmlspecialchars($key).">\n"; break;
    }
  }
  if ($first) $output .= "</data>\n";
  return $output;
}


function getLastUpd ($updated) {
	global $build_fn, $_pathdoc;
	if (MODE_BUILD) $file = $build_fn; // $build_fn is the path+filename set by make-ps in build mode
		else $file = $_pathdoc;
	$file_str = file_get_contents($file);
	if (!inStr("upd_item",$file_str)) return $updated; // no upd_items? no point! use the deprecated php var $updated after all; these are now used only in documents that haven’t been updated since mid-2016
	preg_match('|<p class="upd_item" data-scope=".*?"><\?php echo printUpd\("(.+?)"|', $file_str, $match); // this matches only the first occurrence, and we are trusting that the first upd_item to be the most recent
	return $match[1];
	}


function scoreToStars($rating) {
$key = array(
"1" => "negative examples, fatally flawed papers, junk science, suspected fraud",
"2" => "studies with flaws, bias, and/or conflict of interest; published in lesser journals",
"3" => "typical studies with no more (or less) than the usual common problems",
"4" => "bigger/better studies and reviews published in more prestigious journals, with only quibbles",
"5" => "sentinel studies, excellent experiments with meaningful results",
);
$x = 0; $stars = "";
while ($x++ < $rating) {$stars .= "★";}
while ($x++ < 6) {$stars .= "☆";}
$title = "$rating-star ratings are for {$key[$rating]}. Ratings are a highly subjective opinion, and subject to revision at any time. If you think this paper has been incorrectly rated, please let me know.";
return "<span title='$title'>$stars</span><span class='pupb lighter-link' onClick='toglDispId(\"bibrat\")'>?</span><span class='pupw'id='bibrat'>$title<span class='pupx' onClick='toglDisp(this.parentElement)'></span></span>";
}

function logToFile($logfile, $msg)  {
    if (is_array($msg)) {
        $msg = json_encode($msg);
    }

	// this function may be called in the context of either a server request or command line without, so it has to know how to find the file
	// defaults to logging to logs dir
	global $_ROOT; if (!isset($_ROOT)) $_ROOT = getenv('HOME') . '/painsci';  // in the edge case of CLI context, $_DEVROOT is empty because it's based on $_SERVER which does not exist in that context, so we get the devroot this way
	if (file_exists("$_ROOT/logs/$logfile")) { // only do this if we can find the file
		$fd = fopen("$_ROOT/logs/$logfile", "a");  // open file for appending to
		$str = "[" . date("Y-m-d H:i:s", time()) . "] " . $msg;  // pre-pend date/time to message
		fwrite($fd, $str . "\n"); // write string
		fclose($fd); // close file
		}
	else echo "where?";
	}

function printTestimonials ($citekey, $num = 30, $max_length = 300) {
	global $sources;
	$searchResults = $sources->findRecordsThatContain('type', 'testimonial'); // look for records containing that filename
	if (!$citekey) $citekey = 'all';
	$searchResults = $sources->findRecordsThatContainItems('re', $citekey); // Get a test list of the testimonials that contain the citekey for the current landing page
	$x = 0;
	if ($searchResults) {
		foreach ($sources->results() as $entry) {
			if (wordCounter($entry->get("abstract")) > $max_length) continue;
			if (++$x > $num) return;
			echo $entry->expand("[testimonial]<br>\n\n");
			}
		}
	}

function printTestimonials2 ($citekey, $num = 30, $max_length = 300) {
	global $sources;
	if ($citekey=="frosho") $max_length = 66; // hack! not enough short testimonials for pf, but if I increase the word count I get too many long testimonials for other pages
	if ($citekey=="pf") $max_length = 35; // hack! not enough short testimonials for pf, but if I increase the word count I get too many long testimonials for other pages
	if ($citekey=="shins") $max_length = 35; // hack! not enough short testimonials for pf, but if I increase the word count I get too many long testimonials for other pages
	$searchResults = $sources->findRecordsThatContain('type', 'testimonial'); // look for records containing that filename
	if ($citekey) $searchResults = $sources->findRecordsThatContainItems('re', $citekey); // Get a test list of the testimonials that contain the citekey for the current document (landing page or article)

	if ($searchResults) {
			$x = 0;
		foreach ($sources->results() as $entry) {
			if (wordCounter($entry->get("abstract")) > $max_length) continue;
			$re = $entry->expand("[re]");
			$res = arraynge($re, ', ');
//			printArr($res);
			global $tutorials; $tutorials[] = 'eboxedset'; // add eboxed set to the list of acceptable citekeys when none is specified
			if (count(array_intersect($res,$tutorials)) == 0) continue;
			if (++$x > $num) return;
			$id = strip_tags($entry->expand("[testimonialid]"));
			echo $entry->expand("<p class='testimonial'><strong style='font-size:1.2em;margin-right:.1em; vertical-align:-.1em'>“</strong>[rawabstract]<strong style='font-size:1.2em;margin-left:.1em; vertical-align:-.1em'>”</strong> <span style='color:#999'>~&nbsp;$id</span></p>\n\n");
				}
			}
	}

// Self-explanatory
function straightenQuotes($string) {
	$search = array("’", "‘", "“", "”");
	$replace = array(	"'", "'", '"', '"');
	$string = str_replace($search,$replace,$string);
	return $string;
	}

/* replaces most characters in email address with asterisks */
function obfuscateEmail($email, $numchars = 3) {
	$emailArr = str_split($email);
	foreach ($emailArr as $char) {
		$x++;
		if ($char == '@') {$result .= $char; $domain = true; continue;}
		if ($domain)  {$result .= $char; continue;}
		if ($x < $numchars) {$result .= $char; continue;}
		if ($x % 3 === 0) {$result .= $char; continue;} // echo every other character
		if ($x == count($emailArr)) {$result .= $char; continue;}
		$result .= "•";
		}
	return $result;
	}

// calculate the mean of an array of values
function arrAverage ($values) {
	return array_sum($values)/count($values);
	}

// calculate the variance deviation in click gaps
function arrVariance ($values) { // variance = average of the squares of the differences between the individual and average value
	$avg = arrAverage($values); // get the array mean
	foreach($values as $val)	$diffsSquared[] = ($val-$avg)*($val-$avg); // build an array of the squares of the differences between each value and the average value
	return array_sum($diffsSquared)/(count($diffsSquared)-1);
	}

// calculate the standard deviation (square root of variance) of an array of values
function arrStdev ($values) {
	return sqrt(arrVariance($values));
	}

// calculate the average deviation f an array of values
function arrAveDev ($values) { // avedev = average of the differences of a set of numbers from their average
	$avgVal = arrAverage($values);
	foreach($values as $val) $diffs[] = abs($val-$avgVal); // build an array of differences between individual values and the average for the whole set; abs so we're working only with positive integers
	return arrAverage($diffs);
	}
	
function notifyMe($message, $app_token, $sound = "pushover") { // notify myself via Pushover API, see https://pushover.net/api
	// echo "posting message '$message' to app '$app_token'<br>";
// if (defined('MODE_DEV')) if (MODE_DEV) return; // no need for push notifications in dev
	curl_setopt_array($ch = curl_init(), array(
	  CURLOPT_URL => "https://api.pushover.net/1/messages.json",
	  CURLOPT_POSTFIELDS => array(
		"token" => $app_token, // app_token, different for each defined Pushover “app”
		"user" => "uLVLw63bazdTtdFgPtucVKZLqPdQQL", // USER_KEY, same for all my Pushover apps, see “Your User Key,” prominent in Pushover dashboard
		"title" => $message, // the API uses the name of the app if a title isn't specified; but on iOS, the title displays without unlock
		"message" => $message,
		"sound" => $sound 
	  ),
	  CURLOPT_SAFE_UPLOAD => true,
	  CURLOPT_RETURNTRANSFER => true,
	));
	curl_exec($ch);
	curl_close($ch);
	}

function linky($citekey, $anchor) { // echo text with a link to a citekey only if the current page is different
	global $CK;
	if ($citekey == $CK) echo $anchor;
	else {
		$url = citation($citekey, "[url]");
		echo "<a href='$url'>$anchor</a>";
		}
	}



$smallwords = "a abaft abeam aboard about above absent across afore after against along alongside amid amidst among amongst an and anent apropos around as aside astride at athwart atop barring before behind below beneath beside besides between beyond but by chez circa concerning despite down during else except excluding failing following for from given if in including inside into is like mid midst minus modulo near next nor notwithstanding of off on onto opposite or out outside over pace past per plus pro qua regarding round sans since than the then through throughout times to toward towards under underneath unlike until unto up upon versus via vice vis-a-vis when with within without worth"; // mostly prepositions, a few others

function isBot($user_agent) {
	$bots = ['ahrefsbot', 'yandexbot', 'superfeedr', 'bingbot.htm', 'googlebot', 'semrushbot', 'zoominfobot', 'duckduckbot', 'seznambot', 'pinterestbot', 'twitterbot', 'dotbot', 'feedbot', 'applebot', 'dotbot', 'mj12bot', 'rogerbot'];

    $user_agent = $_SERVER['HTTP_USER_AGENT'];

    return array_filter($bots, function ($bot) use ($user_agent) {
        return strpos(strtolower($user_agent), $bot) !== false;
    });
}

/* Builds a simple list of headings from sections found only in the members-only section of a members-only article. To print only the list, suppress the heading with default_heading=true. */
function makeTOCpreview($content, $default_heading = true) { 
	global $structured;
	$toc = '';
	// warning: multiple member areas (e.g. if there's also an audio embed) have the potential to make it very difficult to accurately detect the start and end position of the main member area
	$startPos = mb_strpos($content, 'makeTOC');
	$endPos = mb_strpos($content, '#memberContent-end'); 
	// var_dump($startPos, $endPos);
	$membersOnlyContent = mb_substr($content, $startPos, $endPos-$startPos);
	//	var_dump(htmlspecialchars($membersOnlyContent));
	if ($structured)
		preg_match_all('|"head" =>\s*"(.+?)",|', $membersOnlyContent, $matches);
	 else
		preg_match_all('|<h[234].*?>(.+?)</h\d>|', $membersOnlyContent, $matches);
	foreach ($matches[1] as $heading) $toc .= "<li>$heading</li>";
	$toc = "<ul id='tighter'>$toc</ul>";
	if ($default_heading == true) echo "<h3>PREVIEW: Headings in the members-only area…</h3>\n\n";
	echo $toc;
}

function makeTOC($content) { 

/* 2022-05-12 — This builds a table of contents from HTML source, called like so:  

<?php makeTOC(file_get_contents($_pathdocAgnostic)); ?>

Significant limitations:
* does not understand structured docs, this is for my simpler pages only
* only H2 with id attributes
* ALL h2s may include some unwanted sections, #2do: define more excluded meta sections
* #2do: only use headings within document markers
* the whole concept might be flawed, perhaps Alpine would be better

*/

	global $structured;
	$toc = '';
	preg_match_all('|<h2.*?id=["\'](.+?)["\'].*?>(.+?)</h\d>|', $content, $matches);
	array_shift($matches);
//	printArr($matches);
	$headingsNum = count($matches[0]);
	
	while ($x++ < $headingsNum) {
		if ($matches[0][$x-1] == "notes") continue; // exclude meta sections
		if ($matches[0][$x-1] == "updates") continue; // exclude meta sections
		if ($matches[0][$x-1] == "rr") continue; // exclude meta sections
		$toc .= "<li><a href='#{$matches[0][$x-1]}'>{$matches[1][$x-1]}</a></li>";
		}
	$toc = "<ul id='tighter'>$toc</ul>";
	echo $toc;
}


function memberContentBadge() {
global $mcb; ++$mcb;
echo "<span class='pupb' onClick='toglDispId(\"mcb$mcb\")'>MEMBERS</span><span class='pupw' id='mcb$mcb'>This article contains a members-only area. There are ten large members-only areas (highlighted items) scattered around the site, plus a growing selection of articles with smaller members-only sections. In most cases, the exclusive content is digressive and interesting, a bonus for members, while the most useful/essential points remain freely available to all visitors. Most PainScience.com content is free and always will be.</em> <span class='pupx' onClick='toglDisp(this.parentElement)'></span></span>";
}




/** returns @boolean: true if current date is close to an event date  */
function trueNearDate($eventMonth, $eventDay, $daysBefore, $daysAfter) { // This function returns true when the current date is less than $daysBefore OR less than $daysAfter after an event date given as a month and a day (e.g. 12, 25 == Dec 25 == Christmas).

$nowStamp = time();
$nowDate = dateStd(time());

$eventDate = (int)date('Y', $nowStamp) . "-$eventMonth-$eventDay";
$eventStamp = parseDate($eventDate);

//var_dump($nowDate, $nowStamp);
//var_dump($eventDate, $eventStamp);

if ($nowStamp < $eventStamp) { // if the event is in the future...
	$daysUntil = ($eventStamp - $nowStamp)/60/60/24;
	if ($daysUntil <= $daysBefore)
	return true;
	}

if ($eventStamp < $nowStamp) { // if the event is the past...
	if (daysSinceTstamp($eventStamp) <= $daysAfter )
	return true;
	}
return false;
}

function str_replace_first ($needle, $replace, $haystack) {
$pos = strpos($haystack, $needle);
if ($pos !== false) {
    return substr_replace($haystack, $replace, $pos, strlen($needle));
}
else return $haystack;
}

/* Returns @string: Generates a "daypass", a concatenation of an enciphered filename and timestamp that temporarily grants access to member content on one page. See generate-daypass.php. */
function generateDaypass ($filename) {
	$encipheredFilename = encipherFilename($filename);
	$encipheredTimestamp = encipherTimestamp(time()); // encode the current timestamp
	//echo "Finally, concatentate $encipheredFilename & $encipheredTimestamp to get the pass key:<br><strong>{$encipheredFilename}{$encipheredTimestamp}</strong><br>";
	return $encipheredFilename . $encipheredTimestamp; // return the pass
	}

/* Returns @string: Generates a trivially obfuscated string from a fragment of a filename */
function encipherFilename($filename) {
	$testing = false;
	if ($testing) echo "Generating a day-pass for: $filename<br>";

	$pathinfo =  pathinfo($filename);
	$filename = $pathinfo['filename'];
	if ($testing) echo "Remove the extension: $filename<br>";

	$filename = str_replace("-", null, $filename); // hyphens are the only "special" characters found in my filenames
	if ($testing) echo "Remove hyphens: $filename<br>";

	$filename = substr ($filename, 2, 8);
	if ($testing) echo "Extract 8 chars from position 3-10: $filename<br>";

	$x = 0;
	$filename = str_split($filename);

	foreach ($filename as $char) {
	$mb_ord = mb_ord($char)-32; // get the codepoint for the capital letter
	$encipheredFilename .= $mb_ord;
	$encipheredFilenameReadable .= " $mb_ord";
	}

	if ($testing) echo "Convert each character to codepoint-32: $encipheredFilenameReadable<br>";

	return $encipheredFilename;
	}

/* Returns @string: Generates a trivially obfuscated timestamp for a given timestamp, defaulting to now. */
function encipherTimestamp($timestamp = null) {
	$testing = false;
	if (!$timestamp)  $timestamp = time();


	if ($testing) echo "Current timestamp: $timestamp<br>";

	$timestamp = substr_replace($timestamp, "00000", 5, 6);
	if ($testing) echo "Reduce the precision of the timestamp: $timestamp (replace last five digits with zeroes)<br>";

	if ($testing) echo "Which converts to: " . dateTime24($timestamp) . " (should be roughly 1 day ago)<br>";

	$timestamp = $timestamp/100000;
	if ($testing) echo "Divide by 100,000 to truncate: $timestamp<br>";

	$timestamp = strrev($timestamp);
	if ($testing) echo "Aaand reverse that string: $timestamp<br>";

	return $timestamp;
	}

/* Returns @string: tidies whitespace in complex multiline strings by collapsing runs of horizontal and vertical whitespaces */
function tidyWhitespace ($string) {
	$string = preg_replace("| {3,10}|", " ", $string); // reduce runs of spaces to 1
	$string = preg_replace("|\n\s+\n|", "\n\n", $string); // removed lines that containing only spaces, tabs, etc
	$string = str_replace("\n", "\n\n", $string); // standardize vertical spacing for nice diffing, make sure every break has at least 2 linefeeds
	$string = preg_replace("|\n{3,10}|", "\n\n", $string); // reduce runs of linefeeds to 2
	return $string;
	}
	
/* return an array of all the @mine type citations in a given @mine type page */
function getInternalCites ($citekey) {
//	var_dump ($citekey);
	$citekeys_arr = array();
	global $sources;
	$citesnum_mypages = 0;
	$record = $sources->safeGet($citekey); // get the record for the given citekey
	$citesnum = $record->get("citesnum"); // get the total count all kinds of cites
	if ($citesnum == "") return false; // abort, because there are no citations of any kind
	$allcites = $record->get("cites"); // get the list of harvested citekeys, e.g. "epsom, lbp, ways_to_hurt"
	$allcites_arr = arraynge($allcites, ", "); // make an array from the list
	foreach ($allcites_arr as $ck) {  // go through the list
		$record = $sources->safeGet($ck); // get the record for e.g. §gru or §epsom
		// printArr($allcites_arr); echo "$citekey cites $ck which is type==" . $record->get('type'); exit;
		if ($record->get('type') == 'mine') { // if it’s an @mine record…
			$citekeys_arr[] = $ck; // save it
			$citesnum_mypages++; // count it
			}
		}
	if ($citesnum_mypages == 0) return false;
	return $citekeys_arr;
	}

function echoDev ($string) {
	if (MODE_DEV) echo "<code class='color_soft_red'>$string</code>";
	else return false;
	}
	
function embed_audio ($filename, $paywalled = false, $figcaption = "Audio version of this section:") {
// 2023-12-22 — Just got a start on using this function, but it’s still too simple to use for all my audio embedding needs.  Audio embeds can be featured (audio for a whole post/article) vs. minor (just a sub-section), and they can be independently paywalled or not.  This function so far only puts out an unpaywalled minor audio embed, and I need to figure out a solution for the others before I can start using it more widely.
$url = "/media/$filename";
if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $url)) error("Audio file does not exist: '$filename'");
//$url = "https://www.painscience.com" . $url;

$embed = <<<embed
	<figcaption class="font_accent color_gray_blue"><em>$figcaption</em></figcaption>
	<audio controls  src="$url"><a href="$url">[DOWNLOAD]</a></audio>
	embed;

if ($paywalled) {
	printPaywallMarkup ('nonmember', 'start'); #paywall #cta_join-start <#====#>
	echo <<<audio_teaser
	<aside class="meta meta-small audio_embed no-print font_accent" style="position:relative;padding-bottom:1em">
		<p><em>This content has an audio version for PainSci members. To unlock, <a href='/membership.php'>join now</a> or login. <span class='pupb' onClick='toglDispId("login")' style='position:absolute;bottom:-.6em; left: 50%;  transform: translateX(-50%);'>LOGIN</span></p></aside>
	</aside>
	audio_teaser;	
	printPaywallMarkup ('nonmember', 'end'); #paywall #cta_join-end <#====#>
	printPaywallMarkup ('member', 'start'); #paywall #memberContent-start
	echo <<<audio_embed
		<aside class="meta meta-small audio_embed no-print">
		$embed
		</aside>
	audio_embed;	
	printPaywallMarkup ('member', 'end'); #paywall #memberContent-end
	}
else { // just the embed without teaser and paywall conditions
	echo <<<audio_embed
	<aside class="meta meta-small audio_embed no-print">
		$embed
	</aside>
	audio_embed;
	}
}

/**
 * https://stackoverflow.com/a/7135484/470749
 * @param string $path
 * @return int
 */
function getDurationOfAudioInSecs($path) {
    $time = getDurationOfAudio($path);
    list($hms, $milli) = explode('.', $time);
    list($hours, $minutes, $seconds) = explode(':', $hms);
    $totalSeconds = ($hours * 3600) + ($minutes * 60) + $seconds;
    return $totalSeconds;
}

/**
 * 
 * @param string $path
 * @return string
 */
function getDurationOfAudio($path) {
    $cmd = "ffmpeg -i " . escapeshellarg($path) . " 2>&1 | grep 'Duration' | cut -d ' ' -f 4 | sed s/,//";
    return exec($cmd);
}

function addPeriodWhereNeeded ($string) {
	// $string = "Built for pressure: How thick is kneecap cartilage?"; var_dump(substr($string, -1));
	$string = trim($string);
	$search = explode(" ","! ? ’ ” \" \' : ;"); // make an array from a list of common terminal punctuation marks
	$terminator = ''; // default to empty string
	if ( !in_array(substr($string, -1), $search)) // if the last character in the string is not one of the terminal marks…
		$terminator = ".";
	return $terminator;
	}

function strip_tags_p ($html) {
	return preg_replace("|<[/]*p>|", null, $html);
}


function echoDebug ($debugmessage) {
	global $debugEchoes;
	if (!$debugEchoes) {
		return;
		}
	else {
		echo $debugmessage;
		}
	}
