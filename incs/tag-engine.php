<?php /* #tag_engine

TAG ENGINE: making `tags easier to use, for PubSys originally, and PainSci more generally over time.

Tagging is a powerful organizational tool, and yet a simple list of tags quickly becomes unwieldy as it grows. Just like the data they are applied to, tags themselves need organizing — there are different types and categories of tags, different types of tags, aliases for ease of data entry, and so on (and on and on).  Taxonomy is hard!  I’ve been tinkering with this since about 2015, and there's still no end in sight.

There are significant USAGE notes in the header of the tag database itself. These notes are more about how it WORKS, more technical. 

Goals for this system:

•  efficient tagging
	✓	synonyms (and special forms for presentation, eg short, long, abbreviated, etc)
	✓	case, space, and symbol insensitivity to reduce friction
	✓	automatic addition of all parent tags
	✓ simple plural and singular synonyms, eg 'cat' matches 'cats' and vice versa
	✓	automatic-tagging (inferred from content and metadata)
•  efficient tag organizing
	✓	simple text file data format, easy to read and edit
	✓	use shorthands for longer field names when doing data entry (desc, syn/s instead of description, synonyms)
	✓ automatic canonicalization of data file (standardized, alphabetized, metadata, shorthands expanded)
	✓ public descriptions and private notes about tags
	✓	new tags from both CMSes added to the database automatically, e.g. tag a post with a new tag and it will be ingested
	✓ alphabetization prefixes so related tags are loosely grouped alphabetically, e.g. "analgesics » opioids" with other analgesics
	✓ show tag usage in data file (# of items)
	✓ CSV format for all metadata tag lists (e.g. all the "parents") for easy auditing/diffing
	•  generate quick-reference guides, reports
		✓	condensed dictionary
		•	untagged, under-tagged posts
		•  the most frequently used tags
	✓ tags for tags (meta-tags): tags like “core” and “category” classify and describe tags themselves	
	✓	shorthand syntaxes for tag types:
		✓ .admin tags are for internal use, functional but never displayed, e.g. a project or subtopic tag
		✓ #adhoc tags are “for this post only,” excluded for tag management
		✓ *orphan tags have suppressed ancestry
		✓ _category tags for major and/or meta-content tags

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
	STRAY TAG: tag created by usage in error or is never or very rarely used again
	SORT PREFIX: a useful syntax for grouping tags alphabetically under a selected parent

Example tag data entry (this is duplicated in the tag engine notes and in the tag database header):

tx » quackery [#] 						< parent » tag (a selected major parent for sorting purposes, AKA sorting prefix » canonical tag)
	notes = My favourite tag. 		< private notes about the tag, eg clarifying what kind of content
	description = Bullshit txs!		< public description of the tag, eg appears in tooltips on mouseover
	short = quack						< a shorter form of the tag
	long = quackery &snake oil	< a longer form of the tag
	abbr = qky 							< abbreviation of the tag
	synonyms = snake oil				< usage of these terms will always be replaced by the main tag
	parents = skeptagticism			< significant umbrella categories, added to the tag list for the item
	children = skeptagticism		< significant umbrella categories, added to the tag list for the item
	tags = core							< description/classification of topics for admin purposes, e.g. like core, probation, requested
	related = altmed					< similar existing tags (#altmed), or terms that are synonymous for my purposes (patent medicine)

MORE ABOUT RELATED TAGS, or “SYNONYMS FOR NOW” — This field is tricky! It has an obvious purpose, and a non-obvious purpose. Its obvious purpose is to list existing tags that are related in some way, and they are effectively just notes, reminders of what related topics exist.  Its non-obvious purpose is another kind of synonym. This distinction is so confusing that I should probably separate them, and call the second field something like "synonyms for now." The idea is to have a place to put topics that are so closely related that I don’t really want to both with a separate tag … but I might someday. Example: #cannabis and CBD. Not synonyms! But close enough FOR MY PURPOSES that I want to treat them as such. If CBD is listed as a related term for #cannabis, then any usage of CBD will be treated as if it were a usage of #cannabis — exactly like a synonym.  The difference is that I can break that link at will by just creating a dedicated #CBD tag, and suddenly all references to CBD will be treated as #CBD usages, not #cannabis. The term "CBD" can remain in the related list for #cannabis (inert but a useful reminder), or to be more taxonomically precise it could be re-defined as a child.  And why not just make it a child in the first place?  Well, it could be.  That basically works the same way.  But the relationship of many related terms isn't so clear.  It’s just a dumping ground for "close enough for my purposes" synonyms, while falling short of being an obvious synonym, child, etc.
	
	Note that duplication of related tags can get confusing. If both #A and #B tags claim that they are "related" to "C" (not it’s own tag)... then C can't be used as synonym for both, and it ends up getting assigned to the last one processed by getTags while building the thesaurus.

Sort prefixes — Sort prefixes exist originally solely for "display", so that tags like "arthritis » osteoarthritis" and "arthritis » rheumatoid arthritis" are visually grouped together. The prefix was not used in any other way. getTags() removed them and saved them in the sort_prefix field, where they remain until updateTags() restores them right before regenerating the tags files. But the sort prefixes are a selected parent tag conceptually: the point is to group related (sibling) tags for organizational convenience, so that I can see several sibling tags together in the database. And so in time I decided to make them functionally equivalent. The sorting prefix syntax is now fully synonymous with declaring parentage.  When parsing, they are added to the parents list. Before output, the prefix syntax is restored for that parent.  If that prefix is the only parent, the parents field isn't even generated.

Cleanup — Because the spontaneous creation of tags in various contexts actually spawns new tags in the tag db — e.g. if I spell a tag wrong and put "snackery" on a post instead of "quackery," then the #snackery tag will be created.  The practical implication is that it becomes necessary to go through the tag db and identify these stray tags and either correct the source, or merge the stray tag into another tag by making it a synonym or relative.

	

Code overview

	getTags
		if there’s a tag db data file
			parse the tag data file into an array keyed with simplified tags
			make a tag thesaurus to facilitate lookups

	updateTags
		for every post
			lookup every given tag:
				does it match a defined tag or synonym?
				if not (simple)
					add it to the post tags
					add it to the new tags field
				if yes (complicated!)
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


ABOUT PARENTS VS CHILDREN

In early versions of this system, I listed tag CHILDREN in the data (e.g. children of “vegetables” are “carrots, peas … ”), and alogrithmically determined the parentage for carrots by looking for tags that specify carrots as children.  This was programatically expensive, but data entry was easy. Then I switched to listing parents in the data file, which is programatically efficient (it’s easy to lookup parents when they have been explicitly listed), but it makes for redundant data entry (you have to list “vegetables” as a parent for every single vegetable tag). I don’t really know what the right answer was.  Ultimately, it might be feasible to have it both ways: to list children OR parents, and let the tag engine canonicalize the data so that both sides of the equation are represented.


SORTING PREFIXES, COMBO TAGS, and SHORTHANDS FOR DECLARING PARENTS

Tags can be PREFIXED by parent tag to group children together alphabetically, which is organizationally useful. I call the prefix a "sorting prefix," but it is also logically equivalent to a parent. Tags can also be COMBINED with a + to denote pairing that is a topic in its own right, and where each term is also a parent. For instance:

	activators + harms
		parents = chiropractic, activators, harms
		
	===
	
	chiropractic » activators + harms

The condensed syntax is more cryptic but clear enough, tidier, and saves real space, about 5% of the database file, 7% of the line count. The terms are written in the condensed format, but read as explicitly declared as parents.

⚠️ It’s still easy to acidentally declare an illegal parent this way, and I may have some debugging ahead.

And why have a combo tag like "activators+harms" instead of just tagging with each of those independently?  Every well-tagged post is theoretically tagged with a combo tag that combines all the tags on the post, e.g. 

	#activators+harms   ~=   #activators #harms

But only the combo tag will be in database as a combo tag FOR SPECIFIC CONTENT. In other words, it’s descriptive of existing content.  It says "yes, I have content that is specifically about the relationship between these things" … which otherwise could only be theoretically inferred from the presence of both tags, but that wouldn't even actually be possible, because that "signal" would be impossible to separate from the noise of countless other combinations of tags which do not apply to specific content.  Another way of putting it: if you're searching for content that id about both "activators" and "harms," you would want anything taged with "activators+harms" to be at the very top of that list … even though there might be several other articles that do actually have both of those tags.

CATEGORIES

A tag prefixed by an underscore is a “category.”  (The prefix is 100% non-functional: it’s stored, but never actually used in the tag-engine.  It’s parsed out of the input, and put back in when the tags are canonicalized. The sole purpose of the underscore is data-entry convenience in BibDesk.)  What does “category” mean?  Isn’t every tag a category?  It’s an imprecise distinction.  Category-ness is a rough expression of the importance of the tag, which is roughly an intersection of the number of items it applies to, how directly it applies to them.  The “back pain” tag is not only applied to more items than “elbow pain,” but the items it is applied to are mostly about back pain, whereas elbow pain is typically a peripheral or sub-topic.  Thus back pain is definitely a category, but it would be difficult to determine it algorithmically.   The underscore tags also began life in my file system as tags specifically referring to the TYPE of content, as opposed to what it was about or its tone.  To some extent, types of content tend to be large or important categories. In time, I may replace the category syntax with a more granular tag rating system.


HOW DOES TAG TALLYING WORK?

* PubSys counts tag usages as it builds blog posts and saves a list of all tags. Writerly gets its tag tallies from that file.
* PainSci ignores that file, and instead gets tag tallies from a list of tag usages harvested from srcs.bib by Make Bib, which has all tag usages from both of the two CMSes that PainSci is based on.  That includes three major types: tags on bibliographic records, tags on my own articles, and tags on blog post which are harvested from PubSys output. That is, all tag usages are logged in a file that is updated by BibliographyTagHarvester.pl, which gets updated by Make Bib (srcs.tags.txt).
* Regardless of where the tag list comes from, updateTags() calls tallyTags() during a PubSys build to ingest, count, de-dupe, and add the counts to the main $tags array.
* Tag tallies are saved to tags-ps.txt, but at this time they are NOT parsed by getTags().  That is, when the $tags array is initially created, it has no tallies, even though the number is right there. This is somewhat limiting. For instance, there would be no way to extract under-used tags from the array.  There are lines in getTags for ingesting tallies, but they are commented out for now.
* Tags parents and ancestors are only counted if they are inferred from other tags on a blog post, or explicitly added to any item.  This is why major parents like #spinalpain have surprisingly low tallies — because a blog post about #backpain or #neckpain will automagically get the #spinalpain tag, but an @article will only get it if I explicitly add it. This is a non-trivial problem I will have to solve at some point.

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
	foreach ($lines as $line) { // loop through all lines
	if (!isset($finished_header)) { // if we’re not yet finished reading the header …
		$tags_header .= $line; // build a duplicate of the header
		if (inStr("*****", $line))  // the end of the header is marked by a row of at least 5 asterisks
			$finished_header = true; continue; // the header is so over, so move on
		}
	else { // if reading data (done with header)
		$line = rtrim($line); // trim line feeds (trim from right only because tabs on the left are meaningful in this format)
		if (trim($line) == "") continue; // ignore empties; didn’t want to do this in the header, but we do for the data
		$lines_tags[] = $line; // build an array of lines with tag data
		if (strpos($line, "\t") !== 0) { // a non-blank line in the body that does not start with a tab… is a tag

			// start parsing a tag item
			preg_match('@\[(\d+)\]@', $line, $tagTallies); // get the tag tallies out of the [ ]; they are recounted by updateTags() … disabled until such time as the tag engine is refactored so that this does not conflict with extracttags adding to the ingested post tag tallies instead of replacing them

			$line = preg_replace("@ \[.*?$@", '', $line); // strip the tag tallies from the line

			$line = ltrim($line, "» "); // remove the canonical tag marker at the beginning of the line, which helps with searching for the canonical instance of a tag that may appear in many other contexts in the database, but is not used in the processed tag data in any way; it will be put back at the "last second" before saving the tag database

			$current_tag = $line; // now we've got just the tag itself

			$current_key = simplify($current_tag); // Make a KEY from a simplified version of the tag: an all-lower case, no-spaces version of the tag. Simplifies parsing and references significantly. The tag may still have some other syntactical complications, such as prefixes (parent»tag), combo tags (tag+tag), or admin/category tag prefixes.

			if (inStr("»", $current_tag)) { // if there's a » symbol, the tag has a sorting prefix and parent, and it needs to be removed from the tag and added to the tag's parent list (which will always be empty at this point)
				$parts = preg_split('|»|', $current_tag); // separate the sort prefix and the tag
				$prefix = trim($parts[0]); // save the prefix
				$current_tag	= ltrim($parts[1]); // re-save the current tag san prefix
				$current_key = simplify($current_tag); // re-save the current key sans prefix
				$tags[$current_key]["sort_prefix"] = $prefix; // save the sort prefix in a field as a string so that it can be restored to the data file by updateTags()
				if ($tags[$current_key]["sort_prefix"] !== $current_tag) { // this is a touch hacky, but sometimes it’s helpful to have a sorting prefix that's identical to the tag itself … and of course a tag cannot be it’s own parent, so … 
					$tags[$current_key]["parents"] = array($prefix); //  add it to a new parents array, if it’s not exactly identical to the tag
					}
				// the sort prefix effectively no longer exists (for the purposes of most of the code in this file); it will be restored by updateTags()
				// if ($current_key == "triggerpoints") {echo "<p>'{$current_tag}'<br>'{$current_key}'</p>"; printArr($tags[$current_key]); exit;}
				} /**/

							/* $test_tag = '2do';
							if (inStr('2do', $current_tag)) {
								echo "<p style='font-size:1.5em'><br>Found test tag: '<strong>$test_tag</strong>'<br>";
								echo "\$current_tag = <code><strong>$current_tag</strong></code><br>";
								echo "\$current_key = <code><strong>$current_key</strong></code> (simplified tag)<br>";
								echo strpos($current_tag, ".");
								echo "</p>";
								// exit;
								} /**/

/* incomplete and not usable until I add a step below; much like with parents, I have to merge this with any existing tag-tags
			if (strpos($current_tag, ".") === 0) {// . prefix denotes "admin" type tag, add it to the tags field
				$tags[$current_key]['tags'] = 'admin'; // add the #admin tag to the tag
				
			} */

			if (inStr("+", $current_tag)) { // if there's a + symbol, the tag is a combo tag, and we don’t need to change the tag, but we do want to extract parent tags from it
				$parts = preg_split("|[+]|", $current_tag); // split it on +
				$parts = array_map('trim', $parts); // trim whitespace from the parts (shouldn't space have already been removed by simplify()?
				if (isset($tags[$current_key]["parents"])) { // if a parents array already exists
					$tags[$current_key]["parents"] = array_merge($tags[$current_key]["parents"], $parts); // given "aardvarks + anteaters", save #aardvark and #anteaters to an array of parents of the combo tag #aardvark+anteaters
				}
				else {
					$tags[$current_key]["parents"] = $parts;
				}
				$tags[$current_key]["parents"] = array_unique($tags[$current_key]["parents"]); // in many cases, one part of the combo tag is also a parent denoted by the sorting prefix, and there would be a duplicate this point
				// echo $current_tag; printArr($tags[$current_key]); exit;
				} /**/
			
			if (isset($tags[$current_key]["parents"])) $tags[$current_key]["parents_inferred"] = $tags[$current_key]["parents"]; // save the parents that have been inferred this way to a seperated array, to facilitate removing them before saving the tag data, which significantly reduces clutter

			$tags[$current_key]["key"] = $current_key; // this is redundant, but it makes some chores easier (eg getTag can use a field lookup to return either the key or any other version of the tag)
			$tags[$current_key]["true"] = ltrim($current_tag, "_"); // this is where the true tag is set in stone (removing the underscore prefix from tags what have it, about a dozen); off the top of my head on much-later review, I do not remember why this wouldn't also be done to the key, and it makes me wonder if this is buggy
			if ($journalling) journal ("getting tag $current_key",2);
			// so now we have an array item keyed with the simplified tag, and the key also also duplicated to a value, plus the "true tag" is 
			// e.g. the true tag “Walking Dead” is now stored like so:
			// array ("walkingdead" => array ("key" => "walkingdead", "true" => "Walking Dead"))

			$tags[$current_key]['tally#'] = $tagTallies[1]; // assign the bib tag tally harvested earlier from the raw line

			continue; // finish (could proceed with an ‘else’)
			} // close if untabbed line
		if (strpos($line, "\t") == 0) { // if a line DOES start with a tab, it’s data about the current tag (this could be “else” instead of another if)
			if (inStr("<##>",$line)) continue; // move on if there’s no data (<##> indicates an empty field)
			$parts = explode("=", $line); // split the line on colon
			$tag_field = trim($parts[0]); // get the field name from the string preceding the colon
			$tag_data = trim($parts[1]); // and get the field data from the 2nd half
			if ($tag_data == "") continue;  // move on if there’s no data 
			if ($tag_field == "syns") 	$tag_field = "synonyms"; // expand shorthand for synonyms
			if ($tag_field == "synos") 	$tag_field = "synonyms"; // expand shorthand for synonyms
			if ($tag_field == "rel") 	$tag_field = "related"; // expand shorthand for synonyms
			if ($tag_field == "re") 	$tag_field = "related"; // expand shorthand for synonyms
			if ($tag_field == "syn") 	$tag_field = "synonyms"; // expand shorthand for synonyms
			if ($tag_field == "parent") 	$tag_field = "parents"; // expand shorthand for parents
			if ($tag_field == "par") 	$tag_field = "parents"; // expand shorthand for parents
			if ($tag_field == "pars") 	$tag_field = "parents"; // expand shorthand for parents
			if ($tag_field == "desc") 	$tag_field = "description"; // expand shorthand for description
			if (!in_array($tag_field, $tag_fields)) continue; // #2do: error for incorrect fields

			if ($tag_field == "synonyms") {
				$tag_data = arraynge($tag_data, ','); // get an array of CSVs
				natcasesort($tag_data);
			}
			
			if ($tag_field == "related") {
				$tag_data = arraynge($tag_data, ','); // get an array of CSVs
				natcasesort($tag_data);
			}

			if ($tag_field == "parents") { // the parents field may already have been populated above by shorthands, either the "parent»child" or the "parents+parent" syntax; if other parents are also declared, they will be added to that array
				$tag_data = arraynge($tag_data, ','); // get an array of CSVs
				natcasesort($tag_data);
				if (isset($tags[$current_key]["parents"])) { // if a parents array already exists
					$tag_data = array_merge($tags[$current_key]["parents"], $tag_data); // add these explicitly declared parents to any parents previously assigned to $parents by other conventions like parent»child or parent+parent
				}
				else {
					$tags[$current_key]["parents"] = $tag_data;
				}
				
				$tag_data = array_values(array_unique($tag_data)); // kill dupes and re-index the array
				natcasesort($tag_data);
			// $foo = array_merge((array) $foo, $bar); // This is the syntax for merging a new array ($bar) into what may or may not already be an array ($foo)
			}

			$tags[$current_key][$tag_field] = $tag_data; // assign whatever field data has been generated to its field

			// echo $current_tag; printArr($tags[$current_key]); exit;
				
			} // endif: tabbed line
		} // endelse: reading data
	} // endloop: lines

	ksort($tags, SORT_FLAG_CASE | SORT_NATURAL); // sort the tag database by key; sort_flag_case combined with either sort_natural OR sort_string will yield a case-insensitive result, but using _natural also sorts just "arthritis" before "arthritis » osteoarthritis", which is visually helpful
	$tag_count = count($tags); // get a count
	// echo "&nbsp;($tag_count tags found)";

	# echo "khdfldfh"; printArr($tags); exit;

	// The "sort_prefix » tag" syntax is conceptually equivalent to "parent » child" or "child¶parents=parent". I don’t want to have to declare the parent explicitly in BOTH the sorting prefix AND the tag data, so this subroutine automates that process.  It’s a little brute forcey, but it is conceptually clean.  Note that I will also REMOVE the tag parent before output if it’s the only parent, because it is also redundant visually.  But it does need to be in the data.
	foreach($tags as $tag => $td) { // go through every tag
		if (isset($td["sort_prefix"]) and
			isset($td["abbr"]) and
			$td["sort_prefix"] == $td["abbr"]
			) { // if there's a sort prefix but no parents
			$key = array_search($td["sort_prefix"], $tags[$tag]['parents']);
			if ($key !== false) {
				unset($tags[$tag]['parents'][$key]);
			} // remove the sort prefix from the array of parents
		}
	} // end tag loop

	// now that we we have the data in a lovely array, make a thesaurus: an array of all possible synonyms pointing to true tags
	foreach($tags as $tag => $td) { // go through every tag
// Important! This will crash some dynamic pages. For each kind of synonym, we'll be checking to see if it has already been previously set, and it should happen only in the context of a build.  For unclear reasons, doing it on, say, bibliography pages results in an exit with a false positive warning. So it’s if (isset AND $pubsys) for each check.  #todo Someday I'll figure out why it needs pubsys.
		if (isset($td["synonyms"]))
			foreach ($td["synonyms"] as $synonym) {
				if (isset($GLOBALS['tag_thesaurus'][simplify($synonym)]) and $pubsys) exit("<p class='warning'>⚠️ The synonym '<strong><span class='copythis2' onClick='copy(this)'>$synonym</span></strong>' declared for #$tag already refers to another tag. Fix before proceeding.<p>"); // with the explosion in complexity of the tag database by 2025, there about three dozen duplicate synonyms; but they remain relatively rare, and easy to fix, with no complications in an of those; now that these warnings are in place, it should be trivial to fix new ones as they occur
				$GLOBALS['tag_thesaurus'][simplify($synonym)] = $tag;
				}
		if (isset($td['short'])) {
			if (isset($GLOBALS['tag_thesaurus'][simplify($td['short'])]) and $pubsys) exit("<p class='warning'>⚠️ The short tag '<strong><span class='copythis2' onClick='copy(this)'>{$td['short']}</span></strong>' declared for #$tag already refers to another tag. Fix before proceeding.<p>");
			$GLOBALS['tag_thesaurus'][simplify($td['short'])] = $tag;
			}
		if (isset($td['abbr'])) {
			if (isset($GLOBALS['tag_thesaurus'][simplify($td['abbr'])]) and $pubsys) exit("<p class='warning'>⚠️ The tag abbreviation '<strong><span class='copythis2' onClick='copy(this)'>{$td['abbr']}</span></strong>' declared for #$tag already refers to another tag. Fix before proceeding.<p>");
			$GLOBALS['tag_thesaurus'][simplify($td['abbr'])] = $tag;
			}
		if (isset($td['long']))  {
			if (isset($GLOBALS['tag_thesaurus'][simplify($td['long'])]) and $pubsys) exit("<p class='warning'>⚠️ The tag abbreviation '<strong><span class='copythis2' onClick='copy(this)'>{$td['long']}</span></strong>' declared for #$tag already refers to another tag. Fix before proceeding.<p>");
			$GLOBALS['tag_thesaurus'][simplify($td['long'])] = $tag;
			}
		$GLOBALS['tag_thesaurus'][$tag] = $tag;
		} // end tag loop

		// now that all the primary tags are defined, we can check related tags to see if they already exist before assigning them
		foreach($tags as $tag => $td) { // go through every tag	
			if (isset($td["related"])) // if the tag has 'related' tags…
			foreach ($td["related"] as $related) { // go through the related tags
				if (!isset($GLOBALS['tag_thesaurus'][simplify($related)])) // if a related tag already exists, do nothing — e.g. we don’t re-assign DMSO to #pharmacokinetics just because it’s related to it, because it already exists as its own tag, so we want to keep dmso=>dimethylsulfoxide; but 'cbd' is not a tag, and I WANT usages of cbd to behave like a synonym for #cannabis indefinitely, so we want cbd=>cannabis
					$GLOBALS['tag_thesaurus'][simplify($related)] = $tag;
				}
		}
	
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


	ksort($GLOBALS['tag_thesaurus'], SORT_FLAG_CASE | SORT_NATURAL); // sort the thesaurus database by key; sort_flag_case combined with either sort_natural OR sort_string will yield a case-insensitive result, but using _natural also sorts just "arthritis" before "arthritis » osteoarthritis", which is visually helpful
	$GLOBALS["tag_db_header"] = $tags_header; // stored where the updateTags function can get to it

/* echo "<pre><span class='boogers'>$tag_count tags<br>";
	//echo $tags_str_qrg;
	//echo $tags_header;
	//echo $tags_str;
	//print_r($lines_header);
	//print_r($lines_tags);
//	print_r($tags);
	//	print_r($GLOBALS['tag_thesaurus']);
	echo "</span></pre>"; /* */

	return $tags;
	}


/** returns @string, html list: post tags marked up as HTML tag list for PubSys  */
function markupTags ($arg_tags) { 
	$arg_tags = arraynge($arg_tags,',');
	global $tags;
	if (!$tags) {
		foreach ($arg_tags as $arg_tag) $tags_str .= "<li class='tag'>$arg_tag</li>";
		}
	else {
		foreach ($arg_tags as $arg_tag) {

			$tag_display = (isset($tags[simplify($arg_tag)]['short'])) ? $tags[simplify($arg_tag)]['short'] : $arg_tag; // use a short version, if available
			
			if (($tags[simplify($arg_tag)]['tally#']??null) > 5) {
				$tags_str .= "<li class='tag'><a href='tag-" . titleToFn($tags[simplify($arg_tag)]['true']) . ".html'>{$tag_display}</a></li>";
				}
			else
				$tags_str .= "<li class='tag' title='Not enough posts use this tag.'>{$tag_display}</li>";
			}
		}
	return "<ul class='tag_list'>$tags_str</ul>";
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

/** returns @string, csv, tags: rich tag set (parents, synonyms, etc) derived from a set of given tags, independently or for a post*/
function extractTags ($tags_str, $post = false) { 
	// For each post/item, extractTags generates a final list of tags from various sources (exact matches, synonyms, and parents of matches, and more in the future) by comparing the tags-as-written to the canonical tags.  In the context of PubSys, the post data is also updated with new fields for each type of tag, as well as a main tag field including all tags.
	// 2025-04-07 Does not appear to do synonyms?
	if ($post and $post['rss_only_post']) return $post; // Don’t extract tags for RSS-only posts, because doing so affects tag counts in the global tags array, even though RSS-only posts are supposed to be post ghosts that have no effect on anything except the RSS feed.	More words: Athough RSS-only posts are taken out of the main posts array after being created, they still get created like a normal post in every other way… and when tags are extracted by extractTags(), the main tags array is updated with tag counts; keeping RSS-only posts out of the main posts array effectively ghosts them in every other way that I know of, but there is this one thing here where an RSS only post will have an effect outside itself if it isn't deliberately excluded.
	global $tags;
	// initialize arrays; tags_final ends up as the "tags" field, all others as fields with matching names
	$tags_given = arraynge($tags_str, ","); // split the list of given tags into an array, assumes no spaces
	$tags_final = array(); // the main destination, the array that will hold the tags that end up in the main, PUBLIC tag list for the post
	$tags_private = array(); // the complete array of tags for my administrative reference, including admin and category tags; this ends up in the final build of the post, and harvested by make bib
	$tags_parents = array(); // an array of parents of all tags (that aren't designated orphans)
	$tags_inferred = array(); // tags extracted from the post by inferTags()
	$tags_adhoc = array(); // tags marked with #, not to be included in the tags db
	$tags_new = array(); // tags that are new (not in the database, not adhoc) as of this build
	$tags_admin = array(); // tags for administrative use only 

	if ($post) { // only if there's a blog post involved…
		$tags_inferred = inferTags($post); // infer tags from post content and metadata
		$tags_given = array_merge($tags_given, $tags_inferred);
		}
		
	# printArr($tags_given); exit;
	foreach ($tags_given as $tag_given) { // work through given and inferred tags, and do some sorting into the different lists of tags as we go
		if ($tag_true = getTag($tag_given)) { // if given tag matches a defined tag
			// if (isset($tags[simplify($tag_true)]['tags'])) $categorytags = $tags[simplify($tag_true)]['tags'];

			//if ($post['psid'] == '2339003') echo "<p>given: $tag_given, true: $tag_true, $categorytags, " . simplify($tag_true) . "</p>";
			//if ($post['psid'] == '2339003') printArr( $tags[simplify($tag_true)]);
			
			if (in_array($tag_true, $tags_final)) continue; // abort if we already have the tag for some reason (eliminates some minor sources of redundancy, like inferred tags)
			
			if (inStr("_size_", $tag_given)) { // special case of the size tag: save the true version to the final tags list (e.g. size M), but use the synonym (_size_m) for the admin and private tags (which matches my customary format in srcs.bib)
				$tags_final[] = $tag_true; 
				$tags_admin[] = $tag_given; 
				$tags_private[] = $tag_given;
				}
			elseif (isset($tags[simplify($tag_true)]['tags']) and inStr('admin', $tags[simplify($tag_true)]['tags'])) { // add admin tags to an independent array of just admin tags, and to the private tag list, not the final (public) tag list
				$tags_admin[] = $tags_private[] = $tag_true;
				}
			else { // some other tag (not a _size_ tag, not an admin tag)
				$tags_final[] = $tags_private[] = $tag_true; 
				}

			if (!inStr("*", $tag_given)) // add any parents, if it’s not an orphan tag (marked with *)
				$tags_parents = getTagParentsNew($tag_given, $tags_parents);
			} // endif: defined tags

		else { // if tag is undefined, then it is either an adhoc or a new tag
			if (strpos($tag_given, "#") === 0) { // prefix of # denotes an adhoc tag (unmanaged tag, not intended to be saved in the database)
				$tags_adhoc[] = $tags_final[] = ltrim($tag_given, "#"); continue; // add it to the adhoc tag list and the final tag list and move on
				}
			$tags_new[] = $tag_given; // add to an array of new tags to be processed seperately below, using the original given tag w symbols
			} // endif: new tags
		} // tag loop

	$tag_lists["tags_given"] = implode(",", $tags_given); // store the original tags (mostly for troubleshooting)
	
	//						if ($post['psid'] == '2339003') echo "<p>tags_admin: $tag_given, tag_true: $tag_true</p>";

	if (isset($tags_inferred))	$tag_lists["tags_inferred"] = implode(",", $tags_inferred);
	if (isset($tags_adhoc)) 		$tag_lists["tags_adhoc"] = implode(",", $tags_adhoc); 
	if (isset($tags_new))			$tag_lists["tags_new"] = implode(",", $tags_new);
	if (isset($tags_parents)) 	$tag_lists["tags_parents"] = implode(",", array_unique($tags_parents));
	if (isset($tags_admin)) 		$tag_lists["tags_admin"] = implode(",", $tags_admin);

	// add parents to the final (public) tag list, de-dupe, and remove admin tags
	$tags_final = array_unique(array_merge($tags_final, $tags_parents));
	$tags_final = removeAdminTags($tags_final);

	$tag_lists["tags"] = implode(",", $tags_final); // convert to csv
	// if ($post['psid'] == '2339003') {printArr($tags_private); printArr($tags_final);}
	$tags_private = array_unique(array_merge($tags_private, $tags_parents)); // add parent tags and de-dupe
	$tag_lists["tags_private"] = implode(", ", $tags_private);
//	$tag_lists["tags_private"] = str_replace(", size ", ", _size_", $tag_lists["tags_private"]); // hack just for #blogharvest: convert the size tag generated by tag engine to the customary format I use for tags in srcs.bib, rather than trying to teach tag engine to produce this hair-splitting difference

	if ($post) { // add the enhanced tags to the $post and return the post
		foreach ($tag_lists as $field => $data) $post[$field] = $data;
		return $post;
		}
	else return $tag_lists; // return the enhanced tags on their own
}

function removeAdminTags(array $input): array {
    return array_values(array_filter($input, function($item) {
        return strpos($item, '.') !== 0;
    }));
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
	$inf[] = "_size_" . $post['size'];
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
function getTagParentsNew ($tag_given, $tags_parents = array()) {
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
			$parentTag = getTag($parent);
			if ($parentTag) {
				if ($parentTag == $tag_given) { // if a given parent is actually the same tag, e.g. "aging" is listed as a parent for the aging tag, which is unlikely to happen … or the more likely scenario for this mistake, if "beauty parlour syndrome" is listed as a parent for 'atlantoaxial instability' when it’s already defined as a synonym
					exit("'$parent' is incorrectly defined as a parent for '$tag_given'");
					}
				if ($echo) echo "… getTag found <strong>$parent</strong> …";
				$tags_parents[] = $parentTag;  // add it to the array of parents so far
				$tags_parents = getTagParentsNew($parentTag, $tags_parents); // recursion
				}
			else { // if the parent is an undefined tag
				$tags_parents[] = $parent;
				}
			} // parents loop
		} // endif: parents
	return array_unique($tags_parents); // return array of parents (possibly unchanged, if there were no parents)
	}


/** returns @multi: builds the tags data file from PubSys posts and srcs.tags.bib and makes tag QRGs  */
function updateTags() {
	journal("looking for new tags in posts",1,true);
	global $tags; if (!$tags) return;
	global $sitecode; $tag_fn = "guts/tags-{$sitecode}.txt";
	$tags = tallyTags($tags); // add ALL tags from srcs.bib, major step
	$tags = getBlogTags($tags); // add NEW tags from PubSys posts
	$tags = removeRedundantParents($tags); // Remove redundant parent tags that don’t need to be declared explicitly in the parents field, because they are implied by the syntax of compound and/or prefixed tags, e.g. fruit » apples+oranges. 
	$tags = restoreSortPrefixes($tags); // Restore sort-prefixes and sort …
	
	# echo 'akdshfldh '; printArr($tags); exit;

	// now that we have the complete tags array, including new tags, and sorted (with sort-prefixes), we do just a tiny bit of cleanup and organizing, format each record in two ways for two destination files: the main tags database file, and a QRG
	
	foreach($tags as $tag => $td) { 
		$td['tally#'] = isset($td['tally#']) ? $td['tally#'] : '0';		
		$tags_str_qrg .= formatTagRecord_QRG($td); // add a tag to the tag QRG list, a compact readable alphabetical list of tags and their synonyms
		$tags_str_db .= formatTagRecord_DB($td); // add a tag to the main tags database
		} // end tag loop

	// save tag files …
	
	// modify the header: add the quick reference guide to the tags
	$tags_str_qrg = "TAGS QUICK REFERENCE GUIDE\n\n" . $tags_str_qrg . "\n";
	$tag_qrg_fn = "guts/tags-qrg-{$sitecode}.txt";
	if (fileExistsNoChange($tags_str_qrg, $tag_qrg_fn)) {
		journal("tags quick-reference guide has not changed, <em>not</em> writing file: $tag_qrg_fn",2,true);
		}
	else {
		journal("<strong>tags quick-reference guide has changed</strong>, writing file: $tag_qrg_fn", 2, true);
		saveAs($tags_str_qrg, $tag_qrg_fn);
		}

	$tags_canonicalized = $GLOBALS["tag_db_header"] . $tags_str_db;
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
	} // eofn updateTags

/** returns @files: makes tag index pages and files */
function makeTagIndexes () {
	if ($GLOBALS['ps']) return; // exit ƒ if this is PS
	journal("making tag index pages",2, true);
	global $tags; if (!$tags) return;
	foreach ($tags as $tag) {
		if ($tag['tally#'] < 6) continue; // exclude rare tags
		$tagged_posts = getPostsByTag($tag['true'], false); // get an array of posts with this tag; false param tells function not to bother find the true form of the tag via getTag, because we know we already have a true tag
//		foreach ($tagged_posts as $x) echo $x['title'] . '… '; exit;
		if (!$tagged_posts) continue;
		$ti_posts_table = make_post_index($tagged_posts); // generate a table of post links (make_post_index defaults to a table)
		$ti_page = renderPhpFile("guts/template-tag-index.php",false,true); // get the RENDERED contents of the template
		// #2do probably should use the short version of the tag, if available; easy but not super important
		$ti_page = str_replace('{$ti_tag}', $tag['true'], $ti_page); 
		$ti_page = str_replace('{$ti_tag_count}', $tag['tally#'], $ti_page);
		// why count()? in theory, we should know this from the # field for the tag in the main tag_db
		// in practice there are probably going to be discrepancies, so let's just count what we actually have
		$ti_page = str_replace('{$ti_posts_table}', $ti_posts_table, $ti_page);
		if ($tag['description']) { // if there's a tag description
			// title is  "# posts tagged '[tag]'"
			// description is [description]
			// append to H1
			$ti_page = str_replace('{$title_html}',"{$tag['tally#']} posts tagged {$tag['true']}", $ti_page);			
			$ti_page = str_replace('{$description}',"{$tag['description']}", $ti_page);
			$ti_page = str_replace('</h1>', "<br><span id=\"subtitle\">{$tag['description']}</span></h1>", $ti_page);			
			}
		else { // if there's no description
			// title is the long form of the tag
			//	description is  "# posts tagged '[tag]'"
			// nothing to append to H1
			$ti_page = str_replace('{$title_html}',"Tag: " . ucfirst($tag['true']), $ti_page);			
			$ti_page = str_replace('{$description}',"{$tag['tally#']} Writerly posts tagged '{$tag['true']}'.", $ti_page); // add it to the title element
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

/** returns @array, tags: returns the tags array with tags from the PainSci bibliography added */
function tallyTags ($tags) {
	if ($GLOBALS['ps']) {
		$tagsToCount = file($_SERVER['DOCUMENT_ROOT'] . '/srcs.tags.txt'); // get an array of all tags occurring in srcs.bib, many thousands of them; this file is produced by BibliographyTagHarvester.pl, which can be run independently with 'make bib tags only.command' or with 'make-ps-bib.command' as part of a full #makebib
		}
	else {
		$tagsToCount = file($_SERVER['DOCUMENT_ROOT'] . '/guts/tags.used.txt'); // got this going 2025-04-15, just added a simple function to non-PS builds that saves all tags generated by the build to a file
		}
	
	// first we're going to count and then eliminate duplicates
	foreach ($tagsToCount as $tagToCount) {
		$tagToCount = trim($tagToCount); // lines collecting by file have linefeeds (I thought there was a way to prevent the linefeeds, but I can't find it)
		if ($tagToCount == "") continue; # empty items have mostly been eliminated, but sometimes they can crop up due to source data errors like a field that leads with a comma tags={,tag} or tags={tag,,tag}
		$tagToCounts[$tagToCount]++; // save this tag to a new array and iterate a tally for that tag; if the tag is new to the array, it gets added with a value of 1; if the tag has already been added, then this just counts the occurrence
		}
	
	# ksort($tagToCounts); // sort for no terribly important reason
	# echo "<p>Unique tags tally: " . count($tagToCounts) . "</p>";
	# printArr($tagToCounts); # exit;
foreach ($tags as $tag => $td) $tags[simplify($tag)]['tally#'] = 0;

	foreach ($tagToCounts as $tagToCount => $count) { // go through the new array of bib tags
		# echo $x++ . ". $tagToCount ($count) ";
		if ($trueTag = getTag($tagToCount)) { // if the tagToCount matches a tag in the tag database…
			$tags[simplify($trueTag)]['tally#'] = $tags[simplify($trueTag)]['tally#'] + $count; // add the number of occurences; this counter does not exist in the tags database until tags are harvested; that is, although previously generated tag counts exist in the tag database (tags-ps.txt), they are not actually put into the $tags array array by getTags() … this function adds them, so that they are there before the db is regenerated
		}
		else { // if the tag is new, then add it to the array
			if (strpos($tagToCount, ".") === 0) // . prefix denotes "admin" type tag, add it to the tags field
				$tags[simplify($tagToCount)]['tags'] = "admin"; // add the #admin tag to the tag
			if (strpos($tagToCount, "_") === 0) { // _ prefix denotes "category" type tag, add it to the tags field
				$tags[simplify($tagToCount)]['tags'] = "category"; // add the #category tag to the tag
				$tagToCount = ltrim($tagToCount, '_'); // prefixes for categories are handled differently from admin tags, and it’s quiiiite confusing, and trying to handle them the same way runs into non-trivial complications regardless of which direction we go… for now the . prefix is maintained in the true tag, and _ is not
				}
			// NOTE: Prefixes for categories are handled differently from admin tags, and it’s quiiiite confusing, and trying to handle them the same way runs into significant complications regardless of which direction we go.
			journal("adding new tag “{$tagToCount}” to tags",3,true);
			$tags[simplify($tagToCount)]['true'] = $tagToCount; // save the tag as given, including prefixes
			$tags[simplify($tagToCount)]['tally#'] = $count;
			$tags[simplify($tagToCount)]['notes'] = 'new!';
		}
	}
	# echo 'dkasfjgdafg '; printArr($tags); exit;
	return $tags;
}

/** returns @array, tags: returns the tags array with new tags from all PubSys posts added and counted */
function getBlogTags ($tags) {
	global $posts; foreach ($posts as $post) { // make an array of new tags found in posts, counting them along the way
		if (!$post['tags_new']) continue;
		$post_new_tags = arraynge($post['tags_new'], ",");
		foreach ($post_new_tags as $newtag) {
			$tags[simplify($newtag)]['true'] = $newtag;
			$tags[simplify($newtag)]['notes'] = "new from “{$post['title']}” [{$post['psid']}]";
			if (strpos($newtag, ".") === 0) // _ prefix denotes "admin" type tag, add it to the tags field
				$tags[simplify($newtag)]['tags'] = "admin";
			if (strpos($newtag, "_") === 0) // _ prefix denotes "category" type tag, add it to the tags field
				$tags[simplify($newtag)]['tags'] = "category";
			// NOTE: Prefixes for categories are handled differently from admin tags, and it’s quiiiite confusing, and trying to handle them the same way runs into significant complications regardless of which direction we go.
			journal("adding new post tag “{$newtag}” to tags",3,true);
			}
		}
	return $tags;
}

/** returns @string, html list: tag record formatted for output in the tags QRG file  */
function formatTagRecord_QRG ($td) {
	if (isset($td["sort_prefix"])) $prefix = $td["sort_prefix"] . " » "; // prepare sort prefix for "display" by appending » (e.g. "arthritis" becomes "arthritis » " before it is inserted before "osteoarthritis"); the actual sorting has already been done, this is just preparing to restore the sort prefix for the file
	if (is_array($td['synonyms'])) $synonyms = implode(" ", $td['synonyms']);
	if (is_array($td['related'])) $related = implode(" ", $td['related']);
	return $prefix . $td['true'] . "\t{$td['tally#']}\t{$td['short']}\t{$td['abbr']}\t{$td['short']}\t{$td['long']}\t{$synonyms}\t{$related}\n";
}

/** returns @string, html list: tag record formatted for output in the main tags data file  */
function formatTagRecord_DB ($td) {
	if (isset($td["sort_prefix"])) $prefix = $td["sort_prefix"] . " » "; // prepare sort prefix for "display" by appending » (e.g. "arthritis" becomes "arthritis » " before it is inserted before "osteoarthritis"); the actual sorting has already been done, this is just preparing to restore the sort prefix for the file
	else $prefix = "» "; // prepend the canonical tag marker, which helps with searching for the canonical instance of a tag that may appear in many other contexts in the database; because this is added at the "last second", and removed immediately upon parsing
	$tagRecord = "\n{$prefix}{$td['true']} [{$td['tally#']}]\n";
	 global $tag_fields; foreach ($tag_fields as $tag_field) {
		if (is_null($td[$tag_field])) continue; // exclude this line to include empty fields
		$tagRecord .= "\t" . $tag_field . " = ";
		if (is_string($td[$tag_field])) $tagRecord .= $td[$tag_field] . "\n";
		if (is_array($td[$tag_field])) $tagRecord .= implode(", ", $td[$tag_field]) . "\n";
		if (is_null($td[$tag_field])) $tagRecord .= "<##>\n"; // could put <##> here, maybe
	} // end fields loop
	return $tagRecord;
}

function makeThesaurusFile () {
	if (!is_array($GLOBALS['tag_thesaurus'])) exit("\$GLOBALS['tag_thesaurus'] is not an array");
	$tag_thesaurus_json = json_encode($GLOBALS['tag_thesaurus']);
	$tag_thesaurus_fn = _ROOT . "/incs/tags-thesaurus.txt";
	if (fileExistsNoChange($tag_thesaurus_json, $tag_thesaurus_fn)) {
		journal("tags file has not changed, <em>not</em> writing file: $tag_thesaurus_fn",2,true);
		}
	else {
		journal("<strong>tags file has changed</strong>, writing file: $tag_thesaurus_fn", 2, true);
		saveAs($tag_thesaurus_json, $tag_thesaurus_fn);
		}	
}

/** returns @array, tags: tag record with 'parents_inferred' removed from 'parents' */
function removeRedundantParents($tags) { // Remove redundant parent tags that don’t need to be declared explicitly in the parents field, because they are implied by the syntax of compound and/or prefixed tags, e.g. fruit » apples+oranges. This saves real space when writing the tags data file: 4351 lines & 148kb before, 4072 lines & 140kb.
	foreach($tags as $tag => $td) { // loop through the array of tags
		if (!isset($td['parents_inferred'])) continue; // this is only relevant to compound and/or prefixed tags, e.g. fruit » apples+oranges
		$tags[$tag]['parents'] = array_values(array_diff($td['parents'], $td['parents_inferred'])); // subtract the "inferred" parents from the main parents array; this value was saved aside waaaay back at the beginning of parsing the tags data file, just to make this step easier
		if (empty($tags[$tag]['parents'])) unset($tags[$tag]['parents']);
	}
	return $tags;
}

/** returns @array, tags: tag record with 'parents_inferred' removed from 'parents' */
function restoreSortPrefixes($tags) {
	foreach($tags as $tag => $td) { // loop through the array of tags
		if (isset($td["sort_prefix"])) { // if there is a sort prefix (fairly rare to date)…
			$tags_resorted[$td["sort_prefix"].' » '.$tag] = $td; // add it to the key for this tag in a duplicate array
			}
		else { // most tags…
			$tags_resorted[$tag] = $td; // copy unchanged tag data unchanged
			}
	}
	ksort($tags_resorted, SORT_FLAG_CASE | SORT_NATURAL); // sort the new array with restored tag prefixes; sort_flag_case combined with either sort_natural OR sort_string will yield a case-insensitive result, but using _natural also sorts just "arthritis" before "arthritis » osteoarthritis", which is visually helpful
	return $tags_resorted; // return the re-sorted tags
}

/** returns @string, html list: post tags marked up with hash tags and space-delimited, very simple */
function markupTagsHashes ($arg_tags) {
	$arg_tags = arraynge($arg_tags,',');
	foreach ($arg_tags as &$arg_tag) $arg_tag = "#{$arg_tag}";
	return implode(" ", $arg_tags);
	}