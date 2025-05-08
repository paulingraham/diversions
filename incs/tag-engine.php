<?php /*

TAG ENGINE: making `tags easier to use, for PubSys and now PainScience too!

Tagging is a powerful organizational tool. And yet a simple list of tags quickly becomes unwieldy as it grows. Just like the data they are applied to, tags themselves need organizing — there are different types and categories of tags, different forms of tags, aliases for ease of data entry, and so on.

Goals for tagging:

•  efficient post tagging
	✓	synonyms (and special forms for presentation, eg short, long, abbreviated, etc)
	✓	case, space, and symbol insensitivity
	✓	automatic addition of all parent tags
	✓ simple plural and singular synonyms, eg 'cat' matches 'cats' and vice versa
	✓	automatic tags implied by other metadata
	✓	automatic tags inferred from content
•  efficient tag organizing
	✓	very simple text file data format, easily to read and edit
	✓  canonicalization of data file (standardized, alphabetized, metadata)
	✓	new tags added to the database automatically
	✓	in the db, shorthands for the longer field names (desc, syn/s instead of description, synonyms)
	✓ show tag usage in data file
	✓ plain tag lists in post header for easy auditing/diffing
	•  automatically generate quick-reference guides, reports
		✓	condensed dictionary
		•	untagged, under-tagged posts
	✓ tags for tags: tags like “core” and “category” for classification of tags themselves	
	✓	shorthands for tag types:
		✓ #adhoc tags are “for this post only,” excluded for tag management
		✓ *orphan tags have suppressed ancestry
		✓ .admin tags are for internal use, functional but never displayed, e.g. a project or subtopic tag
		✓ _category tags
	

Example tag data entry:

quackery [auto tag count] 			< the main or “true” form of the tag, with correct case & punctuation
	notes: My favourite tag. 		< private notes about the tag, eg clarifying what kind of content
	description:							< public description of the tag, eg appears in tooltips on mouseover
	short: quackery						< a shorter form of the tag
	long: qkry								< a longer form of the tag
	abbr: quack 							< abbreviation of the 
	synonyms:	snake oil				< usage of these terms will always be replaced by the main tag
	parents: skepticism				< significant umbrella categories, added to the tag list for the item
	related: reflexology				< other related terms, replaced by the main

(More about “related”: related terms are a trivial sub-type of synonym, semantic hair-splitting with little practical importance.  Synonyms are alternate forms of the very same word as the tag, or extremely similar concepts, like “snake oil” and “quackery.”  Related terms are not true synonyms, but terms I want to lump in with the main tag. They may be technically a parent, or a child, but not worth defining as such.  In practice, this will probably be the bucket where I put the terms that people tend to search for or ask about, a kind of redirecurly?)

TAG ENGINE GLOSSARY

	GIVEN TAG: any tag as written in the post, may or may not match anything in the tag database
	DEFINED TAG: tag with metadata
	NEW TAG: given tag without metadata
	TRUE TAG: the canonical form of the tag, with proper case, space, and punctuation
	KEY TAG: tag stripped of case, space, and punctuation
	THESAURUS: array of all known synonyms (synonym => true)
	POST TAGS: main list of tags for a post
	ADHOC TAGS: tags for this post only (denoted with # prefix)
	ADMIN TAGS: tags for internal use only (denoted with . prefix)
	ORPHAN TAGS: tags with suppressed ancestry (denoted with *)
	TAG TAGS: tags that classify tags, not content (not used on posts, used on tags!)
	

Code overview

	if there’s a tag db data file
		parse the simple, editable tag data file into an array keyed with simplified tags
		make a tag thesaurus

	for every post
		lookup every given tag:
			does it match a defined tag or synonym?
			if not (simple)
				add it to the post tags
				add it to the new tags field
			if it does (complicated!)
				add it to the post tags
				iterate the tag count
				does it have parents?
					for each parent
						is the parent defined?
							add the true parent to the post ancestry tags
							does it have parents? [recursion]
						if not
							add it as-is to the post ancestry tags

	update the tags
		for every post
			add any new tags to a main list of new tags
		add new tags to database
		generate and save canonicalized tag data file
		generate and save quick reference guide


TO DO

need to document, now that things are pretty settled
feature: parent tags need _privacy too
feature: teach test-tags to expand arbitrary lists of given tags (e.g. from a string, not from the database)
mx* orphan syntax failed, but *mx succeeded, perhaps because it was at the end of the given tags
redunancy: it’s routinely redundant to list parents, e.g. “science news” already contains “science”
do we really need to explicitly state a simple substr as an abbreviation?  can we just check for substrings?  or will there be unintended consequences, false positives?
merge description or syns into tag line in data file? no, unnecessary data entry confusion for minimal reward
# separator instead of comma? no, significant dev challenge, and hashtags harder to type than comma
PS COMPATIBILITY/OPPORTUNITIES
	transition from literal array to simple text data file (without breaking anything ELSE)
	can build tag indexes but … seems lame for them to be ONLY for blog posts
	extract tags from database (e.g. if I’m blogging about something in the database, just get the tags from the item in the database)
	


REGARDING PARENTS VS CHILDREN

In the first alpha version of this system, I listed tag CHILDREN in the data (e.g. children of “vegetables” are “carrots, peas … ”), and alogrithmically determined the parentage for carrots by looking for tags that specify carrots as children.  This was programatically expensive, but data entry was easy. Then I switched to listing parents in the data file, which is programatically efficient (it’s easy to lookup parents when they have been explicitly listed), but it makes for redundant data entry (you have to list “vegetables” as a parent for every single vegetable tag). I don’t really know what the right answer was.  Ultimately, it might be feasible to have it both ways: to list children OR parents, and let the tag engine canonicalize the data so that both sides of the equation are represented.

CATEGORIES

A tag prefixed by an underscore is a “category.”  (The prefix is 100% non-functional: it’s stored, but never actually used in the tag-engine.  It’s parsed out of the input, and put back in when the tags are canonicalized. The sole purpose of the underscore is data-entry convenience in BibDesk.)  What does “category” mean?  Isn’t every tag a category?  It’s an imprecise distinction.  Category-ness is a rough expression of the importance of the tag, which is roughly an intersection of the number of items it applies to, how directly it applies to them.  The “back pain” tag is not only applied to more items than “elbow pain,” but the items it is applied to are mostly about back pain, whereas elbow pain is typically a peripheral or sub-topic.  Thus back pain is definitely a category, but it would be difficult to determine it algorithmically.   The underscore tags also began life in my file system as tags specifically referring to the TYPE of content, as opposed to what it was about or its tone.  To some extent, types of content tend to be large or important categories. In time, I may replace the category syntax with a more granular tag rating system.


<##>

*/

$tag_fields = array ("notes", "description", "short", "long", "abbr", "synonyms", "parents","ssf","children","tags","related");

/** returns @array, tags: reads tags data file and makes an array of tags, plus a more specialized “thesaurus” */
function getTags ($tag_fn = false) {
// more detail: reads and canonicalizes the user-editable tags file (tags-sitename.txt), creates a complete array of tags ($tags) and a more specialized “thesaurus” array ($tag_thesaurus) for lookups by synonym
	global $sitecode;
	if ($tag_fn) $journalling = false; else $journalling = true; // in the context of PubSys, we want getTags to journal its progress when running a make; but if getTags is called from a main PS page, which will always require specifying the tag file, journalling is a problem; so we set the flag here, and check it before journalling in this function
	if (!$tag_fn) $tag_fn = "guts/tags-{$sitecode}.txt";
	if (file_exists($tag_fn))
		$lines = file($tag_fn); // we want to preserve LFs as entered in the header, so instead of FILE_SKIP_EMPTY_LINES here, it’ll be some trimming below
	else {
		echo "tag data file '$tag_fn' not found";
		return false;
		}

	global $tag_fields;

	if ($journalling) journal("reading tags from file: $tag_fn",1,true);

	$tags_header = "";
	foreach ($lines as $line) {
	if (!isset($finished_header)) { // if we’re not yet finished reading the header …
		$tags_header .= $line; // build a duplicate of the header
		if (inStr("*****", $line))  // the end of the header is marked by a row of at least 5 asterisks
			$finished_header = true; continue; // the header is so over, so move on
		}
	else { // if reading data (done with header)
		$line = rtrim($line); // trim line feeds (trim from right only because tabs on the left are meaningful in this format)
		if (trim($line) == "") continue; // ignore empties; didn’t want to do this in the header, but we do for the data
		$lines_tags[] = $line; // build an array of lines
		if (strpos($line, "\t") !== 0) { // a line that does not start with a tab is a tag ??? is this wise? should there be a more definite syntax?
			$line = preg_replace("@ \[.*?$@", null, $line); // remove the tag count from the line
			$current_tag = $line; // now working on a new tag
			// now going to make a KEY from the tag: an all-lower case, no-spaces version of the tag
			$current_key = simplify($line); // remove punctuation, whitespace, and uppercase
			$tags[$current_key]["key"] = $current_key; // this is redundant, but it makes some chores easier (eg getTag can use a field lookup to return either the key or any other version of the tag)
			$tags[$current_key]["true"] = ltrim($line,"_"); // this is where the true tag is set in stone (removing the underscore prefix from tags what have it, about a dozen)
				if ($journalling) journal ("getting tag $line",2);
			// so now we have an array item keyed with the simplified tag, and storing the true tag
			// e.g. the true tag “Walking Dead” is now stored like so:
			// array ("walkingdead" => array ("true" => "Walking Dead"))
			continue; // finish (could proceed with an ‘else’)
			} // close if untabbed line
		if (strpos($line, "\t") == 0) { // if a line DOES start with a tab, it’s data about the current tag (this could be “else” instead of another if)
			if (inStr("<##>",$line)) continue; // move on if there’s no data (<##> indicates an empty field)
			$parts = explode("=", $line); // split the line on colon
			$tag_field = trim($parts[0]); // get the field name from the string preceding the colon
			$tag_data = trim($parts[1]); // and get the field data from the 2nd half
			if ($tag_data == "") continue;  // move on if there’s no data 
			if ($tag_field == "syns") 	$tag_field = "synonyms"; // expand shorthand for synonyms
			if ($tag_field == "rel") 	$tag_field = "related"; // expand shorthand for synonyms
			if ($tag_field == "syn") 	$tag_field = "synonyms"; // expand shorthand for synonyms
			if ($tag_field == "parent") 	$tag_field = "parents"; // expand shorthand for parents
			if ($tag_field == "par") 	$tag_field = "parents"; // expand shorthand for parents
			if ($tag_field == "pars") 	$tag_field = "parents"; // expand shorthand for parents
			if ($tag_field == "desc") 	$tag_field = "description"; // expand shorthand for description
			if (!in_array($tag_field, $tag_fields)) continue; // #2do: error for incorrect fields
			if (	$tag_field == "synonyms" or // for multi-item fields
					$tag_field == "related" or
					$tag_field == "parents") { 
				$tag_data = arraynge($tag_data, ','); // get an array of CSVs
				natcasesort($tag_data);
				}
			$tags[$current_key][$tag_field] = $tag_data; // add the data to the array
			} // endif: tabbed line
		} // endelse: reading data
	} // endloop: lines

	ksort($tags); // sort the tag database by key
	$tag_count = count($tags); // get a count
	// echo "&nbsp;($tag_count tags found)";

	// now that we we have the data in a lovely array, make a thesaurus: an array of all possible synonyms pointing to true tags
	foreach($tags as $tag => $td) {
			if (isset($td["synonyms"]))
				foreach ($td["synonyms"] as $synonym) {
					$GLOBALS['tag_thesaurus'][simplify($synonym)] = $tag;
					}
			if (isset($td["related"]))
				foreach ($td["related"] as $related) {
					$GLOBALS['tag_thesaurus'][simplify($related)] = $tag;
					}
			if (isset($td['short'])) $GLOBALS['tag_thesaurus'][simplify($td['short'])] = $tag;
			if (isset($td['abbr'])) $GLOBALS['tag_thesaurus'][simplify($td['abbr'])] = $tag;
			if (isset($td['long'])) $GLOBALS['tag_thesaurus'][simplify($td['long'])] = $tag;
			$GLOBALS['tag_thesaurus'][$tag] = $tag;
		} // end tag loop
	// add (very) simple plural and singular forms of all keys
	foreach ($GLOBALS['tag_thesaurus'] as $key => $tag) {
		// this really only covers the simpleset possible plural/singular variants
		// e.g. if "cats" is in the tag db, "cat" will match, and vice versa
		// many keys will be pointless corruptions (e.g. mailapps, wittys, mxs)
		// the assignment may pointlessly overwrite existing definitions
		// if the key does not end in s, make a new one that does	
		if (strrpos($key, 's') !== strlen($key)-1)
			$GLOBALS['tag_thesaurus'][$key . "s"] = $tag;
		else // or, if it does, make one that doesn't
			$GLOBALS['tag_thesaurus'][rtrim($key,"s")] = $tag; /**/
			}


	ksort($GLOBALS['tag_thesaurus']);

	$GLOBALS["tag_db_header"] = $tags_header; // stored where the updateTags function can get to it

/* echo "<pre><span class='boogers'>$tag_count tags<br>";
	//echo $tag_qrg_str;
	//echo $tags_header;
	//echo $tags_str;
	//print_r($lines_header);
	//print_r($lines_tags);
//	print_r($tags);
	//	print_r($GLOBALS['tag_thesaurus']);
	echo "</span></pre>"; /* */

	return $tags;
	}


/** returns @string, html list: post tags marked up tag list */
function markupTags ($arg_tags) {
	$arg_tags = arraynge($arg_tags,',');
	global $tags;
	if (!$tags)
		foreach ($arg_tags as $arg_tag) $tags_str .= "<li class='tag'>$arg_tag</li>";
	else {
		foreach ($arg_tags as $arg_tag) {
			if (isset($tags[simplify($arg_tag)]['short'])) $arg_tag = $tags[simplify($arg_tag)]['short']; // use a short version, if available
			if ($tags[simplify($arg_tag)]['#'] > 5) {
				$tags_str .= "<li class='tag'><a href='tag-" . titleToFn($arg_tag) . ".html'>{$arg_tag}</a></li>";
				}
			else
				$tags_str .= "<li class='tag' title='Not enough posts use this tag.'>$arg_tag</li>";
			}
		}
	return "<ul class='tag_list'>$tags_str</ul>";
	}

/** returns @string, html list: post tags marked up with hash tags and space-delimited, very simple */
function markupTagsHashes ($arg_tags) {
	$arg_tags = arraynge($arg_tags,',');
	foreach ($arg_tags as &$arg_tag) $arg_tag = "#{$arg_tag}";
	return implode(" ", $arg_tags);
	}


/** returns @string, true tag: looks up a given tag, returns true tag (default) or other (eg short, abbr) */
function getTag($tag_given, $version='true') {
	global $tags;
	$tag_simpl = simplify($tag_given); // probably already simplified, but no harm in making sure
	// case 1: the given tag is a direct match with a true tag
	if (isset($tags[$tag_simpl])) {
		return $tags[$tag_simpl][$version];
		}
	// case 2: the tag-as-written is a synonym, short version, long version, abbreviation, related
	if (isset($GLOBALS['tag_thesaurus'][$tag_simpl])) {
		return $tags[$GLOBALS['tag_thesaurus'][$tag_simpl]][$version];
		} 	
	return false;
}


/** returns @string, csv, tags: rich tag set (parents, synonyms, etc) derived from a list of given tags, independently or for a post*/
function extractTags ($tags_str, $post = false) { 
// more detail: for each post/item, extractTags generates a final list of tags from various sources (exact matches, synonyms, and parents of matches, and more in the future) by comparing the tags-as-written to the canonical tags.  The post data is updated with new fields for each type of tag, as well as a main tag field including all tags.
	// if (inStr("daffod",$post["title"])) echo "!!!";
	$tags_given = arraynge($tags_str, ","); // split the tag list into an array
	global $tags;
	$tags_final = array(); $tags_parents = array(); // initialize arrays
	if ($post) {
		$tags_inferred = inferTags($post); // infer tags from post content and metadata
		$tags_given = array_merge($tags_given, $tags_inferred);
		}
		
	foreach ($tags_given as $tag_given) { // work through given tags
		$tag_given2 = $tag_given;
		$orphan = false; if (inStr("*", $tag_given)) { // look for orphan tags
			$tag_given2 = str_replace("*", null, $tag_given); // marked by * anywhere
			$orphan = true;
			}
		$admin = false; if (strpos($tag_given, ".") === 0) { // look for admin tags
			$tag_given2 = str_replace(".", null, $tag_given); // marked by . anywhere
			$admin2 = true; // hmm, why is this admin2? got to be a bug! #2do
			}
		if (getTag($tag_given2)) { // if given tag matches a defined tag
			$tmp = getTag($tag_given2); // defaults to getting the TRUE tag
			if (strpos($tmp, ".") === 0) $admin = true; // another check for admin tags; if there is no . in the given tag, it is still recognized as an admin tag
			if (in_array($tmp,$tags_final)) continue; // abort if we already have the tag for some reason (eliminates some minor sources of redundancy, such as the possibility that an inferred tag was added that already had it; I think it’s probably easiest to block the redundancy here
			if ($admin) $tags_admin[] = $tmp; else $tags_final[] = $tmp; // add the true tag to the final array of tags for the item
//			$tags[simplify($tmp)]["#"]++; // iterate the tag usage counter
			if (!$orphan) // add any parents, if it’s not an orphan tag (marked with *)
				$tags_parents = getTagParentsNew($tag_given2, $tags_parents);
			} // endif: defined tags
		else { // if tag is unrecognized
			if (strpos($tag_given2, "#") === 0) { // an initial # denotes a joke tag (or just an explicitly unmanaged tag)
				$tags_adhoc[] = $tags_final[] = ltrim($tag_given, "#"); continue; // nothing else to do
				}
			$tags_new[] = $tag_given; // use the original given tag, including symbols like _
			} // endif: new tags
		} // tag loop

	$newtags["tags_given"] = implode(",", $tags_given); // store the original tags (mostly for troubleshooting)
	if ($tags_parents) 	$newtags["tags_parents"] = implode(",", array_unique($tags_parents));
	if (isset($tags_admin)) 	{
		$newtags["tags_admin"] = implode(",", $tags_admin);
		foreach ($tags_admin as $tag) // counter admin tags now that all the tags for the post are in, dupes eliminated
			if ($tags[simplify($tag)]) { // checking the database prevents counting of admin-adhoc tags (unlikely, but maybe)
				if (!isset($tags[simplify($tag)]["#"]))
					$tags[simplify($tag)]["#"] = 1;
				else
					$tags[simplify($tag)]["#"]++; // iterate the tag count field (fieldname = #)
				}
		}
	if (isset($tags_inferred))	$newtags["tags_inferred"] = implode(",", $tags_inferred);
	if (isset($tags_adhoc)) 		$newtags["tags_adhoc"] = implode(",", $tags_adhoc); 
	if (isset($tags_new))			$newtags["tags_new"] = implode(",", $tags_new);
	$tags_final = array_merge($tags_final, $tags_parents); // add parent tags, if any
	if ($tags_final) {
		$tags_final = array_unique($tags_final); // eliminate duplicate tags
		foreach ($tags_final as $tag) // count tags now that all the tags for the post are in, dupes eliminated
			if (isset($tags[simplify($tag)])) { // checking the database prevents counting of admin-adhoc tags (unlikely, but maybe)
				if (!isset($tags[simplify($tag)]["#"]))
					$tags[simplify($tag)]["#"] = 1;
				else
					$tags[simplify($tag)]["#"]++; // iterate the tag count field (fieldname = #)
				}
		// sort($tags_final); // removed Dec 10, 2014: sorting was never really doing anything for the tags except tidying; removed to straightforwardly make way for meaningful data entry order: most applicable given tags are given first
		$newtags["tags"] = implode(",", $tags_final); // convert to csv
		}
	else $newtags["tags"] = null;
	if ($post) { // add the enhanced tags to the $post and return the post
		foreach ($newtags as $field => $data) $post[$field] = $data;
		return $post;
		}
	else return $newtags; // return the enhanced tags on their own
}

/** returns @array, post:  returns post with tags inferred from content/metadata */
function inferTags ($post) {
	$inf = array(); // initialize
	// INFERENCE RULES: all of these rules are idiosyncratic, and unusual in that code is being used to generate content.
	// the “review” tag should be used on posts that come from micropost files containing “reviews” in the filename, or posts with “review” or a star in the title:
	if (inStr("reviews",$post['source_file'])) 	$inf[] = 'review';
	if (inStr("review",$post['title'])) 				$inf[] = 'review';
	if (inStr("★",$post['title']))						$inf[] = 'review';
	// the “vocab” tag should be used on posts that come from micropost files containing “vocab” in the filename:
	if (inStr("vocab",$post['source_file'])) 		$inf[] = 'vocabulary';
	// posts with a priority of 9 or 10 should be assumed to be “best of”:
	if ($post['priority'] > 8)								$inf[] = 'best of';
	// posts are assigned a tag corresponding to their size
	// just add the “size” prefix added to the size code set in the get_post_size function
	// different scales for regular vs main pubsys use
	$inf[] = "size " . strtoupper($post['size']);
	// all micro-img posts are, by definition, tagged with “av”
	if ($post['post_class'] == "micro-img") 			$inf[] = 'av';
	// all posts with a featured link are tagged with “link”
	if ($post['url']) 											$inf[] = 'link';
	// all posts with a featured quot are tagged with “quotes”
	if (inStr("<!--tag:qt-->",$post["html"]))		$inf[] = 'quotes';
	$inf = array_unique($inf);
	return $inf;
	}

/** returns @array, tags: recursively lookup parents of given tag, return an array of parents */
function getTagParentsNew ($tag_given, $tags_parents) {
	global $tags; // echo "$tag_given<br>"; var_dump($tags); exit;
	$tags_parents_undef = array();	
	$echo = false; if ($tag_given == "") { // TESTING
		$echo = true;
		echo "<br><br>• Debugging output for ancestry of <b>{$tag_given}</b>… <br> Parents array:<br>";
		foreach ($tags_parents as $tag_parent) echo "{$tag_parent} … ";
		}
	if (isset($tags[getTag($tag_given,'key')]['parents'])) $parents = $tags[getTag($tag_given,'key')]['parents']; // try to get parents, using the tag of the given key
	if ($echo) foreach($parents as $parent) echo "Parents of given tag: —{$parent}— ";
	if (isset($parents)) { // if the tag has parents (recursion stops here when we run out parents)
		foreach ($parents as $parent) {
			if (getTag($parent)) {
				if ($echo) echo "… getTag found <strong>$parent</strong> …";
				$tags_parents[] = $tmp = getTag($parent);  // add it to the array of parents so far
				$tags_parents = getTagParentsNew(getTag($parent),$tags_parents); // recursion
				}
			else { // if the parent is an undefined tag
				$tags_parents[] = $parent;
				}
			} // parents loop
		} // endif: parents
	return $tags_parents; // return array of parents (possibly unchanged, if there were no parents)
	}


/** returns @multi: updates tag_db and makes tag QRGs  */
function updateTags() {
	journal("looking for new tags in posts",1,true);
	global $tags; if (!$tags) return; global $tag_fields;
	global $posts; global $sitecode; $tags_new = array();
	$tag_fn = "guts/tags-{$sitecode}.txt";
		
	foreach ($posts as $post) { // make an array of new tags found in posts, counting them along the way
		if (!$post['tags_new']) continue;
		$post_new_tags = arraynge($post['tags_new'], ",");
		foreach ($post_new_tags as $newtag) $tags_new[$newtag]++;
		// and perhaps adding them to tag_db right now? why wait?
		}
		
// foreach($tags_new as $tn => $no) echo "$no $tn<br>"; // print simple list of new tags

	// add new tags to the tag_db
	foreach($tags_new as $tn => $no) { // could do this above, instead of another loop?
			$tags[simplify($tn)]['true'] = $tn;
			$tags[simplify($tn)]['#'] = $no;
			if (strpos($tn, ".") === 0) $tags[simplify($tn)]['tags'] = "admin";
			journal("adding new tag “{$tn}” to tags",3,true);
		}
		
if (count($tags_new) == 0)	echo "(" . count($tags_new) . " new tags found)"; // unnecessary as long as we’re journalling individual new tag finds

	// now that we have all tags, old in new, we make some files from them
	foreach($tags as $tag => $td) { 
		
		// build a QRG (compact readable alphabetical list of tags and their synonyms)
			if (is_array($td['synonyms'])) $synonyms = implode(" ", $td['synonyms']);
			if (is_array($td['related'])) $related = implode(" ", $td['related']);
			$tag_qrg_str .= $td['true'] . "\t{$td['#']}\t{$td['short']}\t{$td['abbr']}\t{$td['short']}\t{$td['long']}\t{$synonyms}\t{$related}\n";
			unset($synonyms);	 unset($related);

		// canonicalization of data file
			if (inStr("category", $td["tags"])) $prefix = "_"; else $prefix = NULL; // add the underscore prefix back onto to the tag in the data file, for category tags only
			$tags_str .= "\n{$prefix}{$td['true']} [{$td['#']}]\n";
			foreach ($tag_fields as $tag_field) {
				if (is_null($td[$tag_field])) continue; // exclude this line to include empty fields
				$tags_str .= "\t" . $tag_field . " = ";
				if (is_string($td[$tag_field])) $tags_str .= $td[$tag_field] . "\n";
				if (is_array($td[$tag_field])) $tags_str .= implode(", ", $td[$tag_field]) . "\n";
				if (is_null($td[$tag_field])) $tags_str .= "<##>\n"; // could put <##> here, maybe
				} // end fields loop

		} // end tag loop

	// save tag files …
	
	// modify the header: add the quick reference guide to the tags
	$tag_qrg_str = "TAGS QUICK REFERENCE GUIDE\n\n" . $tag_qrg_str . "\n";

	$tag_qrg_fn = "guts/tags-qrg-{$sitecode}.txt";
	if (fileExistsNoChange($tag_qrg_str, $tag_qrg_fn)) {
		journal("tags quick-reference guide has not changed, <em>not</em> writing file: $tag_qrg_fn",2,true);
		}
	else {
		journal("<strong>tags quick-reference guide has changed</strong>, writing file: $tag_qrg_fn", 2, true);
		saveAs($tag_qrg_str, $tag_qrg_fn);
		}

	$tags_canonicalized = $GLOBALS["tag_db_header"] . $tags_str;
	if (fileExistsNoChange($tags_canonicalized, $tag_fn)) {
		journal("tags file has not changed, <em>not</em> writing file: $tag_fn",2,true);
		}
	else {
		journal("<strong>tags file has changed</strong>, writing file: $tag_fn", 2, true);
		saveAs($tags_canonicalized, $tag_fn);
		}	
		
/* echo "<pre><span class='boogers'>!!!<br>";
print_r($tags);
echo "</span></pre>"; /* */

	makeTagIndexes ();

	} // eofn updateTags

/** returns @files: makes tag index pages and files */
function makeTagIndexes () {
	if ($GLOBALS['ps']) return; // exit ƒ if this is PS
	journal("making tag index pages",2, true);
	global $tags; if (!$tags) return;
	foreach ($tags as $tag) {
		if ($tag['#'] < 6) continue; // exclude rare tags
		$tagged_posts = getPostsByTag($tag['true'], false); // get an array of posts with this tag; false param tells function not to bother find the true form of the tag via getTag, because we know we already have a true tag
//		foreach ($tagged_posts as $x) echo $x['title'] . '… '; exit;
		if (!$tagged_posts) continue;
		$ti_posts_table = make_post_index($tagged_posts); // generate a table of post links (make_post_index defaults to a table)
		$ti_page = renderPhpFile("guts/template-tag-index.php",false,true); // get the RENDERED contents of the template
		// #2do probably should use the short version of the tag, if available; easy but not super important
		$ti_page = str_replace('{$ti_tag}', $tag['true'], $ti_page); 
		$ti_page = str_replace('{$ti_tag_count}', $tag['#'], $ti_page);
		// why count()? in theory, we should know this from the # field for the tag in the main tag_db
		// in practice there are probably going to be discrepancies, so let's just count what we actually have
		$ti_page = str_replace('{$ti_posts_table}', $ti_posts_table, $ti_page);
		if ($tag['description']) { // if there's a tag description
			// title is  "# posts tagged '[tag]'"
			// description is [description]
			// append to H1
			$ti_page = str_replace('{$title_html}',"{$tag['#']} posts tagged {$tag['true']}", $ti_page);			
			$ti_page = str_replace('{$description}',"{$tag['description']}", $ti_page);
			$ti_page = str_replace('</h1>', "<br><span id=\"subtitle\">{$tag['description']}</span></h1>", $ti_page);			
			}
		else { // if there's no description
			// title is the long form of the tag
			//	description is  "# posts tagged '[tag]'"
			// nothing to append to H1
			$ti_page = str_replace('{$title_html}',"Tag: " . ucfirst($tag['true']), $ti_page);			
			$ti_page = str_replace('{$description}',"{$tag['#']} Writerly posts tagged '{$tag['true']}'.", $ti_page); // add it to the title element
			}
		// make filename, add filename to list of confirmed html files
		$tag_as_fn = titleToFn($tag['true']); // yep, yet another form of the tag!
		$fn = $GLOBALS['filenames'][] = STAGE . "/tag-{$tag_as_fn}.html";  // `path
		$preview_link = makePreviewLink($fn);
		if (fileExistsNoChange($ti_page, $fn)) {
			journal("ignoring unchanged #<strong>{$tag_as_fn}</strong> index page [$preview_link]", 2);
			continue;
			}
		journal("saving new/changed #<strong>{$tag_as_fn}</strong> index page [$preview_link]", 2, true);
		saveAs($ti_page, $fn);
		} // tag_index loop

	} // eofn makeTagIndexes

/** returns @string, link: make a preview link from a file name */
function makePreviewLink ($fn) { // #2do, use this other places preview links are needed
	global $settings; extract($settings);
	// appending a random number to the end of the URL forces the browser not use a cached version of the page
	$preview_url = "http://localhost/{$domain_display_lc}{$optional_subdir}/{$fn}?rand=" . mt_rand(1,10000);
	return "<a href='$preview_url'>" . str_replace("html/", null, $fn). "</a>"; // ditch the html dir, slightly prettier
	}

function getPostsByTag ($tag, $check_tag = true) {
	if ($check_tag) $tag = getTag($tag); // get the true form of the tag, if possible. the check is redundant if the tag value is actually from the database, as it is in some cases
	if ($tag == false) return false; // exit if getTag failed
	global $posts; foreach ($posts as $post) {
//		if ($tag == "_Kim") echo $post['tags'] . " … ";
		$tags_arr = arraynge($post['tags'],',');
		if (in_array($tag, $tags_arr)) $post_matches[] = $post;
		}
	return $post_matches;
	}

function getSSFClasses($item_tags) { // from tags on an item (post or @article)
	global $tags;
	$tags_sff_arr = explode(", ", "pro, guides, self-help, debunkery, biology, fun, featured, back, head-neck, limbs, knee, running, massage, exercise, research, new, updated, old, micro, short, medium, big, huge, subtitles, word counts, dates, mind, muscle pain, science, treatment, problems, rsi, biomechanics, etiology, site-news, blog, none");
	// for now, this is a perfectly good place to  define the ssf tags, but obviously it could be more elegant (e.g. extract them from the tags array)
//	if (!is_array($item_tags)) return; // ??? not sure 
	foreach ($tags_sff_arr as $ssf_tag)								// go through each possible SSF tag looking for any match in the item
		foreach ($item_tags as $item_tag)							// go through the item’s tags
			if ($tags[simplify($item_tag)])							// if the tag is in the database (e.g. #adhoc will get skipped)
				if (array_search($ssf_tag, $tags[simplify($item_tag)]))	// if the SSF tag is anywhere the values for that tag (e.g. the short tag, or spelled out as the ssf tag)...
					$SSFclasses[] = str_replace(" ", "-",$ssf_tag);				// add the ssf tag to the array of SSF tags for this item, for use as a classname
	if ($SSFclasses) return array_unique($SSFclasses);
	}


function getTagsForSSF () {
	$tags = getTags(_ROOT . '/blog/guts/tags-ps.txt');
//		var_dump($tags); exit;
	foreach ($tags as $tag_key => $tag) {
		$tags2[$tag_key]['true'] = $tag['true'];

		$all_synonyms = $all_synonyms2 = array();
		$all_parents = array();
		$children = array();
		$all_related = array();
		if (isset($tag['synonyms'])) $all_synonyms = $tag['synonyms']; // add synonyms, if any
		if (isset($tag['related'])) $all_synonyms = array_merge($tag['related'],$all_synonyms); // add related terms, if any
		if (isset($tag['short'])) $all_synonyms[] = $tag['short'];
		if (isset($tag['abbr'])) $all_synonyms[] = $tag['abbr'];
		if (isset($tag['long'])) $all_synonyms[] = $tag['long'];
		if (isset($all_synonyms[0])) $all_synonyms = array_unique($all_synonyms);
		foreach ($all_synonyms as $syn)
			if  ($syn !== $tag['true'] and !inStr($syn,strtolower($tag['true']))) $all_synonyms2[] = $syn;
		if (isset($all_synonyms2[0])) $tags2[$tag_key]['synonyms'] = $all_synonyms2;
		if (isset($tag['parents'])) {

			$parents = getTagParentsNew ($tag_key, $tag['parents']);
			foreach ($parents as $parent)
				$tags2[$tag_key]['parents'][getTag($parent,'key')] = getTag($parent,'true');
			}
		 $children = getTagChildren ($tag_key); // only 1 gen deep, good enough?
		 if ($children[0]) {
			foreach ($children as $child)
				$tags2[$tag_key]['children'][getTag($child,'key')] = getTag($child,'true');
			 }		
		}
	return $tags2;
	}

function getTagChildren ($tag_given) {
	global $tags; if (!is_array($tags))
		$tags = getTags(_ROOT . '/blog/guts/tags-ps.txt');
	foreach ($tags as $tag_key => $tag) {
		if (!isset($tag['parents'])) continue; // there are no parents, move on to the next tag
		foreach ($tag['parents'] as $parent)
			if ($tag_given == getTag($parent,'key')) $children[] = $tag_key;
		}
	if (isset($children)) return $children;
	}
	
// looking for children of: kneepain
// tag_given = kneepain
// go through tags
// if the tag_given (kneepain) is 


/** returns @array, tags: adds parent tags to an array of tags */
function addParentTags ($children) {
// this function assumes the existence of a master array of tags that explicitly list their children
	$parents = array();
	foreach($children as $child) { // go through the children given
		$childs_parents = getTagParents($child); // get an array of its parents, if any (often empty, many have none)
		if ($childs_parents) // but if any parents are found
			$parents = array_merge($parents, $childs_parents); // merge them with the main parents array
		}
	if (empty($parents)) return $children; // if we found no parents for any child tags, then just return the array of children unharmed
	$parents_and_children = array_merge($children, $parents); // otherwise, merge the arrays of children and parents
	return array_unique($parents_and_children); // and return them, eliminating dupes
	}

/** returns @array, tags: gives an array of parents tags to a given tag */
function getTagParents ($child_tag) {
/* this function assumes the existence of a master array of tags that explicitly lists children, $tags_arr for PS, in tags.php, eg: the “sbm” and “about-research” tags are CHILDREN of the PARENT tag “science,” and they could have other parents
	"science" => array(
		"shorttag" =>		"science",
		"longtag" =>		"research, science and evidence-based medicine",
		"children" =>		"sbm, about-research")			*/
	global $tags_arr;
	foreach ($tags_arr as $parent_tag=>$tag_data) { // for through every tag in the tag database
		if (!isset($tag_data["children"])) continue;
		$children_field = str_replace(" ", null, $tag_data["children"]); // kill spaces
		$child_arr = explode(",", $children_field);
		if (in_array($child_tag, $child_arr)) // if the child_tag is listed among the children …
			$parents[] = $parent_tag; // add it to an array of parent tags
		}
	if ($parents) return $parents;
	else return false;
	}
		
?>