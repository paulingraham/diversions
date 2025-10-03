<?php #pubsys > definitions of all functions

use League\HTMLToMarkdown\HtmlConverter; // use PHP Markdown, see https://github.com/michelf/php-markdown, and especially see https://michelf.ca/projects/php-markdown/configuration/
use Michelf\MarkdownExtra; // use HTML To Markdown for PHP, to convert Markdown back to HTML! see https://github.com/thephpleague/html-to-markdown

if ($ps) $record = new XRefRecord(); // This is a bit hacky, done in my profound ignorance of OOP, so that I can access a method (parseNestedxRefs) in a function.  Probably not the best/right way to do this, but it works.

$rss_only_posts = []; // initialize an array in the global scope for RSS-only posts

/* <##> returns a big post array, and generates a much light index array of posts; calls the other get_ functions repeatedly to parse many microposts, imgposts and macroposts */
function getPosts()
{
	journal('reading post files', 1);
	// just a little prep to make sure we've got a folder here; it's a bit untidy for this to be here; it would make more sense for this to happen in the file_management function, but that needs to come after everything, and this needs to come BEFORE everything, so …
	if (! file_exists(STAGE . '/imgs-auto')) {
		mkdir(STAGE . '/imgs-auto');
	} // `path
	global $micropost_files;
	foreach (glob('posts/*') as $fn) {
		if (preg_match("@posts/20\d\d-\d\d-\d\d[a-p]{0,1} .*$@", $fn) == 0) {
			continue;
		} // ignore filenames that don’t begin with a correctly formatted date and a space eg "2013-07-20 filename"; in practice this means that filenames without a leading date can be drafts (preg_match returns a zero if there's no match)
		if (preg_match('@(.sm.txt|.bd.md)@', $fn) == 1) { // ignore filenames with .sm.txt or .bd.md extensions — these are alternate versions, not source files for posts
			continue;
		}
		// skip stale files
//		if (!fileFresh($fn, 500)) continue; // only make posts from files that have been changed recently
		// of the files that remain …
		if (preg_match('@POSTS*@', $fn)) {			// look for filenames including “POSTS”
			$lns = array_reverse(file($fn, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
			$micropost_files[] = $fn;
			foreach ($lns as $ln) {
				if (preg_match("/^20\d\d-\d\d-\d\d[a-p]{0,1}\s.*/", $ln) == 0) {
					continue;
				} // ignore lines that don’t begin with a date, eg 2013-07-20. in practice this means that lines without a leading date can be drafts or comments. (preg_match returns a zero if there's no match)
				$posts[] = getMicropost($fn, $ln, ++$lnno);
			}
		} elseif (preg_match('@.*(jpg|gif|png)$@', $fn)) {	// look for files with common img extensions
			$posts[] = getImgPost($fn);
		} else { 																	// anything remaining is assumed to be a macropost file
			$posts[] = getMacroPost($fn);
		}
	}

	if (! $posts) { // but if not, print the bad news and quit
		echo '<h1>No posts! ☹</h1>';
		exit;
	}
	//otherwise, carry on …

	// post array maintenance and adding of some universal fields

	// remove duds, assign timestamp keys, collect field names
	foreach ($posts as $key=>$post) {
		if ($post == null) { // I’m not sure if this is a good idea, but it works; $posts[] = getMicropost() can result in an empty element in the array (and I don’t know how to make that statement result in nothing), which causes minor issues; but it’s very quick to go through and remove those empty elements
			unset($posts[$key]);
		}
		$posts[$post['timestamp']] = $post; // duplicate the post, but now with a key equal to the timestamp
		unset($posts[$key]); // remove the original
	}

	krsort($posts); // sort! #optimize?

	global $rss_only_posts; // get the global array for RSS only posts
	foreach ($posts as $key => $post) { // it’s probably redundant to loop through the array for this, but it’s conceptually tidier... we're looking RSS-only posts...
//		if ($n++ > 100) break; echo "$n ";
		if ($post['rss_only_post']) { // if we find an RSS-only post...
			$rss_only_posts[$key] = $post; // save it to its own array
			unset($posts[$key]); // and make it a ghost!  remove it from the main posts array, so that it effectively doesn't exist for anything else: indexes, sitemap, making post files, etc... NONE of that has to be handled independently if it’s just taken out of the main post array in the first place
		}
	}

	// prepare for next/prev link building by making an array of keys we can use to reference prev/last posts
	$keys = array_keys($posts); // make an array of all keys
	// a zero-indexed array of keys is going to get confusing, so let's fix that...
	array_unshift($keys, 'temp'); // insert a bogus value so that the index is synced up
	unset($keys[0]); // unset the bogus value

	// go through the posts...
	foreach ($posts as $key=>$post) {
		$x++; // post counter, #1 = first in array, most recent

		$new_posts[$key] = $post; // copy post to new array where it can be modified

		$stillSeekingLastNonPreviewPost = true;
		$lnpp = 1; // how many posts to look back *iterates until the last non-preview post is found)
		while (isset($keys[$x + $lnpp]) and $stillSeekingLastNonPreviewPost) { // if there is a key for the previous post...
			$prevKey = $keys[$x + $lnpp]; // get key (timestamp) of the PREV post (+1 in the arrays of posts and keys)
			$prevPost = $posts[$prevKey]; // get the prev post from the main array
			if ($posts[$prevKey]['preview']) { // if that post is a preview, we ignore it…
				$lnpp++;  // go another level deepeer looking for a non-preview post
			} else {
				$new_posts[$key]['prev_post'] = $prevPost['timestamp']; // assign key (timestamp) of prev post to the prev_post field for this post
				$stillSeekingLastNonPreviewPost = false; // we're done with this one
			}
		}

		$stillSeekingNextNonPreviewPost = true;
		$nnpp = 1; // how many posts to look ahead (iterates until the next non-preview post is found)
		while (isset($keys[$x - $nnpp]) and $stillSeekingNextNonPreviewPost) {  // if there is a key for the next post...
			$nextKey = $keys[$x - $nnpp]; // get key (timestamp) of the NEXT post (-1 in the arrays of posts and keys)
			$nextPost = $posts[$nextKey]; // get the next post from the main array
			// if ($mytemp++ < 10) echo "<p>For {$post['title']}, next post candidate #$nnpp is {$posts[$nextKey]['title']}, and it’s preview status is {$posts[$nextKey]['preview']} </p>";
			if ($posts[$nextKey]['preview']) { // if that post is a preview, we ignore it…
				$nnpp++; // go another level deeper looking for a non-preview post
			} else {
				$new_posts[$key]['next_post'] = $nextPost['timestamp']; // assign key (timestamp) of next post to the next_post field for this post
				$stillSeekingNextNonPreviewPost = false;  // we're done with this one
			}
		}
		unset($prevKey, $nextKey);
	}

	$posts_count = count($posts);
	echo "&nbsp;($posts_count posts found)";

	$posts = $new_posts; // replace $posts array with $new_posts array

	return $posts;
}

/* <##> reads a micropost */
function getMicropost($fn, $ln, $lnno)
{

$post = array('canonical' => null,
'citekey' => null,
'cites' => null,
'content' => null,
'content_premium' => null,
'date' => null,
'description' => null,
'description_audio' => null,
'hidelink' => null,
'html' => null,
'html_premium' => null,
'link' => null,
'link_quoted' => null,
'lock' => null,
'mustindex' => null,
'noindex' => null,
'notpremium' => null,
'podcast_only' => null,
'post_audio' => null,
'post_audio_desc' => null,
'post_audio_dur' => null,
'post_audio_dur_time' => null,
'post_audio_size_bytes' => null,
'post_class' => null,
'post_img' => null,
'premium' => null,
'preview' => null,
'priority' => null,
'psid' => null,
'rss_no_post' => null,
'rss_only_post' => null,
'slug' => null,
'source_file' => null,
'title' => null,
'title_working' => null,
'url' => null,
'url_live' => null);

	journal("reading micropost source file [ ln $lnno of $fn ]", 2);
	// echo "&nbsp;&nbsp;processing $discovery_id<br>";
	$post['post_class'] = 'micro';
	$post['source_file'] = basename($fn);
	$ln = preg_replace("@\s+http@", '---http', $ln); // set up trailing raw URLs for autodetection as featured URLs (that is, raw URLs preceded by whitespace; replace the space with more explicit, standard delimeters for parseSloppyData function)
	$data = parseSloppyData($ln);
	$post['date'] = $data[0];  // assume the first item is a date
	$post = get_timestamp_from_post_date($post); // gets a timestamp from the date, optionally modified by day-order
	if (strpos($data[1], '§') !== false) { // if the second item contains the tick § symbol, then it is content that contains an embedded title
		$post['title'] = get_marked_text($data[1], '§'); // extract the title, using ticks
		$post['title'] = massage_title($post['title']); // remove stray punctuation
		$post['content'] = $content = str_replace('§', null, $data[1]);
	} elseif (strlen($data[1]) < 100 and strlen($data[2]) > 50) { // otherwise, the second item SHOULD be a title in itself (but it is possible that it’s content and a title has been neglected, so we do some rudimentary checking of links, assuming that a title must be shorter than 100 chars and content must be longer than 50; this is hardly foolproof, but will cover most scenarios, and the worst-case scenario isn’t that bad
		$post['title'] = $data[1];
		$post['content'] = $content = $data[2]; // get the content
	}
	if ($post['title'] == '') { // still no title?
		journal('warning: no title found for micropost dated ' . $post['date'] . '; ignoring it', 2, true);
		$post = [];

		return $post;
	}

	if (count($data) > 2) {
		$metadata = array_splice($data, 2, count($data) - 2); // anything in the data array after the content is assumed to be metadata; this makes an array of that metadata only, if it's there
		global $md_syns; // get the array of synonyms for metadata
		/* The synonyms array is constructed from a simple text file ("metadata-synonyms.txt") by a function (getArrFromFile) in settings.php. The purpose of this is to enable (1) nearly frictionless expansion of the synonym list and (2) simple and readable in_array checks to see if user-submitted metadata matches any term in a list of synonyms, eg, if user uses "url" in the metadata for a post, we check to see if it's a link post like so:
		if (in_array($md, $md_syns["link"])) $post["url"] = extract1stUrl($content);
		if "url" is in the array of metadata synonyms for "link", then do stuff */
		foreach ($metadata as $mdo) { // look at each piece of metadata found (mdo = metadata original)
			$md = mb_strtolower($mdo); // work with a lowercase version, to reduce the potential for matching failures
			if ($colonPos = strpos($mdo, ':')) { // find first occurrence of a colon in the string
				$md = substr($mdo, 0, $colonPos); // echo "<p>md = $md</p>";
				$md_pt2 = trim(substr($mdo, $colonPos + 1)); // echo "<p>md_pt2 = `$md_pt2`</p>";
			}
			if ($md == 'http' or $md == 'https') {
				$post['url'] = $md . ':' . $md_pt2;
			} // reassemble URL
			if (preg_match('@(jpg|gif|png)$@', $md, $tmp)) {
				$post['post_img'] = "imgs/{$mdo}";
			}

			if (preg_match('@mp3$@', $md, $tmp)) {
				$post['post_audio'] = "media/{$mdo}";
				$post['post_audio_size_bytes'] = filesize(_ROOT . '/' . $post['post_audio']); // audio file size in bytes
				$post['post_audio_dur'] = getDurationOfAudioInSecs(_ROOT . '/' . $post['post_audio']);
				$post['post_audio_dur_time'] = intval($post['post_audio_dur'] / 60) . ':' . str_pad($post['post_audio_dur'] % 60, 2, '0', STR_PAD_LEFT); // The seconds may be <10secs and those need to zero-padded. Surprisingly tricky, but str_pad does the job, adding 0 only to increase 1-9 to 01-09, but leaving 10-59 alone. I think ;-)
			}

			if (preg_match('@psid(\d+)$@', $md, $tmp)) {
				// 7 digit number prefixed by 'psid'?  you, sir, have found a PubSys ID number, a new unique/consistent identifier added 2023-09-03
				global $psidsArr; // we hafta keep track of these fuckers
				if (strlen($tmp[1]) !== 7) {
					exit("Ruh roh, psid {$tmp[1]} is not 7 digits.");
				}
				if (in_array($tmp[1], $psidsArr)) { // is this new one a dupe? abort, abort!
					exit("Ruh roh, psid {$tmp[1]} is a dupe.");
				}
				$psidsArr[] = $post['psid'] = $tmp[1]; // save it to the psids array, and the post array
			}

			if (in_array($md, $md_syns['link'])) {
				$post['url'] = extract1stUrl($content);
				$post['hidelink'] = true;
			} // hack! by default, do not show the link (because prettifyURL didn’t really work out); I should really fix that function and/or properly strip it out of the code, but this will do the job
			if (in_array($md, $md_syns['hidelink'])) {
				$post['url'] = extract1stUrl($content);
				$post['hidelink'] = true;
			}
			if (in_array($md, $md_syns['description'])) {
				$post['description'] = $md_pt2;
			}
			if (in_array($md, $md_syns['description_audio'])) {
				$post['description_audio'] = getdescription_audio($md_pt2);
			}
			if (in_array($md, $md_syns['priority'])) {
				$post['priority'] = $md_pt2;
			}
			if (is_numeric($mdo) and inRange($mdo, 1, 10)) {
				$post['priority'] = $mdo;
			}
			if (in_array($md, $md_syns['lock'])) {
				$post['lock'] = true;
			}
			if ($md == 'rss_no_post') {
				$post['rss_no_post'] = true;
			}
			if ($md == 'rss_only_post') {
				$post['rss_only_post'] = true;
			}
			if ($md == 'podcast_only') {
				$post['podcast_only'] = true;
			}
			//			if ($md == "fblike")									$post["fblike"] = true;
			if ($md == 'mustindex') {
				$post['mustindex'] = true;
			}
			if ($md == 'noindex') {
				$post['noindex'] = true;
			}
			if (in_array($md, $md_syns['preview'])) {
				$post['preview'] = true;
			}
			if (in_array($md, $md_syns['slug'])) {
				$post['slug'] = $md_pt2;
			}
			if (in_array($md, $md_syns['canonical'])) {
				$post['canonical'] = $md_pt2;
			}
			if (! $post['citekey']) {
				$post = deal_with_citekeys($mdo, $post);
			} //psmod
			// now for micro post-only metadata...
			if ($md == 'class') {
				$post['post_class'] = 'macro';
			} // psot-class override option; rather than using whatever the user typed, or checking it, just go with “macro” because it’s really the only option anyway
			if (in_array($md, $md_syns['tags'])) {
				$tags_given = $md_pt2;
			}
		} // end of metadata loop
	} // end of metadata if

	/* Some custom markup for microposts: convert to markdown (for simple data entry, a convention of one-post-per-line is enforced for Microposts; multiparagraph, multiblock content still possible, but it must be marked up; simple paragraph breaks, blockquotes, headings, lists can all be made Markdown-ready; generally use ……… to insert newlines wherever you would use them in Markdown.  Leading and/or trailing spaces permissible in all cases.

	PubSys		Markdown
	………			\n\n				newline pair
	>>>			\n\n>				newline pair + any blockquoted para
	## 			\n\n##			newline pair + ## (also works with ###)
	•••			\n*				newline + ul list item mark
	111.			\n*_				newline + ol list item mark */

	$content = preg_replace('| *……… *|', "\n\n", $content);
	$content = preg_replace('| *>>> *|', "\n\n>", $content); // >>> (triple greater-than) denotes both a paragraph break AND a blockquote, which in Markdown is a \n\n>
	$content = preg_replace('| *(#{2,3}) *|', "\n\n$1", $content); // ## and ### denote <h2> and <h3> (must match more than 1, because a single hash is quite common)
	$content = preg_replace('| *••• *|', "\n* ", $content); // ••• denotes a UL list item; 1st item must be preceded by extra newlines (eg ………••• )
	$content = preg_replace('| *111\. *|', "\n1. ", $content); // 111 denotes an OL list item; 1st item must be preceded by extra newlines (………111.)

	$post['content'] = $content = str_replace('§', null, $content); // remove ticks

	// CONTENT FINALIZED

	$post['cites'] = getCites($post); // extracts all citations

	$post['html'] = prepareContent($content); // make HTML version of content
	// check HTML for errors and abort build when found (probably should export this to a function) #error_handling_in_posts

	preg_match_all('|<!-- !!! ERROR !!! (.+?) -->|', $post['html'], $matches);
	$errorsArr = $matches[1];
	$errCount = count($errorsArr);
	if ($errCount > 0) {
		$errPlural = ($errCount > 1) ? 's' : '';
		echo "<br><h2 class='warning'><em class='runin'>ABORT!</em> $errCount user error{$errPlural} in rendered content for post '{$post['title']}':</h2>";
		echo '<ol>';
		foreach ($errorsArr as $error) {
			if (strlen($error) > 5) {
				echo "<li>{$error}</li>";
			}
		}
		echo '</ol>';
		echo "<h3 class='warning'>The post markup</h3><pre style='white-space: pre-wrap;'>" . htmlspecialchars($post['html']) . '</pre>';
		exit;
	}
	$post = get_post_size($post);
	$len = strlen($content);
	//	if (strpos($content, "\n\n") !== false)  $multi = true; // not sure $multi matters any more
	// detect metadata in the content
	if (preg_match('@href="(.*?)">\\w+§.*?</a>@ui', $content, $matches)) {
		$post['url'] = str_replace('§', null, $matches[1]);
	} // position [1] matches the href attribute value in the post followed by anchor text containing a title-marking symbol, if any

	//	$post = get_auto_tags($post);
	$post = get_indexing_status($post); // set indexing status (must be big enough, fresh enough, or an explicitly labelled exception)
	$post = get_description($post); // if not set yet, get a marked up description, or a default one
	$post = get_post_urls($post);
	$post['link'] = "<a href='{$post['url_live']}'>{$post['title']}" . addPeriodWhereNeeded($post['title']) . '</a>';
	$post['link_quoted'] = "“{$post['link']}”";
	$post = extractTags($tags_given, $post); // Caution! This modifies the main tags array, a global effect from inside a function that otherwise affects only the data for one post. Specifically, it iterates tag tallies for each tag used in the post, and adds new tags to the database.  The post data is also updated.

	return $post; // return micropost
}

/* <##> reads an img post */
function getImgPost($fn)
{
	// img post dates, titles and optional captions are encoded in the filename separated by a space or |
	// e.g. "2013-06-11 Title | Clever caption up to ~240 chars.jpg"
	$filename = basename($fn); // basename includes extension
	$post['source_file'] = $filename; // true/exact filename for the post
	$path_parts = pathinfo($fn);
	// break filename into pipe-delimited parts
	// image posts have a completely rigid order for metadata
	// yyyy-mm-dd title | caption | tags | src-name | src-url
	//				0		 1			2			  3				4			5
	$path_parts['filename'] = preg_replace("@(\d+-\d+-\d+[a-p]{0,1})@", '$1 |', $path_parts['filename']); // insert a bar delimiter after the date, for easier exploding
	$fn_parts = array_map('trim', explode('|', $path_parts['filename']));
	$post['date'] = $fn_parts[0]; // assume the first item is a date
	$post = get_timestamp_from_post_date($post); // gets a timestamp from the date, optionally modified by day-order
	$post['title'] = $title = ucfirst($fn_parts[1]);
	$post = get_post_urls($post);
	$post['caption'] = ucfirst($fn_parts[2]);
	$tags_given = $fn_parts[3];
	$post['src_name'] = $fn_parts[4];
	$post['src_url'] = $fn_parts[5];
	global $md_syns;
	foreach ($post as $field => $data) {
		if (in_array(strtolower($data), $md_syns['null']) or $data == '') {
			unset($post[$field]);
		}
	}
	if ($post['caption']) {
		$post['description'] = html_to_description($post['caption']);
	}
	journal("reading img post file [ $fn ]", 2);
	if ($post['src_url']) {
		$post['src_url'] = 'http://' . str_replace(':', '/', $post['src_url']);
	}
	if ($post['src_name'] and $post['src_url']) {
		$source = "<a href='{$post['src_url']}'>{$post['src_name']}</a>";
	}
	if ($post['src_name'] and ! $post['src_url']) {
		$source = "{$post['src_name']}";
	}
	if (! $post['src_name'] and $post['src_url']) {
		$source = "<a href='{$post['src_name']}'>{$post['src_url']}</a>";
	}
	if ($source) {
		$source = "<p style='font-size:.8em'>Source: $source</p>";
	}
	$post['ext'] = $path_parts['extension'];
	$src = ROOT_DEV . "/{$fn}"; // `path
	//	echo "$src<br>$dest";
	$new_name = $post['title_smpl'] . '.' . $post['ext'];
	$dest = STAGE . "/imgs-auto/$new_name"; // `path
	if (! file_exists($dest)) { // copy if there’s nothing there yet
		journal("copying img post to imgs-auto folder [ $new_name ]", 2, true);
		copy($src, $dest);
	}

	if (fileFresh($src, 3)) { // only copy if it hasn't been done before, or if the file has been changed recently
		// one more check (only on the recent stuff, because it’s expensive
		if (md5_file($src) !== md5_file($dest)) {
			journal("img post has been modified, replacing old image in imgs-auto folder [ $fn ]", 2, true);
			unlink($dest);
			copy($src, $dest);
		}
	}
	$post['post_class'] = 'micro-img';
	$imagedata = getimagesize($fn); // get data about the image (not just the size)
	$post['width'] = $imagedata[0];
	$post['height'] = $imagedata[1];
	$post['dims_attrs'] = $dims_attrs = $imagedata[3];
	$post['mime'] = $imagedata['mime'];
	// the content of img posts is basically just the image, with title and caption and metadata: it’s simple, but it has to be generated entirely from metadata
	$post['post_img'] = "imgs-auto/$new_name";
	if ($post['caption']) {
		$caption = "<p>{$post['caption']}</p>";
	}
	global $ps;
	if ($ps) {
		$post['indexing'] = false;
	} // indexing: never for img posts on PS (always too small)
	$post['content'] = $content = <<<IMGPOST

<img src='imgs-auto/$new_name' $dims_attrs style="">

$caption

$source

IMGPOST;
	// want more/other metadata? see evernote: "getting image metadata with php"
	$post['html'] = prepareContent($content); // make HTML version of content
	$post = get_post_size($post);
	$post = extractTags($tags_given, $post);

	return $post;
}

/* <##> reads a macropost */
function getMacroPost($fn)
{

$post = array('canonical' => null,
'citekey' => null,
'cites' => null,
'content' => null,
'content_premium' => null,
'date' => null,
'description' => null,
'description_audio' => null,
'hidelink' => null,
'html' => null,
'html_premium' => null,
'link' => null,
'link_quoted' => null,
'lock' => null,
'mustindex' => null,
'noindex' => null,
'notpremium' => null,
'podcast_only' => null,
'post_audio' => null,
'post_audio_desc' => null,
'post_audio_dur' => null,
'post_audio_dur_time' => null,
'post_audio_size_bytes' => null,
'post_class' => null,
'post_img' => null,
'premium' => null,
'preview' => null,
'priority' => null,
'psid' => null,
'rss_no_post' => null,
'rss_only_post' => null,
'slug' => null,
'source_file' => null,
'title' => null,
'title_working' => null,
'url' => null,
'url_live' => null);


	journal("reading macropost source file [ $fn ]", 2);
	$post['post_class'] = 'macro';
	$lines = file($fn, FILE_IGNORE_NEW_LINES);
	// get metadata from the filename
	$fn = basename($fn);
	$post['source_file'] = $fn;

	preg_match("@(\d+-\d+-\d+[a-p]{0,1})[ ](.*?\.\w{2,4})@", $fn, $matches); // we could match the day-order value right here, but we’ll just include it in the date and let the get_timestamp_from_post_date ƒ handle it
	$post['date'] = $matches[1];
	$post = get_timestamp_from_post_date($post); // gets a timestamp from the date, optionally modified by day-order
	$post['title_working'] = $matches[2];

	// now start looking at the contents of the file

	$post['title'] = $lines[0]; // title is the first line of the file, firm convention
	if (strlen($post['title']) > 160) {
		echo '!!!' . $post['title'];
		journal("warning: suspiciously long title, please check; getting title from filename for now: $fn ", 2, true);
		$post['title'] = preg_replace("@\d+-\d+-\d+ (.+?)\.\w+$@ui", '$1', $fn);
	}

	$paywall_marker = $header_marker = false; // #PHP8, highly destructive when I set these defaults for these vars inside the loop below!  should be fine here, but … flagged
	foreach ($lines as $line) { // go through all post lines; in most cases we're looking for a header separated from the post by a *** row

		if (inStr('*****', $line)) { // if the header seperator is found …
			$header_marker = true;
			continue;
		} // … set a flag and skip

		if (inStr('----------', $line)) { // if the end of content seperator is found …
			break; // the rest of the file effectively doesn't exist, stop looping entirely
		}

		if (preg_match('|^//|', $line)) { // if there's a PHP comment marker
			continue; // skip the line
		}

		if (inStr('<!-- === PAYWALL === -->', $line)) {  // as of 2021-12-14, this is much less important, but I left it in place so I could still save premium-only content in a field and do word counts on it... but I have not followed up on that yet
			$paywall_marker = true;
			$header_marker = false;
			continue;
		}

		if (! $header_marker) { // if the header marker hasn't been found yet …
			$metadata[] = $line; // … add the line to an array of metadata
		}

		if ($header_marker and ! $paywall_marker) {
			$content .= "$line\n"; // … add the line to the main content
		}

		if ($paywall_marker) {
			$content_premium .= "$line\n"; // … add the line to the premium content
		}
	}

	global $md_syns; // get the array of synonyms for metadata
		// see getMicropost comments for more about metadata synonyms!
		foreach ($metadata as $mdo) { // look at each piece of metadata found (mdo = metadata original)
			$md = strtolower($mdo); // work with a lowercase version, to reduce the potential for matching failures
			if ($colonPos = strpos($mdo, ':')) { // find first occurrence of a colon in the string
				$md = substr($mdo, 0, $colonPos); // echo "<p>md = $md</p>";
				$md_pt2 = trim(substr($mdo, $colonPos + 1)); // echo "<p>md_pt2 = `$md_pt2`</p>";
			}

			if ($md == 'http' or $md == 'https') {
				$post['url'] = $md . ':' . $md_pt2;
			} // reassemble URL
			
			if (preg_match('@(jpeg|jpg|gif|png)$@', $md, $tmp)) {
				$post['post_img'] = "imgs/{$mdo}";
			}

			if (preg_match('@psid(\d+)$@', $md, $tmp)) {
				// 7 digit number prefixed by 'psid'?  you, sir, have found a PubSys ID number, a new unique/consistent identifier added 2023-09-03
				global $psidsArr; // we hafta keep track of these fuckers
				if (strlen($tmp[1]) !== 7) {
					exit("Ruh roh, psid {$tmp[1]} is not 7 digits.");
				}
				if (is_array($psidsArr) and in_array($tmp[1], $psidsArr)) { // is this new one a dupe? abort, abort!
					exit("Ruh roh, psid {$tmp[1]} is a dupe.");
				}
				$psidsArr[] = $post['psid'] = $tmp[1]; // save it to the psids array, and the post array
			}

			if (preg_match('@mp3$@', $md, $tmp)) {
				$post['post_audio'] = "media/{$mdo}";
				$post['post_audio_size_bytes'] = filesize(_ROOT . '/' . $post['post_audio']); // audio file size in bytes
				$post['post_audio_dur'] = getDurationOfAudioInSecs(_ROOT . '/' . $post['post_audio']);
				$post['post_audio_dur_time'] = intval($post['post_audio_dur'] / 60) . ':' . str_pad($post['post_audio_dur'] % 60, 2, '0', STR_PAD_LEFT); // The seconds may be <10secs and those need to zero-padded. Surprisingly tricky, but str_pad does the job, adding 0 only to increase 1-9 to 01-09, but leaving 10-59 alone. I think ;-)
			}
			if ($md == 'audio_desc') {
				$post['post_audio_desc'] = $md_pt2;
			}

			if ($md == 'mustindex') {
				$post['mustindex'] = true;
			}
			if ($md == 'noindex') {
				$post['noindex'] = true;
			}
			if ($md == 'rss_no_post') {
				$post['rss_no_post'] = true;
			}
			if ($md == 'rss_only_post') {
				$post['rss_only_post'] = true;
			}
			if ($md == 'podcast_only') {
				$post['podcast_only'] = true;
			}
			if ($md == 'notpremium') { // "notpremium" means that the post shouldn't be considered a "premium post", despite containing some members-only content; in other words, this flag overrides the autodetection of premium post based on the inclusion of <!-- === PAYWALL === -->
				$post['notpremium'] = true;
			}
			//			if ($md == "fblike")									$post["fblike"] = true;
			if (in_array($md, $md_syns['link'])) {
				$post['url'] = extract1stUrl($content);
				$post['hidelink'] = true;
			} // hack! by default, do not show the link (because prettifyURL didn’t really work out); I should really fix that function and/or properly strip it out of the code, but this will do the job
			if (in_array($md, $md_syns['hidelink'])) {
				$post['url'] = extract1stUrl($content);
				$post['hidelink'] = true;
			}
			if (in_array($md, $md_syns['description'])) {
				$post['description'] = $md_pt2;
			}
			if (in_array($md, $md_syns['description_audio'])) {
				$post['description_audio'] = getdescription_audio($md_pt2);
			}
			if (in_array($md, $md_syns['priority'])) {
				$post['priority'] = $md_pt2;
			}
			if (is_numeric($mdo) and inRange($mdo, 1, 10)) {
				$post['priority'] = $mdo;
			}
			if (in_array($md, $md_syns['lock'])) {
				$post['lock'] = true;
			}
			if (in_array($md, $md_syns['preview'])) {
				$post['preview'] = true;
			}
			if (in_array($md, $md_syns['slug'])) {
				$post['slug'] = $md_pt2;
			}
			if (! $post['citekey']) {
				$post = deal_with_citekeys($mdo, $post);
			} //psmod
			// the above is IDENTICAL to the metadata checking for microposts
			// now for macro post-only metadata...
			if (in_array($md, $md_syns['subtitle'])) {
				$post = extract_subtitle($post);
			}
			if (in_array($md, $md_syns['canonical'])) {
				$post['canonical'] = $md_pt2;
			}

			if ($md == 'class') {
				$post['post_class'] = 'micro';
			} // post-class override option; rather than using whatever the user typed, or checking it, just go with “micro” because it’s really the only option anyway
			if (in_array($md, $md_syns['tags'])) {
				$tags_given = $md_pt2;
			}
		}

	$post['content'] = $content . $content_premium; // the content is always ALL content, including premium!  the post is the post!

	$post['cites'] = getCites($post); // extracts all citations

	$post['html'] = prepareContent($content . $content_premium); // make the HTML version of content, uses PHP Markdown Extra to convert Markdown to HTML; for historical reasons HTML only means the HTML for posts and teasers, not the HTML for premium content

	// if (inStr("full-fledged evidence", $post['html'] )) exit("<pre>" . htmlentities($post['html']) . "</pre>");

	if ($content_premium) { // set aside the premium content for various reasons (e.g. wordcount)
		$post['content_premium'] = $content_premium;
		$post['html_premium'] = prepareContent($post['content_premium']); // convert the premium content from Markdown to HTML
		if (! $post['notpremium']) { // some posts contain some minor premium content, but should not be consider a premium post overall; they are marked with the not_premium flag in the header
			$post['premium'] = true; // set the premium flag, indicating that the post is "premium" — there is premium content
			$post['title'] .= " (Member Post)"; // append the Member-Post suffix to the title (it will be stripped when deriving a slug from the title)
		}
	}

	$post = get_post_size($post); // counts html + HTML_premium
	$post = get_indexing_status($post); // set indexing status (must be big enough, fresh enough, or an explicitly labelled exception)
	$post = get_description($post); // if not set yet, get a marked up description, or a default one
	$post = get_post_urls($post);
	$post['link'] = "<a href='{$post['url_live']}'>{$post['title']}" . addPeriodWhereNeeded($post['title']) . '</a>';
	$post['link_quoted'] = "“{$post['link']}”";
	$post = extractTags($tags_given, $post); // this can modify the main tags array, a global effect from a function that otherwise affects only the data for a single post

	return $post;
}

/* <##> make web versions (web-ready HTML5) */
function makeWebVersions()
{
	global $posts, $settings, $prepMode;
	extract($settings);
	$noLazyload = true;
	journal('making post files', 1);

	foreach ($posts as $post) {
		$n++;
		if ($prepMode and $n > 3) { // prepMode still works with the complete posts array, but does MUCH LESS with it, like making files out of only the first 3 of them (and the third generally has no changes)
			break;
		}
		if (! isset($post['title_smpl']) or $post['title_smpl'] == ' ') {
			// if there’s no title, issue a warning and skip this post (can’t save a file with a title)
			journal('warning: cannot make a post, date ' . $post['date']. ', file ' . $post['source_file'], 2, true);
			printArr($post);
			continue;
		}
		if ($post['lock']) {
			journal('skipping locked post [' . substr($post['title_smpl'], 0, 25) . ']', 2, true);
			continue;
		}

		// #2d0: it's a fairly straightforward code cleanup job to do this by this method eval('return "' . addslashes($str) . '";');
		// that is, instead of "manually" replacing var names with values (which isn't a travesty), render them as bona fide vars
		// that's how I'm doing it for the rss file; but it's a bit tedious and low priority here … maybe someday

		//	if (inStr("zoomer", $post["html"])) $GLOBALS['pageNeedsFancyZoom'] = true; // setting this here MIGHT work, but it doesn't yet: it requires changes to the variable check in javascript-setup.php that break zoomer detection for the rest of the site

		$template = prepareTemplate($post, 'guts/template-post.php'); // get the RENDERED contents of the template-post

		$thePost = str_replace('{$content}', $post['html'], $template); // BIG STEP! insert the prepared content, with translated custom markup/markdown and rendered PHP (thePost = template + content) — the output of prepareContent()

		if ($post['preview']) {
			$thePost = str_replace('<article>', "<article><h2 style='font-size:3em;color:#c66'>DRAFT — NOT FOR PUBLICATION</h2>", $thePost);
		}

		$thePost = preg_replace("/.*rss_only_line.*\n\n/", "\n<!-- one line removed by flag rss_only_line -->\n", $thePost); // Remove lines from web version if when they contains the "rss_only_line" flag (this step must follow the insertion of the HTML, because it is IN the HTML). Exasperating-edge-case: the rss_only_line directive must be placed at the END of the line (<hr><!-- rss_only_line -->), not the start (<br><!-- rss_only_line -->). Even with that arbitrary convention, it still only works with a hack, see #comment_patch

		// if we’ve gotten this far without skipping the post for any reason, then make a post file
		$postPath = $GLOBALS['filenames'][] = STAGE . "/{$post['fn']}"; // add filename to list of confirmed html files to build (`path)

		$preview_url = "{$urlbase_stage}/{$post['fn']}?rand=" . mt_rand(1, 10000); // `paths
		$preview_link = "<a href='$preview_url' target='_blank'>{$post['fn']}</a>";

		if (strpos($thePost, 'parse error') !== false) {
			journal("warning! parse error in [$preview_link]", 2, true);
		}

		// if (inStr("full-fledged evidence",$thePost)) {exit("<pre>" . htmlspecialchars($thePost) . "</pre>");}

		// Integration with #blogharvest, see get_xref_metadata for detailed notes
		if ($GLOBALS['ps'] and $xrefMetadata = get_xref_metadata($postPath, $post['psid'])) { // if there is XM from a previously saved version of this post, identified with either the filename (most posts for every build) or psid (once in a while when a slug changes)
			$thePost = $xrefMetadata . $thePost; // transplant it to the top of the post content
			}

		if (fileExistsNoChange($thePost, $postPath)) {
			journal("skipping <a href='$preview_url' target='_blank'>web version file</a> (file unchanged: [{$post['title_smpl']}]", 2, $prepMode ? true : false); // in prepMode, I want to know that the file is NOT being written; in all-posts mode, that output would be excessive
		} else { // go ahead and save it
			if (strlen($post['title_smpl']) > 50) {
				$caution = " caution: that's a pretty long filename, consider using a slug?";
			} else {
				unset($caution);
			}
			journal("making post file [$preview_link] from {$post['source_file']}{$caution}", 2, true);
			echo 'saving regular post! ••••';
			saveAs($thePost, $postPath); // `path
		}

		if ($n == 1) { // make extra useful files for the first post in the array
			$postMetadata = $post;
			$removeFields = [ // we're doing to display
				'html_premium',
				'html',
				'post_class',
				'words_round',
				'tags',
				'prev_post',
				'timestamp',
				'psid',
			];
			foreach ($postMetadata as $key=>$field) {
				if ($field == null or in_array($key, ['html_premium', 'html', 'post_class', 'words_round', 'tags', 'prev_post', 'timestamp', 'psid']) ) { // get rid of some cluttery fields
					unset($postMetadata[$key]);
				}
				}
			$metadataTable = printArrTable1($postMetadata, true, 'Post metadata'); // returns a table of post metadata converted from the post array // parameters (array, true to return instead of echo, optional title, optional size class small|medium|large, label for key column th, label for value column th
			$metadataTable = "<div style='width: 94vw; margin-left: calc(-48vw + 50%); padding: 0 1em;border: 2px solid #c66;border-radius:1em;font-size:.8em;'>$metadataTable</div>";
			$thePost_preview = preg_replace('@<body (.+?)>@', "<body $1>\n\n{$metadataTable}", $thePost);
			$thePost_preview = str_replace('<title>', '<title>🔎 ', $thePost_preview);
			$post['fn'] = '===CURRENT-POST-PREVIEW.html';
			$postPath = $GLOBALS['filenames'][] = _ROOT . "/{$post['fn']}"; // if the path isn't added to the global list, it will get cleaned up
			$preview_url = "/{$post['fn']}?rand=" . mt_rand(1, 10000); // `paths
			$preview_link = "<a href='$preview_url' target='_blank'>current post PREVIEW version</a>";
			if (fileExistsNoChange($thePost_preview, $postPath)) {
				journal("skipping $preview_link (file unchanged: {$post['fn']})", 2, true);
			} else { // go ahead and save it
				journal("making $preview_link", 2, true);
				echo 'saving post preview! ••••';
				saveAs($thePost_preview, $postPath); // `path
			}

			makeTextVersion();			// make a version of the post that is partially prepared for use in plain text contexts (social media posts)
			makeButtondownVersion();	// make a version of the post partially prepped for use with Buttondown
		} // end making files for current post
	}	// end of post loop, I think
}

/* <##> Convert a rendered post into a plain text version relatively ready for use in social media sharing (e.g. italics → ALLCAPS, links on their own line). */
function makeTextVersion()
{ /* There is much overlap between all of the post-converting functions: makeRSS, makeTextVersion, and makeButtondownVersion. In all cases, code is messy and the output is perpetually imperfect, and it’s probably impossible to make it perfect… but the text and Bd versions only have to be better than manual conversion a post, and that's a low bar. The makeTextVersion() function is the easiest of the three because the output is the most bare bones, and in particular because it never produces paywall stuff and member content, it can remove images completely, etc. */
	global $posts, $settings;
	extract($settings);

	foreach ($posts as $thePost) { // get the first post from the posts array
		break;
	}
	$theContent = $thePost['html']; // this function converts a post from the fully rendered html content (and just the content, not the "chrome" from the blog post template)

	// many modifications…
	$theContent = preg_replace("|<div class=[\"']imgbox.*?<!--/imgbox--></div>|uism", null, $theContent); // remove all images completely
//	$theContent = preg_replace('|<div x-data="loginForm.*?<!--/loginForm--></div>|uism', null, $theContent); // remove the #login form, refundant since it was moved out of the body and into the template
	$theContent = preg_replace('|<ul class="member_benefits_list">.*?<!--/member_benefits_list--></ul>|uism', null, $theContent);
	$theContent = preg_replace('/<!-- paywall markup: member start -->(.+?)<!-- paywall markup: member end -->/uism', null, $theContent); // remove member content
	$theContent = preg_replace('/<!-- rss_no_block_start -->(.+?)<!-- rss_no_block_stop -->/uism', null, $theContent); // remove all blocks excluded from RSS
	$theContent = preg_replace('/.*?rss_no_line.*?\n/', null, $theContent); // remove all lines w rss_no_line
	$theContent = preg_replace('/.*?paywall markup:.*?\n/', null, $theContent); // remove all paywall markup lines
	$theContent = preg_replace('|<style.*</style>|uism', null, $theContent); // remove all style blocks
	$theContent = preg_replace('|<a href="#fcj\d+" title="" id="frj\d+">\d+</a> |', null, $theContent); // remove footnote reference links
	$theContent = preg_replace('|<li id="fcj\d+">(.+?)</li>|', '† $1', $theContent); // convert notes list
	$theContent = preg_replace('|<li.*?>(.+?)</li>|', '👉🏻 $1', $theContent); // convert lists
	$theContent = str_replace('&nbsp;', ' ', $theContent);
	$theContent = preg_replace('|<em>et al(.{0,1})</em>|', 'et al$1', $theContent); // remove <em> from et al. before convertin all other em'd text to ALL CAPS; note that it’s looking not for a literal period following et al, but any character SUCH AS a period (but it’s a period if there's anything there), so this changes both <em>et al</em> and <em>et al.</em>

{
		// Upperize or flag other usages of the <em> and <strong> and <h*> elements. Note that it’s easy to suppress conversions just by adding a class to the element, e.g. <em class="thought">Hey</em>, I thought. <em class="thought">I don’t really want <em>this whole thought</em> to be converted to uppercase, even though I do want <em>most</em> emphasized text to be so converted."</em>
		$theContent = preg_replace_callback('|<em>(.{1,30}?)</em>|uism', function ($word) { // upperize smaller emphasized strings to all-caps
			return mb_strtoupper($word[1]); // ⚠️ must be multibyte version of function or it poisons the entire damn file;
		}, $theContent); // convert em or strong text to uppercase

		$theContent = preg_replace('|<em>(.{31,500}?)</em>|uism', "⚠️ larger italicized string (>30), upperize? ➡️$1⬅️", $theContent); // flag longer emphasized strings for editorial decision on upperizing

		$theContent = preg_replace_callback('|<h\d>(.+?)</h\d>|uism', function ($word) { //upperize headings
			return mb_strtoupper($word[1]); // ⚠️ must be multibyte version of function or it poisons the entire damn file
		}, $theContent); // convert em or strong text to uppercase

		$theContent = preg_replace_callback('|<strong>(.+?)</strong>|uism', function ($word) { // upperize strong strings
			return mb_strtoupper($word[1]); // ⚠️ must be multibyte version of function or it poisons the entire damn file;
		}, $theContent); // convert em or strong text to uppercase

		$theContent = $theContent . "\n~ Paul Ingraham, PainScience.com publisher";
		$theContent = "{$thePost['title']}\n\n{$theContent}\n\nWeb version of this post:\n\n{$thePost['url_live']}";
		preg_match_all('|<a href="(.+?)">|', $theContent, $results); // save links
}

	foreach ($results[1] as $url) {
		$theContent = $theContent . "\n$url";
	}
	$theContent = str_replace('https://www.painscience.com', 'PainScience.com', $theContent); // replace full PainSci URLs with prettified URLs
	$theContent = preg_replace('|<.+?>|', null, $theContent);
	$theContent = preg_replace('|<!--.*?-->|', null, $theContent); // remove all comments
	$theContent = tidyWhitespace($theContent);

	// okay, done building the text version, now to save it
	$path = '/Users/paul/Desktop/';
	$fn = "TEMP {$thePost['title_smpl']}.sm.txt";
	$url = "x-bbedit://open?url=file:///$path$fn";
	if (fileExistsNoChange($theContent, $path.$fn)) {
		journal("skipping <a href='$url'>current post TEXT version</a> (file unchanged: {$fn})", 2, true);
	} else { // go ahead and save it
		journal("making <a href='$url'>current post TEXT version</a> of '{$thePost['title']}'", 2, true);
		saveAs($theContent, $path.$fn); // `path
	}
}

/* <##> Convert post content into a Markdown+Buttondown version relatively ready for newsletter use. */
function makeButtondownVersion()
{ /* There is much overlap between all of the post-converting functions: makeRSS, makeTextVersion, and makeButtondownVersion. In all cases, code is messy and the output is perpetually imperfect, and it’s probably impossible to make it perfect… but the text and Bd versions only have to be better than manual conversion a post, and that's a low bar. But makeButtondownVersion() function is particularly fugly in a variety of ways, and the order of operations is really quite tricky. As of 2023-06-27 it’s very likely that it’s a bit janky and fragile. */
	global $posts, $settings;
	if (! $GLOBALS['ps']) {
		return;
	}
	extract($settings);
	foreach ($posts as $thePost) { // get the first post from the posts array
		break;
	}
	$theContent = $thePost['content']; // this post works with the content BEFORE it is rendered by prepareContent, which could and maybe should be the "makeHtmlVersion" function, which converts the source content (PHP/HTML/Markdown) into pure HTML, while this function converts it into DIFFERENT HTML/Markdown.

	if (inStr('<?php', $theContent)) { // process the PHP, mostly citations, images, and paywall includes
		$theContent = renderPhpStr($thePost['content']);
	}

	// >Q or >q mark blockquotes that are styled distinctively in the web version, and at some point I could also style them for Buttondown as well, but for the moment this code simply gets rid of the '>Q' or '>q' markup.
	$theContent = preg_replace("/>*[qQ]\s+/", '>', $theContent);

/* Given this web version output of an image:

<div class="imgbox center" style="max-width:500px">
<img class="" src="/imgs/filename.jpg" alt="This is the alt text." width="500" height="427">
<p class="img_byline">TEST BYLINE</p>
<p class="capt caphead below "><!--caption-->TEST CAPTION HEADING</p>
<p class="capt below ">TEST CAPTION&nbsp;BODY, which <em>can</em> contain <a href="https://www.painscience.com/">some</a> Markdown in the source.<!--/caption--></p><!--/imgbox--></div>

Convert to:

<figure>XXX IMAGE: filename.jpg

<figcaption>TEST CAPTION HEADING — TEST CAPTION&nbsp;BODY, which <em>can</em> contain <a href="https://www.painscience.com/">some</a> Markdown in the source.</figcaption></figure>

Note that Markdown in the source is converted to html by img() to html, then converted back to Markdown below (ignoring <figcaption>), but then must be converted back to html yet again.
*/

	if (preg_match("|<p class='img_byline'>(.+?)</p>|", $theContent, $bylineSearch)) $theByline = $bylineSearch[1]; // this is a PARTIAL, JANKY solution to including the byline in the figcaption; a few lines below I insert $theByline into the figcaption element; this works if there is ONE image with a byline, but it’s pretty janky and fails with multiples

	$theContent = preg_replace("|<p.*?><!--caption-->(.+?)<!--\/caption--></p>|", '<figcaption>$1</figcaption>', $theContent);

	$theContent = preg_replace("|<figcaption>(.+?)</p><p.+?>(.+?)</figcaption>|", '<figcaption>$1 —  $2</figcaption>', $theContent); // caption should contain all html at this point, no md

	$theContent = str_replace("</figcap", "$theByline</figcap", $theContent);

	 // TESTING: to print the output for a given post for auditing, identify it with a distinct title substr:
/* if (inStr('TESTING20250509', $thePost['title'])) {
		echo "<br><textarea cols=80 rows=100 style='font-size:1em'>" . str_replace("\n", "¶\n\n", htmlentities($theContent)) . "</textarea>";
		exit;
		} /* */

	$theContent = preg_replace('|<div class=\'imgbox[^>]*?>|', '<figure>', $theContent);
	$theContent = str_replace('<!--/imgbox--></div>', '</figure>', $theContent);
	$theContent = str_replace('<div class=\'clear\'></div>','',$theContent);
	$theContent = preg_replace("|(<img[^>]+)alt\s*=\s*'([^']+?)'(.+?>)|", "$1$3¶¶COPY THIS ALT ATTRIBUTE INTO THE IMG ELEMENT:¶alt='$2'>\n\n", $theContent); // this extracts any alt attribute and moves it to a line after image block; this will preserve it for convenient copy/paste in the final version
	$theContent = preg_replace("|<img.*?src='/imgs/(.+?)'.*?>|", "=====================================¶UPLOAD THIS FILE TO BUTTONDOWN AND CONVERT TO HTML:¶$1", $theContent);
	// the ¶ symbols get converted to linefeeds below

	// Convert paywall markup, removing most of it and replacing the starts and stops with much more spartan Buttondown template tags. As a general rule, the newsletter resembles the RSS output more than the web output, so anything excluded from RSS probably needs to be excluded here too.
	$theContent = preg_replace("/.*rss_no_line.*\n/", null, $theContent); // remove one line from RSS
	$theContent = preg_replace('|<!--\s*rss_no_block_start(.+?)rss_no_block_stop\s*-->|s', "\n<!-- removed from RSS: multiple lines -->\n", $theContent); // remove multiline content from RSS

	$theContent = str_replace('<!-- paywall markup: non-member start -->', '{% if subscriber.canUNDERSCOREbeUNDERSCOREupsold %}', $theContent);	// underscores in the Buttondown template tags need to be temporarily protected from HtmlConverter
	$theContent = str_replace('<!-- paywall markup: non-member end -->', '{% endif %}', $theContent);
	$theContent = str_replace('<!-- paywall markup: member start -->', '{% if subscriber.canUNDERSCOREviewUNDERSCOREpremiumUNDERSCOREcontent %}', $theContent);
	$theContent = str_replace('<!-- paywall markup: member end -->', '{% endif %}', $theContent);
	// replace Stripe payment links with dynamic Button upgrade links for each plan...

	$theContent = preg_replace('@</*(div|template).*?>@', null, $theContent); // 2024-10-04 fixed bug, added '?' so that just tags themselves are removed, not the content between them

	// We now have a mixture of both HTML and Markdown+Buttondown!  If we try to convert Markdown to Markdown, we get a bunch of escaping of symbols and such.  So we have to, good grief, convert the HTML to Markdown, and then immediately converting the fully Markdownified post right back to HTML! Sheesh.
	$theContent = MarkdownExtra::defaultTransform($theContent); // convert the mixture of Markdown and HTML to pure HTML...

	$html2md = new HtmlConverter(['header_style'=>'atx']);  // invoke HtmlConverter (to remove specified notes: new HtmlConverter(array('remove_nodes' => 'span div'));
	$theContent = $html2md->convert($theContent); // and back to markdown!
	// a few hacky repairs after that janky step...

$theContent = str_replace("•\n", "<p class='separator'>•</p>\n", $theContent);

	$theContent = preg_replace_callback('|<figcaption>(.+?)</figcaption>|', 	 // Having converted everything to Markdown, some content needs to be converted *back* to HTML!  HtmlConverter converts everything that it can convert to Markdown, which does not include the <figcaption> element, but does include its contents. So if there's markup in the caption, it is now Markdown … but Markdown inside any html element is protected from processing, so Buttondown will not convert it.  Which means that we need to do it HERE: that is the contents of the <figcaption> element should now be converted back to HTML.  That is, <figcaption>Some *italics* here</figcaption> is the output of HtmlConverter here, but that won't work in Buttondown — it needs to be <figcaption>Some <em>italics</em> here</figcaption>
		function ($matches) {
			$convertedMatch = MarkdownExtra::defaultTransform($matches[1]);  // Convert the match
			$convertedMatch = str_replace("<p>", "", $convertedMatch); // remove the added paragraph tags
			$convertedMatch = str_replace("</p>", "", $convertedMatch);
			$convertedMatch = trim($convertedMatch);
		   return "<figcaption>$convertedMatch</figcaption>"; // Return as markdown
		},
		$theContent); /**/

/* // TESTING: to print the output for a given post for auditing, identify it with a distinct title substr:
if (inStr('Adding injury', $thePost['title'])) {
echo "<br><textarea cols=80 rows=100 style='font-size:1em'>" . str_replace("\n", "¶\n\n", htmlentities($theContent)) . "</textarea>";
exit;
} /* */

	$theContent = str_replace('UNDERSCORE', '_', $theContent); // restore underscores to Buttondown template tags
	$theContent = str_replace('UNDERSCORE', '_', $theContent); // restore underscores to Buttondown template tags
	$theContent = str_replace('&lt;', '<', $theContent); // restore some angle brackets that got rendered into entities
	$theContent = str_replace('&gt;', '>', $theContent); // restore some angle brackets that got rendered into entities
	$theContent = str_replace('¶', "\n", $theContent); // insert some intended CRs that got nuked

	// the worst is over, but still plenty of busy work now…

	// Patch an occasional instance of a relative URL for a blog post, probably usually from an annotation for a citation.  Arguably these should simply be corrected in the source, and I will… but I will forget to do that! And this will fix it when I do.
	$theContent = str_replace('](/blog/', '](https://www.painscience.com/blog/', $theContent);

	// replace Stripe payment link urls with Buttondown template tags for {{upgrade_url}} with the product
	$theContent = str_replace('https://buy.stripe.com/14k2a96dA6ORbiEeV6', '{{ upgrade_url }}?product=pst1', $theContent);
	$theContent = str_replace('https://buy.stripe.com/aEU5mlcBYehjeuQeV3', '{{ upgrade_url }}?product=pst2', $theContent);
	$theContent = str_replace('https://buy.stripe.com/fZe0219pM8WZ72obIT', '{{ upgrade_url }}?product=pst3', $theContent);

	$theContent = preg_replace("|\[(\d+)\]\(#fcj\d+\)|", ' [$1]', $theContent); // remove note links

	$wordCount = roundDown($thePost['words_exact'] * .8); // calculate an estimated post-intro wordcount , ~80% of the whole
	$readingTime = roundDown($wordCount / 300);  // maybe these values should be in the post data?
	// make a Buttondown intro template

	$introTemplate = <<<introtemplate
		==== INTRODUCTION


		==== WORD COUNT, READ ON PAINSCI, SOCIAL MEDIA LINKS
		Continue reading below, or [on PainScience.com]({$thePost['url_live']}), about {$readingTime}-minutes more ({$wordCount} words). Comment on the [Facebook post](XXX) or [exTwitter](XXX) or [Threads](XXX), and you’re always welcome to reply directly to these emails.

		Warm Regards,<br>

		_Paul Ingraham, PainScience.com Publisher_

		<img src="https://buttondown.s3.amazonaws.com/images/8f329c03-70ec-4a5c-97c1-73023b5f0daf.png" alt="A type-logo for the newsletter with the words 'PainSci Updates' superimposed on the blue salamander mascot/logo for PainScience.com, and a wi-fi symbol over the ‘i’ in PainSci.">
		introtemplate;

	// add standard P.S. #cta_join to the intro template to non-member posts
	if (! $thePost['premium']) {
		$introTemplate .= <<<introtemplate
		\n\n{% if subscriber.can_be_upsold %}
		**P.S.** “Good” **PainSci membership** starts at <small>USD</small>
		**[$3/month]({{ upgrade_url}}?product=pst1)** to get premium emails, the main benefit, or
		**[$5/month]({{ upgrade_url}}?product=pst2)** to unlock a whole bunch of perks — podcast, archives, lots of members-only content on the website, more — or get serious with
		**[$10/month]({{ upgrade_url}}?product=pst3)** to unlock… well, not many more benefits, but the occasional perk (this tier is mostly about supportive patronage). 😜 [Compare plans](https://www.painscience.com/membership.php).
		{% endif %}
		introtemplate;
	}

	$theContent = $introTemplate . "\n\n\n\n\n\n\n\n===== title + template ↑ ======================== post content ↓ ==============\n\n\n\n\n\n\n\n" . $theContent;

	if ($thePost['post_audio']) { // inject audio template if there's audio, #podcast
		if (isset($thePost['post_audio_desc'])) {
			$post_audio_desc = ' ' .  $thePost['post_audio_desc'];
		}

		$audio = <<<AUDIO
			{% comment %} AUDIO EMBED for pst2 and pst3 members only. {% endcomment %}{% if subscriber.can_view_premium_content and subscriber.stripe_subscription.product != 'pst1' %}<p class="aside">This post has an audio version for members like you <a href="https://www.painscience.com/{$thePost['post_audio']}">listen from your web browser</a> or <a href="https://www.painscience.com/login.php?{{ subscriber.email }}">login to subscribe to the podcast</a>.</p>{% endif %}

			{% comment %} AUDIO CTA x2, one for pst1 members, another for regular subscribers, just slightly different wording, and upgrade vs join links. {% endcomment %}{% if subscriber.stripe_subscription.product == 'pst1' %}<p class="aside">This post has an audio version for PainSci members paying $5+/month. <a href="{{ manage_premium_subscription_url }}">Upgrade now</a>.{$post_audio_desc}</p>{% endif %}{% if subscriber.can_be_upsold and subscriber.stripe_subscription.product != 'pst1' %}<p class="aside">This post has an audio version for PainSci members. <a href="{{ upgrade_url}}?product=pst2">Join now</a>.{$post_audio_desc}</p>{% endif %}
			AUDIO;
		$theContent = $audio . "\n\n\n\n\n\n" . $theContent;
	}

	// minor fine tuning
	$theContent = str_replace(' ❐', null, $theContent);

	// if ($thePost['psid'] == 3322820) {echo "<pre>" . htmlentities($theContent) . "</pre>";}

	$theContent = str_replace('Members-only post unlocked past this point. Welcome!', "____________________________________________\n\n***Members-only post unlocked past this point. Welcome!***", $theContent);

	// some whitespace adjustments for readability, because this is a document that I will definitely tinker with directly
	$theContent = str_replace('<figure>', "\n\n\n<figure>\n\n", $theContent); // vertical whitespace around <figure>
	$theContent = str_replace('<figcaption>', "\n\n<figcaption>", $theContent); // vertical whitespace before <figcaption>
	$theContent = str_replace('</figure>', "</figure>\n\n\n", $theContent); // // edge case! add whitespace after </figure> (it gets stripped out by the conversion to markdown, not sure what that's about).  But I also want extra for readability.
	$theContent = str_replace("\n#", "\n\n\n\n#", $theContent); // add whitespace above headings for readability
	//	$theContent = str_replace("{% if subscriber", "\n\n\n\n{% if subscriber", $theContent); // add whitespace above conditional Buttondown template tags
	$theContent = preg_replace("|\n(\d+)\. |", "\n\n$1. ", $theContent);

	// echo "<br><pre>" . htmlentities($theContent) . "</pre><br>";

	// add title and endmark to finish!
	$theContent = "{$thePost['title']}\n\n{$theContent}\n\n<figure><img src='https://buttondown.s3.amazonaws.com/images/1e4c1d47-0715-4578-b5df-a4182e00d6b7.png' alt='Illustration of the blue salamander logo/mascot for PainScience.com.'></figure>";

	// okay, done building the text version, now to save it
	$path = '/Users/paul/Desktop/';
	$fn = "TEMP {$thePost['title_smpl']}.bd.md";
	$url = "x-bbedit://open?url=file:///$path$fn";
	if (fileExistsNoChange($theContent, $path.$fn)) {
		journal("skipping <a href='$url'>current post NEWSLETTER version</a> (file unchanged: {$fn})", 2, true);
	} else { // go ahead and save it
		journal("making <a href='$url'>current post NEWSLETTER version</a> of '{$thePost['title']}'", 2, true);
		saveAs($theContent, $path.$fn); // `path
	}
}

/* <##> make the home page (index.html) */
function makeHomepage()
{ // outputs a post index (a link list), which is then included on the home page, which is rendered
	global $ps;
	if ($ps) {
		return;
	} // #psmod: this ƒ is redundant for PS blog production, a trivial and slightly confusing difference: instead of generating an post-list in guts and index.html for the html folder, the make_sy_indexes ƒ creates a bunch of full inline posts in guts to be included by blog.php (which echoes the structure of articles.php and tutorials.php files in relationship to their respective subdirs)
	journal('making home page', 1);
	journal('making home page table of all posts', 2, true);
	$index_str = make_post_index();
	$fn = 'guts/posts-all-index-links.html';
	if (fileExistsNoChange($index_str, $fn)) {
		journal("post link list unchanged, <em>not</em> writing file: $fn", 2, true);
	} else {
		journal("<strong>post list changed</strong>, writing file: $fn", 2, true);
		saveAs($index_str, $fn);
	}

	// renders the home page template file (which includes the index-post-list)
	$home_page_str = renderPhpFile('guts/template-home-page.php', false, true);

	$fn = STAGE . '/index.html'; // `path
	if (fileExistsNoChange($home_page_str, $fn)) {
		journal("home page unchanged, <em>not</em> writing file: $fn", 2, true);

		return;
	}
	journal("<strong>home page changed</strong>, writing file: $fn", 2, true);
	saveAs($home_page_str, $fn);
}

/* <##> Prepare post content by processing Markdown and my custom extensions to it, especially rendering PHP, mostly cite() calls, and some tidying.  */
function prepareContent($content)
{

// 1.	pubsys custom content shorthands
	// #2do: this should be moved to its own function

	// 	* process custom markup for the easyImg function
	$content = prepareEasyImg($content);

	//		simple substitution shorthands (no synonyms), just one right now
	//		* custom separator
	$content = preg_replace("/•+[\r\n]/", "<p class='separator bullet'></p>\n", $content);

	//	$content = str_replace_first(' PainScience.com', ' [PainScience.com](https://www.painscience.com/)', $content); // replace the first unlinked usage of PainScience.com in a post with a linked version; include a leading space, because that will distinguish it from linked usages

	//		shorthands with synonyms (“sh” for shorthand)
		$sh_syns = getArrFromFile('synonyms-pubsys-shorthands.txt', true); // sh for shorthand
		$crs = "[\r\n]{1,3}"; // the vertical space pattern gets used a lot, so var it
		$sh_pattern = "@{$crs}!(\w{2,30}){$crs}@";

	// look for shorthands: prefixed by ! and isolated vertically
	if (preg_match_all($sh_pattern, $content, $matches, PREG_PATTERN_ORDER)) {
		$shs = $matches[1];
	}

	if ($shs??null) {
		foreach ($shs as $sh) {
			// for each shorthand:
			// 1. check to see if it is a known synonym
			// 2. look for and capture both the shorthand and
			//	 then white-space, then replace sh with content
			if (in_array($sh, $sh_syns['clear'])) {
				$content = preg_replace("@({$crs})!{$sh}({$crs})@", "$1<br style='clear:both'>$2", $content);
			}

			if (in_array($sh, $sh_syns['break'])) {
				$content = preg_replace("@({$crs})!{$sh}({$crs})@", '$1<br>$2', $content);
			}

			if (in_array($sh, $sh_syns['stars'])) {
				$content = preg_replace("@({$crs})!{$sh}({$crs})@", "$1<div class='separator stars'>&#9733; &#9733; &#9733;</div>$2", $content);
			}

			if (in_array($sh, $sh_syns['sidebar'])) {
				$content = preg_replace("@({$crs})!{$sh}{$crs}(.*?)({$crs})@", "$1<p class='sidebar'>$2</p>$3", $content);
			}
		}
	}

	// 2. process PHP Markdown using MarkdownExtra
	$content = MarkdownExtra::defaultTransform($content);
	//	if (inStr("full-fledged evidence",$content)) {echo "<pre>" . htmlspecialchars($content) . "</pre>";}

	$content = str_replace("\n\n<!--", '<!--', $content);  #comment_patch — This handles a frustrating edge case. MarkdownExtra parses comments strangely, inserting CRs around them. This is problematic for directives like "rss_only_line" — without a fix, it will separate directives like rss_only_line from the lines to which they refer. I've studied the behaviour (too much), and been unable to figure out exactly what it's doing or how to prevent it, and it's not worth more energy (like, ever again). The bottom line is they do indeed get separated by vertical whitespace. Even patching this is a bit awkward and unclear, because there's no way to know the order… and fixing one order breaks the other… and so the fix doesn't just require this replacement, but a very arbitrary convention for the order.

	// More custom Markdown extensions that follow MarkdownExtra processing:
	// >Q or >q mark blockquotes that should be styled as a featured quotes (and >~ marks the attribution line). A small-q is for regular featured quotes; capital Q denotes large-type appropriate for short quotes.
	$content = preg_replace("/<blockquote>\s*<p>\s*[q]\s+/", "<blockquote class='featured'><!--tag:qt--><p>", $content);
	$content = preg_replace("/<blockquote>\s*<p>\s*[Q]\s+/", "<blockquote class='short'><!--tag:qt--><p>", $content);
	$content = preg_replace('/<p>~/', "<p class='attr'>", $content);

	// if (inStr('t three of what has tu',$content))  echo "  →".htmlentities($content)."←  <br>"; // prints content for a specific post right before parsing it with renderPhpStr
	// 3. render PHP (but only if there’s PHP to render)
	if (inStr('<?php', $content)) {
		$content = renderPhpStr($content);
	} //optimize?

	// 4. minification — reduce and standardize runs of whitespace (spaces and CRs) to minimize trivial file modification noise
	$content = preg_replace('@[ ]{2,20}@', ' ', $content);
	$content = preg_replace("@[\r\n]{3,20}@", "\n\n", $content);

	$content = stripslashes($content);

	return $content;
}

/* <##> process custom markup for calls to the easyImg function. */
function prepareEasyImg($content)
{
	// converts markup like <<picture.jpg---Caption etc>> to easyimg('picture.jpg---Caption etc');
	// easyImg can be marked up two ways:
	$content = "\n\n{$content}\n\n";

	// look for easyImg calls that are vertically isolated, begin with an image filename, and a similar, alternate search for ones wrapped in << >> (easier than lumping it all into one pattern)
	$easyImg_type1 = "/[\r\n]([\w-]{2,50}\.(jpg|jpeg|gif|png).*?)[\r\n]/ui";
	$easyImg_type2 = '/<<(.*?)>>/';
	$replace = "<?php echo easyImg('\$1'); ?>";
	$content = preg_replace($easyImg_type1, "\n\n" . $replace . "\n\n", $content);
	// some linefeeds are put back in, because they got "used up" by the match and need to back or the easyImg calls end up inlined with adjacent blocks, with some slightly oogy consequences

	// now that has been dealt with, it’s safe to convert all remaining easyImg requests marked with the << >> syntax
	$content = preg_replace($easyImg_type2, $replace, $content);

	// tricky step now: pre-convert runs of tabs, which are VERY nice to use for data entry (and they better be given the time I've put into making this work), but they cause other troubles; first, for reasons unknown, tab runs tend to get strangely converted to tab-space pairs by str_replace and possibly other functions; second, markdown also screws with tab runs, so to protect these function calls from markdown, it's needful to convert
	// what this does: match the contents of easyImg calls, and the apply a second replacement function to the whole match, swapping tab runs for ---
	$content = preg_replace_callback("/easyImg\('(.*?)'\);/uis", function ($matches) {
		return preg_replace("@[\t]+@", '---', $matches[0]);
	}, $content);

	$content = preg_replace_callback("/easyImg\('(.*?)'\);/uis", function ($matches) {
		return "easyImg('" . addslashes($matches[1]) . "')";
	}, $content); // I add slashes so that function calls like easyImg can contain hashes; I take the slashes out after PHP has been parsed

	return $content;
}

/* <##> make the main and member #RSS feeds */
function makeRSS($max = 25)
{
	journal('making the RSS feeds (main + member)', 1);
	/*	if (!isset($_GET["rss"])) {
		journal("skipping the RSS feed", 1); return;
		}
		journal("making the RSS feed", 1); */
	/* Generates an RSS file using only $max of the most recent posts.  The original post array is not sorted, so this is a bit tricky.  The index array is sorted, so we use that, using the title_smpl to match it to the original, full post in the main posts array. This is important, because the searching is computationally expensive; using this method, we only ever do a small amount of searching through the big array.  */
	journal("checking $max recent posts", 2, true);
	global $posts, $rss_only_posts;
	$postsWithRSS = $posts + $rss_only_posts; // merge the arrays (don’t use array_merge, because that fucks with all the keys)
	krsort($postsWithRSS); // sort it again, and this is actually critical, because RSS only deals with recent posts and assumes dated post order!
	global $settings;
	extract($settings);
	foreach ($postsWithRSS as $post) {
		if ($post['preview'] or $post['rss_no_post'] or $post['podcast_only']) { // exclude post previews & posts explicitly excluded from RSS
			continue;
		}
		extract($post); // get all the data for the found post

		$date_rss = date('D, d M Y H:i:s -0700', $timestamp); // get the date for the post in RSS-friendly format
		if ($n++ == $max) {
			break;
		}
		journal("adding a post to the RSS feeds [ $title_smpl ]", 3);
		if ($n == 1) {
			$last_build_date = $date_rss;
		} // make the build date equal to date of most recent post
		$title = numericEntities($title); // RSS will choke on named entities like &ldquo; so a special function is needed to convert special chars to numeric entities specifically
		$content = $html; // output the post content as HTML

		// if (inStr("full-fledged evidence", $content)) exit("<pre>" . htmlentities($html) . "</pre>");

		// Make some changes to content to prepare it for RSS, mostly removing or simplifying common components.  Starts with generic exclusions of content from RSS, either individual paras marked with <!-- rss_no_line --> and multiline content marked with <!-- START/rss_no_block_stop -->

		$content = preg_replace("| *<span(.+?)x-show='!member'(.+?)>LOGIN</span>|", '', $content); // remove standard login prompt from RSS

		$content = preg_replace("/.*rss_no_line.*\n/", "\n<!-- removed from RSS (one line) -->\n", $content); // remove one line from RSS
		$content = preg_replace('|<!--\s*rss_no_block_start(.+?)rss_no_block_stop\s*-->|s', "\n<!-- removed from RSS: multiple lines -->\n", $content); // remove multiline content from RSS
		$content = preg_replace("@src\s*=\s*(['\"])imgs@", "src=$1https://$domain/imgs", $content); // covert src URLs from relative to absolute
//		$content = preg_replace("@href=(['\"])/blog@", 'href=$1/blog', $content); // not sure what this was for, but removing it changes nothing
		$content = preg_replace("@<div class='caption'>(.*?)</div>@", "<div class='caption'><small>$1</small></div><br>", $content); // add <small> and a <br> to captions
		$content = str_replace("<p class='separator bullet'></p>", '<center>•</center>', $content); // convert class-based separator bullets to a plain bullet that doesn't need a stylesheet
		$content = preg_replace("|<aside class=[\"'].+?[\"']>|", "<br><hr>\n\n<div>", $content);
		$content = preg_replace("|</aside>|", "</div>\n\n<hr><br>", $content);

		$content = str_replace(" loading='lazy'", null, $content); // remove loading=lazy	attributes, unnecessary in feed (probably doesn’t hurt either)
		if ($url) { // if the post has a featured URL
			$url_rss = "\n\t<link>$url</link>";
			$url_rss_link = "<p>Featured link for this post: $url_rss</p>";
			$url_pretty = '<p><small>' . prettifyURL($url) . '</small></p>';
			$url_pretty = "<p>[<a href='$url'>Go to the link featured in this post</a>]</p>";
		}

		if ($GLOBALS['ps']) { // #psmod: tweaks for the PainSci RSS feed, mostly simplifications, starting with explicit exclusions, and moving on to a variety of page elements that won't look good in RSS (e.g. pull quotes)

			$old = ($post['timestamp'] < 1727679601) ? '-old' : ''; #dated_content Set a flag which will be used to append "-old" to the template filename to use the old template that defines the GUID with a URL, for all posts from before 2024-10-01 ("Plug your sodium channels with VX-548"). Going forward, the default template uses the posts PSID for the URL. This line + 2 usages of the $old var + the two old templates can be removed after about 2 months.  // echo $post['title'] . " — $old <br>";

			$content = preg_replace('@<a href="#fcj\d+" id="frj\d+">(\d+)</a>@', ' [$1]', $content); // disable footnote reference links, which do not play nicely with RSS
			// if (inStr("full-fledged evidence", $content)) exit("<pre>" . htmlentities($html) . "</pre>");
			$content = preg_replace('@<!--=== PULL QUOTE === --><pq>(.+?)</pq>@', "<u><strong>$1</strong></u>", $content);
			$content = preg_replace('@<p><!--=== PULL QUOTE === -->(.+?)</p>@', null, $content); // completely remove standalone pull-quotes (which are usually duplicates or near-duplicates of text)

			$content = preg_replace("@<p class='capt(.*?)'>(.+?)</p>@", '<p>[Image caption: $2]</p>', $content);
			$content = preg_replace("@<a class='zoomer.+?/a>@", null, $content); // removes zoomer buttons markup
			$content = preg_replace("@<span class='pupb'.+?<span class='pupw[^<]+>(.*?)<span class='pupx'.*?</span></span>@", ' [ $1 ] ', $content); // replaces simple popup markup with just the popup content wrapped in square brackets
			// This next bit is hack to block my responsive-image solution in RSS, which involves a pair of img elements handled with different CSS at different window sizes; the "constrained" image is marked with "<!-- constrained IMG START -->" and "…END -->". I remove the inner comment delimiters to comment-out the whole image (much easier than cooking up reliable regex pattern to remove the whole thing):
			$content = str_replace('<!-- constrained IMG START -->', '<!-- constrained IMG START ', $content);
			$content = str_replace('<!-- constrained IMG END -->', 'constrained IMG END -->', $content);
			// remove inline imgs altogether, since they are doomed to render poorly, source example: <img class='inline ' src='/imgs/smiley--sq-15x15-<1k.png' width='16' height='16' alt='' style='border-width:0px; border-style:none; display:inline;'>
			$content = preg_replace("@<img class='inline.*?>@", null, $content);

			// So far the content string for the post contains everything, both member and non-member content. Now we fork the content into $content and $content_member, removing members-only content from the free version of the post and vice versa.

			// CREATE MEMBER VERSION OF THE POST by deleting teaser (non-member) content, which is delimited by <!-- paywall markup: non-member start/end -->. This will leave regular content intact. This does not change the $content variable — it just creates a modified copy of it in $content_member, and then saves appends it to $rss_posts_member.
			$content_member = preg_replace('|<!-- paywall markup: non-member start -->(.+?)<!-- paywall markup: non-member end -->|s', null, $content);
			$content_member = preg_replace('|<div.{0,100}x-show=["\']\!member["\'].{0,100}>.*?</div>|s', null, $content_member);
			$audio_blurb_top = $audio_blurb_bottom = null;
			if ($post['post_audio'] and $title !== 'Podcast at last!') { //podcast_content #dated_content // had to special case the inclusion of the standard audio blurbs for the podcast announcement post because it was conspicuously redundant
				$audio_blurb_top = "<p><em><small>There is an audio version of this post ({$post['post_audio_dur_time']}) in the PainSci Updates podcast for members only. See the end of the post for more information.</small></em></p>\n\n<hr>\n\n";
				if (time() < 1712849686 + 90 * 24 * 60 * 60) {
					$newstuff = 'new (!) ';
				}
				$audio_blurb_bottom = "<p><em><small>There is an audio version of this post ({$post['post_audio_dur_time']}) in the {$newstuff}PainSci Updates podcast for members only. You can subscribe to the podcast much like you subscribe to this feed, but with a podcast app. You just need your personal address for the podcast, available on your PainSci account page.</small> {$post['post_audio_desc']}</em></p>";
			}

			// Generate one MEMBER RSS POST
			$rss_posts_member .= eval('return "' . addslashes(file_get_contents("template-rss-member-post.xml", true)) . '";'); // Build the member version of the post from the member template, and append it to a string containing all member posts so far. The template contains "{$content_member}" which will be replaced with the value of $content_member for this post (along with other vars).

			// Now finish the non-member version of the post by removing member content.
			$content = preg_replace('|<!-- paywall markup: member start -->(.+?)<!-- paywall markup: member end -->|s', null, $content); // Delete member content.
			$content = preg_replace('|<[/]*?template.*?>|', null, $content); // Remove <template> elements, which RSS reeders may not know what to do with (resulting in non-member content being invisible, which is bad). This is mostly due to the <template> element.
		}

		// Generate one REGULAR RSS POST
		$template_file_contents = file_get_contents("template-rss-main-post.xml", true);
		ob_start();
		eval("?>$template_file_contents"); // On the use of eval in the PainSci CMS: craftdocs://open?blockId=D8BB4DEF-66B7-4395-9020-1CACBE6BBFC4&spaceId=bc7d854c-3e5b-a34e-4850-a6d2f31a1a59
		$rss_post = ob_get_contents();
		ob_end_clean();
		$rss_post = eval('return "' . addslashes($rss_post) . '";'); // substitute values in the RSS post template with values filled in
		// if (inStr("Charles", $content)) exit(htmlspecialchars("<pre>$rss_post</pre>"));
		$rss_posts .= stripslashes($rss_post); // remove the slashes we just added
		$rss_posts = preg_replace("|\n{3,5}|", "\n\n", $rss_posts); // standardize vertical whitespace (to minimize spurious whitespaces diffs)
		unset($url, $url_rss, $url_pretty); // cleanup some vars
	}

	// We now have a string containing all posts: $content.  For PainSci, that string has had all member content removed, leaving any teaser content.  And for PainSci, there's a second string containing all posts — $content_member — which has all teaser stuff removed.

	// Generate the REGULAR RSS FILE
	$rss_str = eval('return "' . addslashes(file_get_contents('template-rss-main-channel.xml', true)) . '";');
	// On the use of eval in the PainSci CMS: craftdocs://open?blockId=D8BB4DEF-66B7-4395-9020-1CACBE6BBFC4&spaceId=bc7d854c-3e5b-a34e-4850-a6d2f31a1a59
	$fn = "html/feed-{$sitecode}.xml";
	$rss_str = tidyRSS($rss_str); // some tidying to make the file easier to audit
	if ($GLOBALS['ps']) {
		$fn = _ROOT . '/stage/rss.xml';
	} //psmod, non-generic save location for rss, to match legacy location // `path
	if (fileExistsNoChange($rss_str, $fn)) { //`path
		journal("main RSS feed unchanged, <em>not</em> writing file: $fn", 2, true);
	} else {
		journal("<strong>main RSS feed changed</strong>, writing file: $fn", 2, true);
		saveAs($rss_str, $fn);
		if ($GLOBALS['ps']) {
			saveAs("This is a COPY of the main production feed file /stage/rss.xml, just saved in the same location as the member feed files (rss-member-tH7MvsD7.xml & rss-member-podcast-hg4v437k.xml), which is entirely for convenience (and the member feeds need to live in /incs). It’s just handy to have all in one place for easier comparison.\n\n" . $rss_str, _ROOT . '/incs/rss-main.xml');
		}
	}

	// Generate the MAIN MEMBER RSS FILE
	if ($GLOBALS['ps']) {
		$rss_str_premium = eval('return "' . addslashes(file_get_contents('template-rss-member-channel.xml', true)) . '";');
		// On the use of eval in the PainSci CMS: craftdocs://open?blockId=D8BB4DEF-66B7-4395-9020-1CACBE6BBFC4&spaceId=bc7d854c-3e5b-a34e-4850-a6d2f31a1a59
		// And then save that feed with a different name:
		$fn = _ROOT . '/incs/rss-member-tH7MvsD7.xml';
		$rss_str_premium = tidyRSS($rss_str_premium); // some tidying to make the file easier to audit
		if (fileExistsNoChange($rss_str_premium, $fn)) { //`path
			journal("member RSS feed unchanged, <em>not</em> writing file: $fn", 2, true);
		} else {
			journal("<strong>member RSS feed changed</strong>, writing file: $fn", 2, true);
			saveAs($rss_str_premium, $fn);
		}
	}
}

/*<##> make the #podcast (#rss feed for audio versions of posts, for members only)  */
function makePodcast($max = 100)
{
	journal('making the podcast feed', 1);
	/* Generates an RSS file using only $max of the most recent posts.  The original post array is not sorted, so this is a bit tricky.  The index array is sorted, so we use that, using the title_smpl to match it to the original, full post in the main posts array. This is important, because the searching is computationally expensive; using this method, we only ever do a small amount of searching through the big array.  */
	journal("checking $max recent podcast episodes", 2, true);
	global $posts, $rss_only_posts; // get the RSS-only posts (not many!)
	$postsWithRSS = $posts + $rss_only_posts; // merge the arrays (don’t use array_merge, because that fucks with all the keys)
	krsort($postsWithRSS); // sort it again, and this is actually critical, because RSS only deals with recent posts and assumes dated post order!
	global $settings;
	extract($settings);
	foreach ($postsWithRSS as $post) {
		if ($post['preview'] or $post['rss_no_post']) {
			continue;
		} // exclude post previews & posts explicitly excluded from RSS
		unset($title, $date_rss, $psid, $url_live, $post_audio, $post_audio_size_bytes, $post_audio_dur, $description, $link, $link_quoted, $url, $url_rss, $url_pretty, $post_img); // cleanup some vars
		extract($post); // get all the data for the found post
		if (! $post_img) {
			$post_img = 'imgs/painsci-updates-badge--sq-3000x3000-300k.jpg';
		}
		if (! $post['post_audio']) {
			continue;
		} // this is the audio feed, so skip posts without audio
		$date_rss = date('D, d M Y H:i:s -0700', $timestamp); // get the date for the post in RSS-friendly format
		if ($n++ == $max) {
			break;
		}
		journal("adding an episode to the podcast feed [ $title_smpl ]", 3);
		$title = numericEntities($title); // RSS will choke on named entities like &ldquo; so a special function is needed to convert special chars to numeric entities specifically
		// if (inStr("full-fledged evidence", $content)) exit("<pre>" . htmlentities($html) . "</pre>");

		$description = $post['description']; // Default to the default description, which always exists in some form…
		if (isset($post['description_audio'])) { // If there is a longer description, use that.  At launch of the podcast, description_audio is used solely for show notes (but, like all descriptions, it might prove to be more useful in the future)
			$description = $post['description_audio'];
		}
		if (! inStr('<p>', $description)) {
			$description = "<p>$description</p>"; // add <p> tags if they aren't already there; the description_audio field has them if it contains multipara content
		}

		// Generate the post/episode
		$template_file_contents = file_get_contents('template-rss-member-podcast-post.xml', true);
		ob_start();
		eval("?>$template_file_contents"); // On the use of eval in the PainSci CMS: craftdocs://open?blockId=D8BB4DEF-66B7-4395-9020-1CACBE6BBFC4&spaceId=bc7d854c-3e5b-a34e-4850-a6d2f31a1a59
		$rss_post = ob_get_contents();
		ob_end_clean();

		$rss_post = eval('return "' . addslashes($rss_post) . '";'); // substitute values in the RSS post template with values filled in
		// if (inStr("Charles", $content)) exit(htmlspecialchars("<pre>$rss_post</pre>"));
		$rss_posts .= stripslashes($rss_post); // remove the slashes we just added
		$rss_posts = preg_replace("|\n{3,5}|", "\n\n", $rss_posts); // standardize vertical whitespace (to minimize spurious whitespaces diffs)
	}
	// We now have a string containing all posts, storedin $rss_posts
	// Generate the REGULAR RSS FILE
	$rss_str = eval('return "' . addslashes(file_get_contents('template-rss-member-podcast-channel.xml', true)) . '";');
	// On the use of eval in the PainSci CMS: craftdocs://open?blockId=D8BB4DEF-66B7-4395-9020-1CACBE6BBFC4&spaceId=bc7d854c-3e5b-a34e-4850-a6d2f31a1a59
	$fn = 'incs/rss-member-podcast-hg4v437k.xml';
	$rss_str = tidyRSS($rss_str); // some tidying to make the file easier to audit
	if ($GLOBALS['ps']) {
		$fn = _ROOT . '/' . $fn;
	} //psmod, non-generic save location for rss, to match legacy location // `path
	if (fileExistsNoChange($rss_str, $fn)) { //`path
		journal("Podcast feed unchanged, <em>not</em> writing file: $fn", 2, true);
	} else {
		journal("<strong>Podcast feed changed</strong>, writing file: $fn", 2, true);
		saveAs($rss_str, $fn);
	}
}

function tidyRSS($rss_str)
{
	$rss_str = preg_replace("|<!-- removed from RSS.+? -->\n|", null, $rss_str); // I put these in, and now I’m taking them out... so nuke this line if you want to see removal markers!
	$rss_str = tidyWhitespace($rss_str);
	$rss_str = str_replace('<item>', "\n\n\n\n\n\n<item>", $rss_str);	// add extra space before each post

	return $rss_str;
}

/* <##> make the sitemap */
function makeSitemap()
{
	global $posts;
	journal('making the sitemap', 1);
	$mostRecentPostDate = $posts[current(array_keys($posts))]['date']; // this references the first post in the posts array by its key, which is begotten with the incantation: current(array_keys($posts))
	global $settings;
	extract($settings);
	$sitemap_str = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\" xmlns:image=\"http://www.sitemaps.org/schemas/sitemap-image/1.1\" xmlns:video=\"http://www.sitemaps.org/schemas/sitemap-video/1.1\">\n<url>\n<loc>http://{$domain}</loc>\n<lastmod>{$mostRecentPostDate}</lastmod>\n<changefreq>weekly</changefreq>\n<priority>1.0</priority></url>\n";
	$sitemap_str = str_replace('http://www.painsci', 'https://www.painsci', $sitemap_str);
	// note, lastmod must be equal to the most recent post, and will be subbed in after
	journal('adding posts to sitemap', 2, true);
	foreach ($posts as $post) {
		if ($post['preview'] or 					// do not include post previews
			$post['indexing'] === false or		// a noindexing directive is equivalent to exclusion from the sitemap
			$post['canonical']) { // if there's a canonical URL, do not include this one in the sitemap
			continue;
		}
		journal('adding post '  . $post['title_smpl'], 3);
		$loc = "\n<!--#" . ++$n . "--><url><loc>http://{$domain}/{$post['title_smpl']}.html</loc>\n" .
		'<lastmod>' . $post['date'] . '</lastmod>';
		$sitemap_str .= str_replace('http://www.painscience.com', 'https://www.painscience.com/blog', $loc); // #psmod: add “blog” subdir
		// PRIORITY is .7 for macro and .5 for microposts unless otherwise stated
		$default_priority = 7; // default priority for macro posts
		global $ps;
		if ($ps) {
			$default_priority = $default_priority - 2; // on ps, posts are relatively less important than the rest of the site
		}
		if ($post['priority']) {
			$sitemap_str .= "<priority>0.{$post['priority']}</priority>";
		} elseif ($post['post_class'] == 'macro') {
			$sitemap_str .= '<priority>0.' . $default_priority . '</priority>';
		} else {
			$sitemap_str .= '<priority>0.' . ($default_priority - 2) . '</priority>';
		}
		// CHANGE FREQUENCY is "never" unless otherwise stated (really not supported here yet)
		if ($post['changefreq']) {
			$sitemap_str .= "<changefreq>{$post['changefreq']}<changefreq>";
		} else {
			$sitemap_str .= '<changefreq>never</changefreq>';
		}
		$sitemap_str .= "</url>\n";
	}
	$sitemap_str .= "\n</urlset>";

	$fn = STAGE . "/sitemap-{$sitecode}.xml"; // `path
	if ($GLOBALS['ps']) {
		$fn = ROOT_TRUE . "/stage/sitemap-{$sitecode}-blog.xml";
	} // # symod, adding -blog suffix, saved parallel to main sitemap-ps.xml, `path
	if (fileExistsNoChange($sitemap_str, $fn)) {
		journal("sitemap unchanged, <em>not</em> writing file: $fn", 2, true);

		return;
	}

	journal("<strong>sitemap changed</strong>, writing file: $fn", 2, true);
	saveAs($sitemap_str, $fn);
}

function remove_old_files($posts)
{ // called at the end of makeWebVersions()
	journal('cleanup obsolete files', 1, true);
	//	printArr($GLOBALS['filenames']);
	//	echo "!!!!!!!!!<br><br>!!!!!!!!!!!!<br><br>";
	//	printArr(glob(STAGE . "/*.html")); exit;
	foreach (glob(STAGE . '/*.html') as $fn) { // for every html file (posts and tag indexes)
		if (inStr('index.html', $fn)) {
			continue;
		} // ignore the index
		if (in_array($fn, $GLOBALS['filenames'])) {
			continue;
		} // ignore pages matching a known good file
		unlink($fn);
		$x++;
		journal("removing obsolete page [$fn]", 2, true);
	}
	foreach (glob(STAGE . '/imgs-auto/*') as $fn) {
		preg_match("@.*?/imgs-auto/(.*?)\.\w{3}@", $fn, $matches);
		$fn_mod = $matches[1];
		//		echo "<br>$fn<br>$fn_mod<br>";
		$found_match = false; // reset matching status
		foreach ($posts as $post) { // go through the posts
			if (array_search($fn_mod, $post) == 'title_smpl') {
				$found_match = true;
			}
		}
		if (! $found_match) {
			journal("removing obsolete img: $fn_mod", 2, true);
			unlink($fn);
			$x++;
		}
	}
	if ($x == 0) {
		journal('no obsolete files found', 2, true);
	} else {
		journal("$x obsolete files found", 2, true);
	}
}

/* PubSys miscellaneous functions.

(Many of these functions could potentially be useful for BibSys, and transferred to misc-functions.php.)

get_settings																settings			read blog settings from simple text file
get_marked_text ($content, $marker = "`")				string			title or description parsed from content
paragraphinate ($content)											content			micropost content split into paragraphs
massage_title ($title)												string			title trimmed of trivial trailing punctuation
extractTags ($tags)													string			comma-delimited list of tags
extract_subtitle ($post)												post				add basetitle & subtitle to post
markupTags ($tags)													tags				marked up tag list
get_post_size ($post)													post				add word count and size fields
get_description ($post)												post				add description field
html_to_description ($description, $max = 250)			string			description parsed from start of HTML content
file_management														n/a				moves/copies misc important files to /html

extract_citekey															string, a citekey extracted from a given PainScience.com URL

printBlog												different method needed

make_current_microposts_alias				different method needed
removeImgsFromParas ($content)			obsolete	trivial function, no longer required
getBlog													obsolete	original post-parser, quite long-winded
copy_imgs												obsolete, incomplete

These functions are used by both the PainScience.com blog AND the Writerly CMS. */

/** returns @string: title trimmed of trivial trailing punctuation, for titles harvested from microposts */
function massage_title($title)
{
	$title = preg_replace('@(.*?)[.,]$@', '$1', $title);

	return $title;
}

/** returns @array: of global blog settings, read from a simple text file format */
function get_settings()
{
	$fnarr = glob('guts/settings-*'); // find the settings file
//	include($fnarr[0]);
	$settings = getArrFromFile($fnarr[0], false, true); // $synonyms = false, $simple = true;
	date_default_timezone_set('America/Los_Angeles');
	$settings['year'] = date('Y');

	if ($settings['optional_subdir']) {
		$settings['optional_subdir'] = '/' . $settings['optional_subdir'];
	}

	// add lowercase versions of the $settings array
	foreach ($settings as $name=>$value) {
		$settings[$name . '_lc'] = strtolower($value);
	}

	// now generate some settings …
	// a sitecode if it wasn’t set
	if (! $settings['sitecode']) {
		$settings['sitecode'] = str_replace(' ', '-', $settings['sitename_lc']);
	}

	global $ps;
	if ($ps) {
		$settings['protocol'] = 'https';
	} else {
		$settings['protocol'] = 'http';
	}

	// `paths

	$settings['root_dev'] = ROOT_DEV; // this is kind of redundant, already set at the beginning of the make, but it's worth putting it in the settings;

	$settings['stage'] = STAGE; // this is kind of redundant, already set at the beginning of the make, but it's worth putting it in the settings;

	//url bases
	$settings['urlbase_prod'] = "{$settings['protocol']}://" . $settings['domain_lc'] . $settings['optional_subdir'];
	$settings['urlbase_stage'] = str_replace(ROOT_TRUE, 'http://' . $_SERVER['HTTP_HOST'], STAGE);

	ksort($settings);

	// define name constants for all settings
	foreach ($settings as $name=>$value) {
		if (!defined(strtoupper($name))) { // if not define already
			define(strtoupper($name), $value); // define!
		}
	}

	return $settings;
}

/** returns @array, post: adds live/local urls and filename to blog post */
function get_post_urls($post)
{
	//	#2do fix this syntax: $title = $post['slug'] ? $post['title'] : $post['slug']; // if there's slug, use the slug; otherwise, use the title
	if ($post['slug'] == '') {
		$title = $post['title'];
	} else {
		$title = $post['slug'];
	}
	$post['title_smpl'] = titleToFn($title); // simplified and hyphenated version of the title, ready for use as filename
	$post['title_smpl'] = str_replace('-(member-post)', '', $post['title_smpl']); // strip out the Member-Post suffix
	$post['fn'] = "{$post['title_smpl']}.html"; // append .html for the filename
	global $settings;
	extract($settings);
	$post['url_stage'] = "$urlbase_stage/{$post['fn']}";
	$post['url_live'] = "$urlbase_prod/{$post['fn']}";
	return $post;
}

/** returns @string: blog post title or description marked in text by explicit and implict markers (can return any marked string in principle)  */
function get_marked_text($content, $marker = '`')
{ // Tries to extract a chunk of text from content that is marked in a distinctive way: either bracketed by a distinctive marker, or a marker in a short string bracketed by some other common markup or punctuation. For instance, `paired ticks` can bracket any phrase, or a tick can be placed <em>inside ` emphasized text</em>.  There are several acceptable scenarios.
	// #2do someday I’m going to have to deal with a regex stumper here, see:
	// evernote:///view/523333/s4/e5b1c0c9-8ab6-49b1-a4f5-070868f67b65/e5b1c0c9-8ab6-49b1-a4f5-070868f67b65/

	$m = $marker;

	// look for `bracketed text`
	if (preg_match("@$m(.*?)$m@", $content, $matches)) {
		return ucfirst(str_replace($m, null, $matches[1]));
	}

	//	if (preg_match("@href=\"(.*?)\">\\w+$m.*?</a>@ui", $content, $matches))

	// look for <a href='url'>Nice title</a>
	// note, the tick can be at any location between the defining elements
	if (preg_match("@<a href.*?>([\w\s,!]*$m.*?)</a>@", $content, $matches)) {
		return ucfirst(str_replace($m, null, $matches[1]));
	}

	// look for <strong>`nice title</strong>
	if (preg_match("@<strong.*?>(.*?$m.*?)</strong>@", $content, $matches)) {
		return ucfirst(str_replace($m, null, $matches[1]));
	}

	// various other similar searches within common HTML and Markdown
	if (preg_match("@<em.*?>(.*?$m.*?)</em>@", $content, $matches)) {
		return ucfirst(str_replace($m, null, $matches[1]));
	}
	if (preg_match("@<cite.*?>(.*?$m.*?)</cite>@", $content, $matches)) {
		return ucfirst(str_replace($m, null, $matches[1]));
	}

	// look for the first “nice `title” bracketed by single punctuation marks
	// there's some matching cleverness I had to learn for this:
	// only want characters before the tick that are NOT the marker
	// otherwise it gets greedy if there are earlier marker
	// so instead of .*? before the tick, it has to be [^”]
	// iow, "anything but a closure"

	if (preg_match("@“([^”]*$m.*?)”@u", $content, $matches)) {
		return ucfirst(str_replace($m, null, $matches[1]));
	}
	if (preg_match("@—\s{0,1}([^”]*$m.*?)\s{0,1}—@u", $content, $matches)) {
		return ucfirst(str_replace($m, null, $matches[1]));
	}
	if (preg_match("@‘([^’]*$m.*?)’@u", $content, $matches)) {
		return ucfirst(str_replace($m, null, $matches[1]));
	}
	if (preg_match("@\*([^*]*$m.*?)\*@u", $content, $matches)) {
		return ucfirst(str_replace($m, null, $matches[1]));
	}
	if (preg_match("@\[([^]]*$m.*?)\]@u", $content, $matches)) {
		return ucfirst(str_replace($m, null, $matches[1]));
	}
	if (preg_match("@_([^_]*$m.*?)_@u", $content, $matches)) {
		return ucfirst(str_replace($m, null, $matches[1]));
	}

	// Maybe` this is the text: in a phrase followed by a colon.
	if (preg_match("@([A-Z][^:.]+$m.*?):@u", $content, $matches)) {
		return ucfirst(str_replace($m, null, $matches[1]));
	}

	// short sentences ending in exclamation points, or questions marks
	// Like `this! Or `this?
	if (preg_match("@([A-Z][^:]+$m.*?)[\?!]@u", $content, $matches)) {
		return ucfirst(str_replace($m, null, $matches[1]));
	}

	// STILL nothing?! Well, there’s one more common scenario, especially for descriptions (∂): from the start of the content to a marker at an arbitrary “stop here” marker.
	if (preg_match("@(.*?$m)@u", $content, $matches)) {
		return ucfirst(str_replace($m, null, $matches[1]));
	}

	return false; // never found anything? report failure
}

/** returns @string, content: micropost content split into paragraphs on standard delimers */
function paragraphinate($content)
{ // for content delimited by triple-ellipses ……… and triple-gts >>>, form paragraphs
	$paras = explode('………', $content);
	foreach ($paras as $para) {
		if (strpos($para, '>>>') === 0) {
			$newcontent .= "<blockquote><p>$para</p></blockquote>\n\n";
		} else {
			$newcontent .= "<p>$para</p>";
		}
	}

	return $newcontent;
}

/** returns @array, post: add new fields bastetitle & subtitle, parsed from a title if possible */
function extract_subtitle($post)
{
	// This system largely ignores subtitles, and mostly uses the full title wherever a title is needed, and the field name "title" CONTINUES TO REFER TO THE WHOLE TITLE even after this function extracts any parts.  However, basetitles and subtitles are handy here and there.
	if (! preg_match('/(.+?)([?:!…—])(.+)/u', $post['title'], $matches)) {
		return $post;
	} // check for divider characters that indicate the beginning of the subtitle. Originally this function tried to find the position of the divider and then str_split at that position, but that causes hairy unicode trouble.  This one-step preg_matching method is MUCH better.
	if (trim($matches[2]) == ':') {
		$post['basetitle'] = trim($matches[1]);
	} else {
		$post['basetitle'] = trim($matches[1]) . $matches[2];
	}
	$post['subtitle'] = trim($matches[3]);
	if (! $post['slug']) {
		$post['slug'] = $post['basetitle'];
	} // “slug” is a short-title and may differ from the basetitle, but the basetitle provides a default slug wherever a basetitle exists
//	echo "<br>working on {$post['title']}, divided into —{$post['basetitle']}— and —{$post['subtitle']}—<br>";
	return $post; // return the $post, whether it has been modified or not
//	if ($title_div_pos > strlen($title)/3+2) journal("caution: long subtitle",2,true); // just a little math: the divider should probably be at less than one third of the total title length (plus a tiny bit of leeway)?
}

/** returns @array, post: add new fields words_exact, words_round, containing word count */
function get_post_size($post)
{
	if ($post['post_class'] == 'micro-img') {
		if ($GLOBALS['ps']) {
			$post['size2'] = 'xxs';
		} else {
			$post['size'] = 's';
		}

		return $post;
	}

	// remove some stuff we don’t want to count; conveniently, this is easily achieved just with existing teaser and RSS exclusions naturally take care of most of this
	$stripped_content = preg_replace('|<!-- paywall markup: non-member start -->(.+?)<!-- paywall markup: non-member end -->|s', null, $post['html']);
	$stripped_content = preg_replace('|<!--\s*rss_no_block_start(.+?)rss_no_block_stop\s*-->|s', null, $stripped_content); // remove multiline content from RSS
	$stripped_content = preg_replace("/.*rss_no_line.*\n/", null, $stripped_content); // remove one line from RSS
	$stripped_content = strip_tags($stripped_content); // get content without markup, including premium content if it exists

	// if (inStr('Softer', $post['title'])) exit("<pre>" . htmlentities($stripped_content) . "</pre>");
	// if (inStr("full-fledged evidence", $stripped_content)) exit("<pre>$stripped_content</pre>");

	$post['words_exact'] = $words = count(explode(' ', $stripped_content)); // count the words
	$post['words_round'] = roundDown($post['words_exact']); // get a more readable rounded count
	$post['read_time'] = ceil((ceil($words/100)*100)/300) . 'm';
	if ($words < 450) $post['read_time'] = "90s";
	if ($words < 300) $post['read_time'] = "1m";
	if ($words < 150) $post['read_time'] = "30s";
	if ($words < 90) $post['read_time'] = "20s";




	// these assignments generates only a size CODE, not a tag; a tag is generated from the code later)
	if (! $GLOBALS['ps']) { // small size scale (for generic pubsys blogging)
		if ($words >= 1000) {
			$size = 'xl';
		}
		if ($words < 1000) {
			$size = 'l';
		}
		if ($words < 400) {
			$size = 'm';
		}
		if ($words < 50 or $post['post_class'] == 'micro-img') {
			$size = 's';
		} // img posts default to xxs
	}

	if ($GLOBALS['ps']) {
		// a larger scale for the main site (pubsys posts are mixed with much larger content)
		// this scale matches the scale used in harvesting sizes of articles (see BibliographyUpdater.pl)
		if ($words < 6000) {
			$size = 'xxl';
		}
		if ($words < 4000) {
			$size = 'xl';
		}
		if ($words < 2000) {
			$size = 'l';
		}
		if ($words < 1000) {
			$size = 'm';
		}
		if ($words < 600) {
			$size = 's';
		}
		if ($words < 400) {
			$size = 'xs';
		}
		if ($words < 200 or $post['post_class'] == 'micro-img') {
			$size = 'xxs';
		} // img posts default to xxs
		if ($words < 100 or $post['post_class'] == 'micro-img') {
			$size = 'xxxs';
		} // img posts default to xxs
	}
	$post['size'] = $size;

	return $post;
}

/** returns @array, post: add new field description */
function get_description($post)
{
	// how long? http://www.joshspeters.com/how-to-optimize-the-ogdescription-tag-for-search-and-social
	// #2do: it would be clever detect natural stops close to the end of the arbitrary truncation, and trim to that
	// where are we getting the description?
	if ($post['description']) {
		return $post;
	} // if it’s already been explicitly defined in metadata, there’s nothing to do, buy-bye
	// otherwise …
	extract($post);
	$description = $html; // by default, the description is extracted from the entire html content
	// or it could be marked up with the ∂ symbol
	//	if ($post_audio) echo "<pre>" . htmlentities($html) . "</pre>";
	if (strpos($html, '∂') !== false) {
		$description = get_marked_text($html, '∂');
		$post['html'] = str_replace('∂', null, $html); // remove ∂s from the html
		$post['content'] = str_replace('∂', null, $content); // remove ∂s from the content
	}
	$description = html_to_description($description); // convert to a string suitable for a metadata description

	$post['description'] = preg_replace('|\s+|', ' ', $description); // eliminate runs of spaces

	return $post;
}

/** returns @string: post description, from converted HTML content to a string suitable for metadata description */
function html_to_description($description, $max = 115)
{
	// assumes HTML input (that is, content after all php and markdown has been rendered)
	// this function starts with the entire post and whittles it down to the first description-sized chunk of content
	$description = emUpperizer($description, 20); // convert <20 char emphasized text to uppercase
	$description = preg_replace('|<!-- paywall markup: non-member start -->(.{10,1000}?)<!-- paywall markup: non-member end -->|s', null, $description); // remove paywall-related content (in posts with audio, this appears at the top and hijacks the description
	$description = preg_replace('|<!-- paywall markup: member start -->(.{10,1000}?)<!-- paywall markup: member end -->|s', null, $description); // remove paywall-related content (in posts with audio, this appears at the top and hijacks the description
	$description = strip_tags($description); // get rid of all other HTML
	$description = preg_replace("@[\r\n]+@", ' ', $description);
	$description = trim($description);
	// the idea of this fairly tricky little bit of logic and math is to deal with this problem: it makes no sense to truncate a 255 char post to 250.  If the content is within, say, 25% of the maximum, then the truncation should be greater -- to create a larger difference between the description snippet and the full content.
	if (strlen($description) > $max and strlen($description) < $max * 1.25) {
		$max = intval($max - $max * .25);
	}
	// echo "<p>original string length: " . strlen($description) . " • calculated length to truncate to: $max </p>";
	if (strlen($description) > $max) {
		$description = truncateToWord($description, $max);
	}

	return $description;
}

/**  returns @nothing: puts a few blog resources in place as needed, some critical, others for convenience */
function file_management()
{

	// alias to most current microposts file, a convenience
	make_current_microposts_alias($micropost_files);
	journal('checking a few more critical files', 1);

	// the contents of the "HTML" folder should be completely automatically managed/autogenerated; it should be possibel to wipe it clean, and then rebuilt in every detail; this requires checks for certain files
	// @2do add checks to see if htaccess, favicon and other key files even exist in the first place (can't copy a favicon file that doesn't exist yet)

	
	global $ps; if ($ps) {

		// htaccess `path (irrelevant to the other blogs since migrating hosting to CFPages)
		if (fileExistsNoChange(file_get_contents('guts/htaccess'), 'html/.htaccess')) {
			journal("The 'htaccess' file is in place and unchanged", 2, true);
		} else {
			copy('guts/htaccess', STAGE . '/.htaccess');
			journal('restoring and/or updating htaccess file', 2, true);
		}
	}

	global $ps; if (! $ps) {

		// 404 handler `path
		if (fileExistsNoChange(file_get_contents('guts/404.html'), 'html/404.html')) {
			journal("The 'not-found' file is in place and unchanged", 2, true);
		} else {
			copy('guts/404.html', STAGE . '/404.html');
			journal('restoring and/or updating not-found file', 2, true);
		}

		// _redirects files for cfpages
		if (fileExistsNoChange(file_get_contents('guts/_redirects'), 'html/_redirects')) {
			journal("The 'not-found' file is in place and unchanged", 2, true);
		} else {
			copy('guts/_redirects', STAGE . '/_redirects');
			journal('restoring and/or updating Pages _redirects file', 2, true);
		}
	}

	// favicon `path
	if (file_exists('guts/favicon.ico')) {
		if (! file_exists('html/favicon.ico')) {
			copy('guts/favicon.ico', STAGE . '/favicon.ico');
			journal('restoring favicon file to /stage', 2, true);
		} else {
			journal('favicon file is in place', 2, true);
		}
	} else {
		journal('! no favicon file in /guts', 2, true);
	}
}

/** returns @file, alias: create an easily accessible alias to the most recent microposts file */
function make_current_microposts_alias()
{
	return;
	global $micropost_files;
	sort($micropost_files);
	$most_recent_file = array_pop($micropost_files);
	global $posts;
	global $settings;
	extract($settings);
	$hard_link = STAGE . '/posts/CURRENT MICRO POSTS, ' . strtoupper($sitename) . '.html';
	if (file_exists($hard_link)) {
		unlink($hard_link);
	}
	link(STAGE . '/' . $most_recent_file, $hard_link);
}

/** returns @string, html: a simple table or list of all or some posts (see also make_post_matrix) */
function make_post_index($posts = null, $type = 'table')
{ // default to table, otherwise list
	if (! $posts) {
		global $posts;
	} // if a subset of posts isn’t specified, use them all
	//	foreach ($posts as $x) echo $x['title'] . '… '; exit;
	foreach ($posts as $post) {
		$index_str .= make_post_index_item($post, $type);
	}
	if ($type == 'list') {
		return "<ol reversed class='post_index'>\n{$index_str}</ul>\n";
	}
	if ($type == 'table') { // table is a "matrix", but a very simple one
		return <<<TABLE
			<table id='matrix' class='post_index'>
			<thead><tr>
				<th class='thsize' data-sort="int"></th>
				<th data-sort="int"><span class="char_sort_arrow">▼</span></th>
				<th class='thtitle' data-sort="string" ></th>
			</tr></thead><tbody>
			{$index_str}</tbody></table>
			TABLE;
	}
}

function list_urls($posts = null, $type = 'table')
{ // default to table, otherwise list
	if (! $posts) {
		global $posts;
	} // if a subset of posts isn’t specified, use them all
	//	foreach ($posts as $x) echo $x['title'] . '… '; exit;
	foreach ($posts as $post) {
		$postPageUrl = "{$optional_subdir}/{$post['title_smpl']}.html";
		$postUrlsAndLinks .= <<<TEMPLATE
			PainScience.com$postPageUrl
			https://www.painscience.com$postPageUrl
			[{$post['title']}](https://www.painscience.com$postPageUrl)
			<a href="https://www.painscience.com$postPageUrl">{$post['title']}</a>
			\n
			TEMPLATE;
	}
	checkSave($postUrlsAndLinks, 'guts/posts-links.md', 'URLs and links only for copy/paste via LaunchBar'); // used in index.php
}

/** returns @array, html: an index of some or all posts marked up as list items or table rows */
function make_post_index_item($post, $type = 'table')
{
	if ($post['preview']) {
		return null;
	} // do not process post previews
	extract($post);
	$index_class = $size; // assign a class indicating the size of the post
	$postSizesIndex = ['xxs' => '1', 'xs' => '2', 's' => '3', 'm' => '4', 'l' => '5', 'xl' => '6']; // a numeric index representing post sizes, used for filtering
	$date = dateClear($timestamp);
	// now over-ride that class for certain kinds of posts which need a different icon, like image posts
	$audioBadge = $post_audio ? "<span style='opacity:0.5'><small>&#128264;</small></span>" : null;
	if ($premium) {
		$title = str_replace('(Member Post)', '(<strong>Member Post</strong>)', $title); // embolden the "Member Post" suffix
	}
	if ($post_class == 'micro-img' or strpos($tags, 'photography') !== false) {
		$index_class = 'img';
	}
	if ($type == 'list') {
		// 		return "<li class='mbi $index_class'><a href='$title_smpl.html'>$title</a> $audioBadge » <small>$date</small></li>\n";
		return "<li class='mbi $index_class' x-show='itemSizeShow[$postSizesIndex[$size]]' x-transition.duration.800ms><a href='$title_smpl.html'>$title</a> $audioBadge » <small>$date</small></li>\n";
	}
	if ($type == 'table') {
		if (! $words_exact) {
			$words_exact = 10;
		}
	}

	return <<<ROW
		<tr class='$tags'>
			<td class='tdsize $index_class' data-sort-value="$words_exact" title="word count: $words_exact"></td>
			<td class='tddate' data-sort-value="$timestamp" nowrap>$date</td>
			<td class='tdtitle' data-sort-value='$title_smpl'><a href='$title_smpl.html'>$title</a></td>
		</tr>\n
		ROW;
}

/** returns @array, post: add timestamp, converted from xx-xx-xx type post date */
function get_timestamp_from_post_date($post)
{
	$date = $post['date'];
	$post['day-order'] = $ord = 0;
	if (strlen($date) == 11) {
		$post['date'] = substr($date, 0, -1);
		$post['day-order'] = $ord = ord(substr($date, -1)) - 96; // to convert a to 1 and b to 2 etc, get the ASCII value of the letter with ord and subtract 96 (letters start at position 96 in the ASCII index)
	}
	$timestamp_candidate = parseDate($date) + $ord;
	global $timestamps;
	if (! is_array($timestamps)) {
		$timestamps = [];
	} // first time here? initialize the array
	//	echo "<br>$date [$ord]: $timestamp_candidate …";
	while (in_array($timestamp_candidate, $timestamps)) {
		$timestamp_candidate = $timestamp_candidate + 1;
	}
	//	echo "<br>$date [$ord]: $timestamp_candidate …<br>";
	$post['timestamp'] = $timestamp_candidate;
	array_unshift($timestamps, $timestamp_candidate);

	return $post;
}

/** returns @files: writes blog content to multiple files customized for use on PainScience.com */
function make_sy_indexes()
{
	global $ps;
	if (! $ps) {
		return;
	} // #psmod: this ƒ is for PS blog production only
	journal('making custom indexes for PS', 1, true);
	// A lot of output is idiosyncratic for a given format; for instance, a post's date of 2013-10-26 may be output as "Saturday, October 26, 2013" or "Oct 26".  In general, such trivial variations should be calculated from the post data when it’s time to output, and not at the time that the post is created.  But there is a large grey zone.  If idiosyncratic output is likely to be used in any of most output, should it then be calculated when the post is produced?  In general, this function is devoted to whatever formatted output was NOT calculated when the post was originally generated by a get_ function.
	global $settings;
	extract($settings);
	global $posts;
	foreach ($posts as $post) {
		if ($post['preview']) {
			continue;
		} // do not include preview posts in indexes
		// make post page links:
		$postPageUrl = "{$optional_subdir}/{$post['title_smpl']}.html";

		// fine-tuning of variables for output
		$date_str = date('M j', parseDate($post['date'])); // format a standard simple date string
		$date_str = str_replace(' ', '&nbsp;', $date_str);
		$date_str_link = "<a href='https://www.painscience.com$postPageUrl' title='click for post page'>$date_str</a>";

		// lots to do with featured urls
		if ($post['url']) {
			// first, link title to a featured URL
			$featured_link = "<a href='{$post['url']}' title='$symbol_help'><span style=\"color:#DDD\" title='click for featured item'>∞</span></a>";
			$title_str = "<h3><a href='{$post['url']}'>{$post['title']}</a> $featured_link </h3>\n";
			$post['url'] = preg_replace("@ca/blog/0(.*?)\.php@", 'ca/$1', $post['url']); // kill this off someday when all the blog posts have expired
	if (! isset($post['hidelink'])) { // stop now if the hidelink flag is set
		$link = $post['htmlFeaturedCk'] ? $post['htmlFeaturedCk'] : "<p class='widebar featured_link'><a href='{$post['url']}'>" . prettifyURL($post['url']) . '</a></p>';
	}
		} else {
			$title_str = "<h3>{$post['title']}</h3>\n";
		}

		$postIdDivider = "\n\n<!-- ==================================================== -->\n<!-- === <# {$post['date']}</a>: {$post['title']} [{$post['words_exact']}] #>]\n\n{$post['description']} -->";

		/* $post_template_inline = <<<TEMPLATE
		$postIdDivider
		<div class='css_micropost' id='{$post['smpl']}'>
		<div class='css_micropost_md font_accent'>$date_str_link</div>
		$title_str {$post['html']} $link
		</div>\n
		TEMPLATE;
		$postsAllInlineStr .= $post_template_inline;
		if (++$postsRecentInlinCount <= 25) $postsRecentInlineStr .= $post_template_inline; */

		if (++$postsRecentPopupListCount <= 5):
		$postTemplatePopupList = <<<TEMPLATE
			$postIdDivider
			\n\n<li class="l2">$date_str_link — <a href="https://www.painscience.com$postPageUrl">{$post['title']}</a></li>\n\n
			TEMPLATE;
		$postsRecentPopupListStr .= $postTemplatePopupList;
		endif;

		if (++$postsRecentIndexLinksCount <= 20):
			if (strlen($post['description']) > 80) {
				$description = truncateToWord($post['description'], 80);
			} else {
				$description = $post['description'];
			}

		/* $postRecentTableLinks = <<<TEMPLATE
		<tr><td><a href="$postPageUrl">{$post['title']}</a> • $description</td>
		<td class="item_metadata font_narrow"><span class="item_update"><span class="colour_gray_blue">$date_str</span></span>
		<span class="item_word_count">{$post['words_round']}</span></td></tr>
		TEMPLATE;
		$postsRecentIndexLinksStr .= $postRecentTableLinks; */

		$postsRecentIndexLinksTemplate =	 <<<TEMPLATE
				<li><span class="item_update"><span class="colour_gray_blue">$date_str</span></span>: <a href="https://www.painscience.com$postPageUrl">{$post['title']}</a></li>\n
				TEMPLATE;

		if ($postsRecentIndexLinksCount < 6) {
			$postRecentLinkList6 .= $postsRecentIndexLinksTemplate;
			$postRecentLinkList20 .= $postsRecentIndexLinksTemplate;
		}

		if ($postsRecentIndexLinksCount >=6 and $postsRecentIndexLinksCount <= 20) {
			$postRecentLinkList20 .= $postsRecentIndexLinksTemplate;
		}


		endif;

		// build a matrix of blog posts for articles.php, with tags translated to class names that will be applied to each row for search, sort, filter (SSF)

		// get all item tags
$item_tags = explode(',', $post['tags']); // explode the record tags to an array of true tags

foreach ($item_tags as $tag) { // make an array of tags KEYS
	$tag_keys[] = simplify($tag);
}

		// add more tags inferred from metadata
		// (note, these probably duplicate some inferred tags by the tag engine); may be substantial obsolete and result in non-identical redundant tags
		$dateClear = dateClear($post['timestamp']);
		$dateClearer = dateClearer($post['timestamp']);
		// another reckoning of size to blend with main PS article index
		if (daysSinceTstamp($post['timestamp']) < 180) {
			$freshness = 'new';
		}
		$nowords = $post['words_round'];
		$size = 'micro'; // default to micro and then override at ascending break points...
		$sizeSymbol = '•';
		if ($nowords > 300) {
			$size = 'short';
			$sizeSymbol = '••';
		}
		if ($nowords > 1000) {
			$size = 'medium';
			$sizeSymbol = '•••';
		}
		//if ($nowords < 150) $summary =

		$tag_keys[] = $freshness;
		$tag_keys[] = 'blog';
		// sort($tag_keys); 										// sort the tags #newmatrix
$tag_keys = implode(' ', $tag_keys); // we’re done with the array: convert to a string for insertion into class attributes

if (inStr('ytemb', $summary)) {
	unset($summary);
} // hack to eliminate ytembs in this context

		$sizeSymbol = "<span class='char_bullet_compact'>$sizeSymbol</span>";

		$posts_matrix = <<<TEMPLATE
			{$post['timestamp']}:::<tr class="blog" id="{$post['title_smpl']}" data-tags="$tag_keys">
			<td class="item_title" style="min-width:50%;" data-sort-value="{$post['title_smpl']}"><a href="https://www.painscience.com$postPageUrl">{$post['title']}</a>$summary &nbsp; <span class='subtitles'>{$post['subtitle']}</span></td>
			<td class="item_update date small" nowrap data-sort-value="{$post['timestamp']}"><span class='freshness'>$freshness</span> &nbsp; <span class='date swnj'>$dateClear</span><span class='swoj date'>$dateClearer</span></td>
			<td class="item_word_count words small" data-sort-value="$nowords">$nowords</td>
			<td class="item_highlight highlights"></td></tr>
			TEMPLATE;
		// REMOVED <td class="item_size size" data-sort-value="$nowords">$sizeSymbol</td>

		$score = $score + 100 - (daysSinceTstamp($post['timestamp']) / 2); // 200 points for a brand new post
		if ($score < -300) {
			$score = -300;
		}
		if (daysSinceTstamp($post['timestamp']) > 100) { // for posts over 100 days old, their size becomes a factor
			/*	if ($nowords > 800) $score = $score + 300;
				if ($nowords > 500) $score = $score + 200;
				if ($nowords > 300) $score = $score + 100;
				if ($nowords > 160) $score = $score + 50;
				if ($nowords < 80) $score = $score - 50; */
			$wordsadj = $nowords / 2;
			if ($wordsadj > 300) {
				$wordsadj = 300;
			}
			$score = $score + $wordsadj;
		}
		if (inStr('unused', $post['tags_admin'])) {
			$score = $score + 100;
		} // rank unused posts much higher
		if (inStr('hot', $post['tags'])) {
			$score = $score + 100;
		}
		if (inStr('meta', $post['tags'])) {
			$score = $score + 50;
		}
		if (inStr('fun', $post['tags'])) {
			$score = $score + 20;
		}
		if (inStr('LOL', $post['tags'])) {
			$score = $score + 40;
		}
		if (inStr('announce', $post['tags'])) {
			$score = $score + 10;
		}
		// echo "$score (was $lastscore)  — " . $post['title'] . "<br>";

		// $score = round($score, 0, PHP_ROUND_HALF_DOWN);

		$postsMatrixTmp[$post['timestamp']] = [$score, str_replace("\n", ' ', $posts_matrix) . "\n"];

		$wordsTotal = $wordsTotal + $nowords; // this should be available for Writerly too

		// $posts_all_urls .= "redirect 301 $postPageUrl https://www.painscience.com$postPageUrl\n";

		// initializations
		unset($score);
		unset($link);
		unset($postPageUrl);
		unset($post_link);
		unset($tags_str);
		unset($SSFclasses);
		unset($SSFclasses);
		unset($warning);
		unset($summary);
		unset($size);
		unset($sizeSymbol);
		unset($freshness);
		unset($tag_keys);
	}

	arsort($postsMatrixTmp); // sort by score (first value in each record)
$postsMatrixTmp = array_slice($postsMatrixTmp, 0, 200, 'PRESERVE_KEYS'); // take just the 200 best records, but don't wreck keys, because …
krsort($postsMatrixTmp); // re-sort by the post timestamp, which is stored in the keys
foreach ($postsMatrixTmp as $post) {
	$postsMatrixStr .= $post[1];
} // build the final string

	$postsAllLinkList = make_post_index($posts, 'list');
	$postsAllLinkList = str_replace("href='", "href='https://www.painscience.com/blog/", $postsAllLinkList);

	// checkSave($posts_all_urls, "guts/posts-all-urls.html", "all URLs in plain text");
checkSave($postsAllLinkList, 'guts/posts-all-link-list.html', 'all links in a list file');  // used in blog.php
checkSave($postsMatrixStr, 'guts/posts-all-table-matrix.html', 'all links in a table file'); // used in articles.php
checkSave($postsRecentPopupListStr, 'guts/posts-recent-popup-list.html', 'full recent posts batch file'); // used in more.php
checkSave($postRecentLinkList6, 'guts/posts-recent-list-links-6.html', 'recent posts links in a list'); // used in index.php
checkSave($postRecentLinkList20, 'guts/posts-recent-list-links-20.html', 'recent posts links in a list'); // used in index.php
checkSave($wordsTotal, 'guts/wordcount.txt', 'text file containing blog word count'); // this feature should be available for Writerly too, and definitely won’t be the way this is written!

// currently unused
// checkSave($postsRecentInlineStr, "guts/posts-recent-inline.html", "full recent short posts batch file");
// checkSave($postsRecentIndexLinksStr, "guts/posts-recent-table-links.html", "recent posts links in a table file");
// checkSave($postsAllInlineStr, "guts/posts-all-inline.html", "all short posts batch file");
}

/** returns @array, post: add fields related to detected citekeys */
function deal_with_citekeys($md, $post)
{ //psmod

	// this ƒ exits without doin’ nuthin’ if it’s not PS, not an PS url, or an PS old blog post url
	if ($GLOBALS['ps'] == null) {
		return $post;
	} // exit ƒ if this isn’t PS
//	if ($post["date"] == "2013-10-30") echo "!";
	if ($md < 60 and ! inStr(' ', $md)) { // if it's a short string with no spaces, look for a valid citekey …
		global $sources;
		if ($sources->safeGet($md)->isValid()) {
			$post['citekey'] = $ck = $md;
		}
	}
	$url_lc = strtolower(($post['url']??''));

	if (! ($ck??null) and ! $url_lc) {
		return $post;
	} // give up, we got nuthin
	// but if there is a url
	if (! ($ck??null) and $url_lc) { // let's try the URL now
		if (! inStr('painscience', $url_lc)
			or inStr('/blog', $url_lc)) {
			return $post;
		} // wrong kind of url :-(
		if (preg_match('@php#(.*)@', $post['url'], $anchor)) {
			$post['url_anchor'] = $anchor[1];
		}
		// at this point the rule pretty much has to be a URL we get a ck from, so we do
		$post['citekey'] = $ck = extract_citekey($post['url']);
	}

	// we now have a ck, either from metadata or from a url
	// if there isn't a url yet, time to set it (now that we're done with url checking logic)
	if (! $post['url']) {
		$post['url'] = citation($ck, '[smarturl]');
	}

	$post['citekey_type'] = $ck_type = citation($post['citekey'], '[type]');
	$formats = ['mine' => 'featured', 'article' => 'fn3', 'wepage' => '']; // This is a nice bit of work, but a bit cryptic too.  The default xref templates for some record types don’t work very well for the blog, but new templates just for the blog aren’t really necessary either. In this array I specify the name of the template that should be used for specific record types — or, if nothing is specified, then there is no value and the default template is used.  ;-)
	 $htmlFeaturedCk = citation($ck, $formats[$ck_type]); // get rich HTML for a featured PS link
	 if ($post['url_anchor']) {
		 $htmlFeaturedCk = str_replace('.php', ".php#{$post['url_anchor']}", $htmlFeaturedCk);
	 } // add the anchor, if any
	$post['htmlFeaturedCk'] = $htmlFeaturedCk;

	return $post;
}

/** returns array (fields): blog post field names */
function get_post_field_names()
{
	global $posts;
	$fields = []; // an array for fields
	foreach ($posts as $post) {
		$fields = array_merge($fields, array_keys($post));
	}
	$fields = array_unique($fields); // strip (many) duplicates from fields array
	asort($fields); // sort fields array
	$fields = array_values($fields); // reset key order

	return $fields;
}

/** returns @string(table) or file: a matrix of blog post data, echoed in an html table or saved in a TSV */
function make_post_matrix($echo = true, $save = true)
{
	journal('making post data matrix', 1, true);
	global $settings;
	extract($settings);
	global $posts, $prepMode;

	$fields = get_post_field_names(); // #2do: this should be a globally available array

	$posts_all_table = '';
	foreach ($posts as $post) {
		if ($prepMode and $n++ > 10) {
			break;
		}
		$tsvItems = '';
		if ($save) { // harvest nearly all post data for TSV output
			foreach ($fields as $key=>$field) {
				if ($field == 'html' or $field == 'content') { // skip content and html fields (the only exclusions)
					unset($fields[$key]); // they must be removed from the $fields array as well as the data, or the columns are thrown outta whack
					continue;
				}
				$tsvItems .= ($post[$field]??null) . "\t";
			}
			$tsvItems = rtrim($tsvItems, '\t') . "\n"; // remove final comma, add lf
			$postTemplateTsvRows .= $tsvItems; // add row to list of rows
			unset($tsvItems);
		}

		// fine-tuning of variables for tabular output
		$date_str = date('M j, y', parseDate($post['date'])); // format a standard simple date string
		$prioritySort = 0 + $post['priority'];
		if (strlen($post['title']) > 50) { // clip long titles...
			$title_clipped = substr($post['title'], 0, 50) . " <span class='truncation_symbol'></span>";
		} else {
			$title_clipped = $post['title'];
		}
		$linked_title = "<a href='{$post['url_stage']}' title='{$post['title']}'>$title_clipped</a>";
		$tags = str_replace(',', ', ', $post['tags']);
		$src_file = $post['source_file'];
		if (strlen($src_file) > 40) { // clip long source_file names
			$src_file = substr($post['source_file'], 0, 40);
		}
		// #2do, maybe someday I can figure out how to build a link for opening files again
		// $src_file_url = "file://localhost{$stage}/posts/" . rawurlencode($src_file);
		// $src_file = "zzz <a href=\"{$src_file_url}\">$src_file_short</a>"; // alas, safari seems to strip localhost out of these URLs, probably for security reasons
		if ($post['url']) {
			$featured_url = "<a href='{$post['url']}'>∞</a>";
		}

		if ($post['post_img']) { // if there is a post img …
			$post_img_path = "/html/{$post['post_img']}"; // create a within-root path for legacy pubsys, which includes the html dir
			global $ps; if ($ps) $post_img_path = str_replace('/html', '', $post_img_path); // remove the html for PainSci
			if (file_exists(_ROOT . $post_img_path)) { // if it exists, build a linked image with a relative URL
					$postImgTiny = '';
					$postImgTiny = "<a title='{$post['post_img']}' href='$post_img_path'><img src='$post_img_path' height='25px'></a>";
			}
			else { // if it doesn't exist, alert me to the missing-ness
				echo "<p class='warning'>MISSING POST IMAGE: {$post_img_path}</p>"; // this should probably be done while building posts in the first place, not printing an index of them, but whatever, it’s minor and I’m right here
			}
		}

		$draft = ($post['preview']) ? 'DRAFT' : '';
		$index = ($post['indexing']) ? 'idx' : 'noidx';
		$member = ($post['premium']) ? '<strong>$</strong>' : '';



		$postTemplateTableRow = <<<ROW
			<tr>
				<td data-sort-value='{$post['timestamp']}' class='small' nowrap>{$date_str}</td>
				<td nowrap><a href="{$post['url_live']}">LIVE</a> / <a href="{$post['url_stage']}">PREVIEW</a></td>
				<td class='small ' class='light'>{$post['size']}</td>
				<td class='small'>{$post['words_exact']}</td>
				<td class='small'>{$post['read_time']}</td>
				<td class='small'>$draft</td>
				<td class='small'>$member</td>
				<td>$postImgTiny</td>
				<td data-sort-value='{$post['fn']}' nowrap>{$linked_title}</td>
				<td class='small light'>{$post['fn']}</td>
				<td class='small light'>{$post['psid']}</td>
				<td>{$post['slug']}</td>
				<td class='small light'>$tags</td>
				<td class='small light' nowrap style='overflow:hidden'>$src_file</td>
			</tr>
			ROW;

		$postTemplateTableRow = str_replace('micro-img', 'img', $postTemplateTableRow);
		$posts_all_table .= $postTemplateTableRow;

		unset($featured_url);
		unset($postImgTiny);
	}

	if ($echo) {
		echo '</div>'; // minor but hacky little thing, closing off the div containing the journal output before printing table
		echo  <<<TABLE
			<table id='matrix'>
			<thead><tr>
			<th data-sort="int">date<span class="char_sort_arrow">▼</span><!-- default sort --></th>
			<th>links</th>
			<th title='post size code' >•</th>
			<th data-sort="int">words [time]</th>
			<th data-sort="int">⏲️</th>
			<th>draft</th>
			<th>$</th>
			<th>img</th>
			<th data-sort="string">linked title</th>
			<th data-sort="string">url</th>
			<th data-sort="int">psid</th>
			<th data-sort="string">slug</th>
			<th>tags</th>
			<th data-sort="string">post file</th>
			</tr></thead>
			$posts_all_table
			</table>
			TABLE;
	}

	return; // for years this function proceeded to save the table data, but I never actually used it, ever, so... meh

	if ($save) {
		$tsv = implode("\t", $fields) . "\n" . $postTemplateTsvRows;
		$fn = 'guts/posts-matrix.tsv';
		if (fileExistsNoChange($tsv, $fn)) {
			journal("post matrix TSV file unchanged, <em>not</em> writing file: $fn", 2, true);
		} else {
			journal("<strong>post matrix TSV file changed</strong>, writing file: $fn", 2, true);
			saveAs($tsv, $fn);
		}
	}
} // end of make_post_matrix ƒ

/** returns @string (citekey): gets a citekey extracted from a given PainScience.com URL */
function extract_citekey($my_url)
{ /* Extracts a citekey for a sources.bib record from one of three common URL formats for my own content:
1) /bibliography.php?epsom
2) /articles/epsom-salts.php
3) /epsom

Use-case: when I’m blogging, I’m often copying and pasting content back and forth between different contexts, and I don’t want to have to worry about the format of the URLs.  If they happen to be raw URLs for PainScience.com, PubSys can cope.
*/

	global $sources;
	$original_url = $my_url;

	// 1. check for bibliography URLS
if (strpos($my_url, 'biblio') !== false) { // if it’s a bib url, assume everything after the ? is a citekey (pretty safe assumption) … #2do, remember that I can and do use biblio URLs of the format https://www.PainScience.com/gru, which this function will probably choke on
	$pos = strrpos($my_url, '?') + 1;
	$candidate_ck = substr($my_url, $pos, strlen($my_url) - $pos);
	if ($sources->safeGet($candidate_ck)->isValid()) {
		return $candidate_ck;
	}
}

	// 2. check for full article URLs
	$my_url = preg_replace('@php#.*$@', '', $my_url);
	if (strpos($my_url, 'php') == strlen($my_url)) {  // if it’s a full url (terminates with php), find the associated citekey
		$my_url = preg_replace('@php#.*@', '', $my_url);
	} // remove link anchor
	$pos = strrpos($my_url, '/') + 1; // get the position of the last slash in the url
	$filename = substr($my_url, $pos, strlen($my_url) - $pos); // extract the filename
	$sources->findRecordsThatContain('url', $filename); // look for records containing that filename
	if (count($sources->results()) == 1) { // tiny bit of validation: there should only be one
	foreach ($sources->results() as $entry) {
		$candidate_ck = $entry->get('citekey');
	}
	}
	if ($candidate_ck) {
		return $candidate_ck;
	}

	// 3. if the function still hasn't returned a value, proceed with the assumption that the URL is a short URL, which terminates with a citekey, and grab everything after the last slash
	$my_url = rtrim($my_url, '/');
	$pos = strrpos($my_url, '/') + 1;
	$candidate_ck = substr($my_url, $pos, strlen($my_url) - $pos);
	if ($sources->safeGet($candidate_ck)->isValid()) {
		return $candidate_ck;
	} else {
		return $original_url;
	} // if no citekey was found, return the original URL
}

/* add a post to a lightweight index of posts, probably obsolete now */
function add_post_to_index($post)
{
	// 2013-10-28, the index now appears to be obsolete, since the posts array now contains and is sorted by timestamps, but I’ll keep this code around for a while yet, just in case
	return;
	extract($post);
	global $index;
	if (! is_array($index)) {
		$index = [];
	} // first time here? initialize the array
	while (array_key_exists($timestamp, $index)) {
		$timestamp++;
	} // salt the timestamp to make them unique so same-day posts do not collide (this has the effect of creating a default 1-second difference in the timestamp if there is none already specified (with a day-order letter, eg, 2013-10-25a, 2013-10-25b etc)
	$index[$timestamp]['date'] = $date;
	$index[$timestamp]['title_smpl'] = $title_smpl; // a unique indentifier share with the main post array, critical for lookups
	$index[$timestamp]['post_class'] = $post_class; // a minor piece of info for building the html index
	$index[$timestamp]['title'] = $title;
	$index[$timestamp]['size'] = $size;
	$index[$timestamp]['priority'] = $priority;
	if ($tags) {
		$index[$timestamp]['tags'] = $tags;
	}
}

function get_auto_tags($post)
{
	global $ps;
	if (! $ps) {
		return;
	} // #psmod: for now, this is PS only
	$tags_explicit = $post['tags'];
	$content = $post['html'];
	include 'tags.php';
	//	echo "checking post [{$post['timestamp']}]……… ";
	foreach ($tags_arr as $tag=>$tag_data) {
		unset($indicators);
		if (array_key_exists('indicators', $tag_data)) {
			$indicators = explode(', ', $tag_data['indicators']);
			foreach ($indicators as $indicator) {
				//				echo "checking for indicator [$indicator] of tag [$tag] in post [{$post['timestamp']} with content [ ]………<br> ";
				if (inStr($indicator, $content)) {
					echo 'BINGO<br>';
					$est_relevance++;
					$tags_auto[] = $tag;
				}
			}
		}
	}
	if ($tags_auto) {
		$post['tags-auto'] = implode(',', $tags_auto);
	}

	return $post;
}

/* `indexing */
function get_indexing_status($post)
{
	global $ps;
	if (! $ps) {
		return $post;
	} // #psmod: for now, this is PS only

	$index = false; // indexing false (noindex) unless changed below

	$index = true; // flipped the script 2024-07-08, no longer believe this could possibly matter for the SEO reasons I originally had

	$ageInDays = daysSinceTstamp($post['timestamp']);
	$thresholdAge = 300; // higher is more permissive, more will get indexed
	$thresholdWords = 150;  // lower is more permissive, more will get indexed; anything 4x the threshold will be indexed, regardless of age
	$words = $post['words_exact'];

	// freshness is dominant, anything fresh will get indexed; as posts age to 2x and 3x the freshness threshold, they will only be indexed if they are increasingly large (2x and 3x the size threshold)
	if ($ageInDays < $thresholdAge) {
		$index = true;
	} // index all more recent posts, regardless of size
	if ($ageInDays > $thresholdAge * 1 and $ageInDays < $thresholdAge * 2 and $words > $thresholdWords * 1) {
		$index = true;
	}
	if ($ageInDays > $thresholdAge * 2 and $ageInDays < $thresholdAge * 3 and $words > $thresholdWords * 2) {
		$index = true;
	}
	if ($ageInDays > $thresholdAge * 3 and $ageInDays < $thresholdAge * 4 and $words > $thresholdWords * 3) {
		$index = true;
	}
	if ($words > $thresholdWords * 4) {
		$index = true;
	}

	$post['indexing'] = $index;

	// overrides…
	if ($post['mustindex']) {
		$post['indexing'] = true;
	}
	if ($post['noindex']) {
		$post['indexing'] = false;
	}

	return $post;
}

function makeMemberPostLists()
{
	global $ps;
	if (! $ps) {
		return;
	} // #psmod: this ƒ is for PS blog production only
	journal('making premium post lists', 1, true);
	global $settings;
	extract($settings);
	$links = [];
	global $posts;
	foreach ($posts as $timestamp => $post) {
		if (! $post['premium'] or ($post['preview']??false)) { // eliminate non-member posts + posts with preview status
			continue;
		} // this is only for premium posts
		// if ($timestamp < 1626332400) break; // won't find any premium posts before this
		$audioBadge = $post['post_audio'] ? " <span style='opacity:0.5'>&#128264;</span>" : null;
		$post['title'] = str_replace(' (Member Post)', '', $post['title']); // we're making a list of member posts, which will be used in a context where it’s obvious that the posts are for members, so it’s redundant to include it in the title
		$postPageUrl = "{$optional_subdir}/{$post['title_smpl']}.html";
		if (date('y', $timestamp) == date('y', time())) {
			$dateStr = date('M j', $timestamp);
		} // format a standard simple date string, without current year
		else {
			$dateStr = date('M j, Y', $timestamp);
		} // format a standard simple date string, including non-current year
		$linksArr[] = "\t<li><strong style='color:#777'>{$dateStr}</strong> — <a href='https://www.painscience.com{$postPageUrl}'>{$post['title']}</a> — <small style='color:#777'>{$post['words_round']} words{$audioBadge}</small></li>\n";
		$filesList .= "{$post['fn']}\n";
		$urlsList .= "{$post['url_live']}\n";
		//		$passesArr[] = "\t<li>https://www.painscience.com<strong>{$postPageUrl}</strong>?pass=" . generateDaypass($post['title_smpl']) . "</li>\n";
	}

	//	$dayPassList =  "\n<ul class='smallest font_narrow'>\n" . implode("\n", $passesArr) . "</ul>\n";
	//	checkSave($dayPassList, _ROOT . '/incs/list-posts-member--daypasses-all.html', 'all links in a list file');

	checkSave($filesList, _ROOT . '/incs/list-posts-member--files.html', 'all links in a list file');
	checkSave($urlsList, _ROOT . '/incs/list-posts-member--urls.html', 'all links in a list file');

	$linkListAll = "\n<ul>\n" . implode("\n", $linksArr) . "</ul>\n";
	checkSave($linkListAll, _ROOT . '/incs/list-posts-member--links-all.html', 'all links in a list file');

	$linksArr20 = array_slice($linksArr, 0, 20);
	$linkList20 = "\n<ul>\n" . implode("\n", $linksArr20) . "</ul>\n";
	checkSave($linkList20, _ROOT . '/incs/list-posts-member--links-20.html', 'all links in a list file');

	$linksArr10 = array_slice($linksArr, 0, 10);
	$linkList10 = "\n<ul>\n" . implode("\n", $linksArr10) . "</ul>\n";
	checkSave($linkList10, _ROOT . '/incs/list-posts-member--links-10.html', 'all links in a list file');

	$linksArr5 = array_slice($linksArr, 0, 5);
	$linkList5 = "\n<ul>\n" . implode("\n", $linksArr5) . "</ul>\n";
	checkSave($linkList5, _ROOT . '/incs/list-posts-member--links-5.html', 'all links in a list file');

	echo $linkList5;
}

function prepareTemplate($post, $templateFile)
{
	global $settings;
	extract($settings);

	$template_file_contents = file_get_contents($templateFile);
	ob_start();
	eval("?>$template_file_contents"); // On the use of eval in the PainSci CMS: craftdocs://open?blockId=D8BB4DEF-66B7-4395-9020-1CACBE6BBFC4&spaceId=bc7d854c-3e5b-a34e-4850-a6d2f31a1a59
	$thisTemplate = ob_get_contents();
	ob_end_clean();

	//		indexing is set to false for pages that are too short or too old, unless an exception is specified with “mustindex” keyword, see get_indexing_status
	if ($post['indexing'] === false) {
		$thisTemplate = str_replace('{$robots}', "<meta name='robots' content='noindex, follow'>", $thisTemplate);
	}
	if ($post['indexing'] === true) {
		$thisTemplate = str_replace('{$robots}', "<meta name='robots' content='index, follow'>", $thisTemplate);
	}

	$thisTemplate = str_replace('{$description}', $post['description'], $thisTemplate);
	$thisTemplate = str_replace('{$psid}', $post['psid'], $thisTemplate);

	if (!$GLOBALS['ps']) { // some non-ps tag handling
		$thisTemplate = str_replace('{$tags_hashed}', markupTagsHashes($post['tags']), $thisTemplate);
			if (isset($post['tags'])) {
				$thisTemplate = str_replace('{$tags}', markupTags($post['tags']), $thisTemplate);
			} else {
				$thisTemplate = str_replace('{$tags}', null, $thisTemplate);
			}
		}

	$thisTemplate = str_replace('{$tags}', $post['tags_private'], $thisTemplate);
	$thisTemplate = str_replace('{$words_round}', $post['words_round'], $thisTemplate);
	$thisTemplate = str_replace('{$cites}', $post['cites'], $thisTemplate);
	$thisTemplate = str_replace('{$source_file}', $post['source_file'], $thisTemplate);
	$thisTemplate = str_replace('{$title_smpl}', $post['title_smpl'], $thisTemplate);
	$thisTemplate = str_replace('{$pageimg_custom}', $post['pageimg_custom'], $thisTemplate);
	$thisTemplate = str_replace('{$pageimg_default}', $pageimg_default, $thisTemplate);
	$thisTemplate = str_replace('{$sitename}', $sitename, $thisTemplate);
	$thisTemplate = str_replace('{$sidecode}', $sitecode, $thisTemplate);
	$thisTemplate = str_replace('{$domain}', $domain, $thisTemplate);

	// setting page images is a bit of a mess right now, with full fragmentation between PS and non-PS builds; #2do: refactor so that ogimg is agnostic about context and can be used for all of the following scenarios

	$pubdate_iso = date('c', parseDate($pubdate));
	$sdTitle = substr($title, 0, 109); // truncate title to max allowable for this structured data

	if ($GLOBALS['ps']) { 	//schema_markup — This code block substantially similar to (but simpler than) code in head-schema-markup.php.  Bread crumb trails are missing, but ... it doesn't seem like they've ever done anything for articles, so meh.

		$sdArray = [];
		$sdArray[0] = [
			'@context' => 'https://schema.org',
			'@type' => 'Article',
			'headline' => $post['title'],
			'datePublished' => date('c', parseDate($post['date'])),
			'dateModified' => date('c', parseDate($post['date'])),
			'author' => [[ // author => an array of authors, each of which is an array of keys and values
				'@type' => 'Person',
				'name' => 'Paul Ingraham',
				'url' => 'https://www.painscience.com/about-paul-ingraham.php',
				]], // end of author => array
			]; // end of sdArray = array (

		if ($post['post_img']) {
			global $imgsBlacklistArr;
			if (! in_array($post['post_img'], $imgsBlacklistArr)) {
				$sdArray[0]['image'] = ['https://www.painscience.com/' . $post['post_img']]; // add an array of page ims
			}
		}

		if ($post['premium']) {
			$sdArray[0]['isAccessibleForFree'] = false;
			$sdArray[0]['hasPart'] = ['@type' => 'WebPageElement', 'isAccessibleForFree' => false, 'cssSelector' => 'members_only'];
		}

		$sdArray[] = [
			'@context' => 'https://schema.org',
			'@type' => 'BreadcrumbList',
			'itemListElement' => [
				[
					'@type' => 'ListItem',
					'position' => 1,
					'name' => 'Blog',
					'item' => 'https://www.PainScience.com/blog/',
					],
				],
			];

		$sdJSON = json_encode($sdArray, JSON_UNESCAPED_SLASHES);
		$schemaMarkup = "<script type=\"application/ld+json\">$sdJSON</script>\n\n"; // echo the JSON into a <script> element
		$thisTemplate = str_replace('<!-- schema markup -->', "<!-- schema markup -->\n{$schemaMarkup}", $thisTemplate);
	} // end of schema markup generation

	// first, for PS using ogimg...
	if ($GLOBALS['ps'] and $post['post_img']) { // declare custom image for PS
		global $imgsBlacklistArr;

		if (in_array(str_replace('imgs/', null, $post['post_img']), $imgsBlacklistArr)) { // image is blacklisted
			//Blacklist_of_images that shouldn't be rendered due to the risk of copyright trolling. The images-blacklist.txt file is read into $imgBlacklistArr by img.php and then checked wherever images are referenced.
			$thisTemplate = str_replace('<!-- page image -->', "<!-- '{$post['post_img']}' is a blacklisted image referenced by pubsys for featured image for blog post '{$post['title']}' -->\n<!-- page image -->", $thisTemplate);
			unset($post['post_img']); // like it never existed, so that the default will be triggered below
		} else { // img is not blacklisted, proceed
			$post_img = ogimg(str_replace('imgs/', null, $post['post_img']));
			$thisTemplate = str_replace('<!-- page image -->', "<!-- custom page image -->\n{$post_img}", $thisTemplate);
		}
	}
	if ($GLOBALS['ps'] and ! $post['post_img']) { // set default images for PS
		$post_img = ogimg($site_img);
		$thisTemplate = str_replace('<!-- page image -->', "<!-- default page image -->\n{$post_img}", $thisTemplate);
	}

	// for non-PS blogs
	if (! $GLOBALS['ps']) {
		if ($post['post_img']) { // if there’s a custom post img
			$thisTemplate = str_replace('<!-- default page image -->', '<!-- custom page image -->', $thisTemplate);
			$thisTemplate = str_replace('{$site_img}', $post['post_img'], $thisTemplate); // post_img contains the dir, either /imgs or /imgs-auto
		} else { // set default images for non-PS, no custom img
			$thisTemplate = str_replace('{$site_img}', 'imgs/' . $site_img, $thisTemplate);
		} // “imgs” dir must be inserted, because it isn’t in the settings value
	}

	if ($post['canonical']) { //psmod, stopped short of using because I don’t grok the effect of declaring one URL for link rel and another for og:url etc)
		$tmp = $post['canonical'];

		if (citation($post['canonical'], '[type]') == 'mine') {
			$url = citation($post['canonical'], '[url]');
		} else {
			$url = citation($post['canonical'], '[biburl]');
		}

		// <link rel="canonical"	href="http://{$domain}/blog/{$title_smpl}.html">

		$thisTemplate = preg_replace('@<link rel=.canonical.*?>@', "<link rel='canonical' href='$url'>", $thisTemplate);
	} /**/

	// a little tweaking …
	if ($post['post_class'] == 'micro' or $post['post_class'] == 'micro-img') { // for the small post types, change the class of the document <body> element
		$thisTemplate = str_replace('<body>', "<body class='micropost'>", $thisTemplate);
	}

	// `titles
	// (originally used the slug in the <title> element, until I discovered that resulted in excessively terse titles in Google search results)

	// start with the main H1 title
	if (isset($post['subtitle'])) { // if the title is divided into basetitle and subtitle …
		$thisTemplate = str_replace('<h1 id=\'title\'>{$title}', "<h1 id='title'>" . $post['basetitle'], $thisTemplate); // use the basetitle for id=title, with any markup intact
		$thisTemplate = str_replace('{$subtitle}', $post['subtitle'], $thisTemplate); // use the subtitle for id=subtitle, with any markup intact
	} else { // if there is no subtitle
		$thisTemplate = str_replace('<h1 id=\'title\'>{$title}', "<h1 id='title'>" . $post['title'], $thisTemplate); // use the basetitle for id=title, with any markup intact
		$thisTemplate = str_replace(' <span id="subtitle">{$subtitle}</span>', '', $thisTemplate);
	}

	// now deal with other uses of the title in metadata, like <title>
	// title field is everything, basetitle is the main title without subtitle, subtitle is the subtitle, slug is a simplified version of the basetitle
	if (strlen($post['title']) > 65 and isset($post['basetitle'])) { // if the full title is long and there's a basetitle we can use …
		$title = $post['basetitle'];
	} else {
		$title = $post['title'];
	}
	$thisTemplate = str_replace('{$title}', strip_tags($title), $thisTemplate); // insert other instances of the title, this time with stripped markup

	$post_date1 = date('Y-m-d', parseDate($post['date']));
	$post_date2 = date('M j, Y', parseDate($post['date'])); // 2015-10-06 changed date format to maximize chances of correct parsing by Googlebot
	$thisTemplate = str_replace('{$date1}', $post_date1, $thisTemplate);
	$thisTemplate = str_replace('{$date2}', $post_date2, $thisTemplate);

	if ($post['url']) {  // work with featured URLs
		// link title to a featured URL
		$thisTemplate = preg_replace("/<h1(.*?)>(.*?)<\/h1>/uism", '<h1$1><a href="' . $post['url'] . "\">$2&nbsp;<span style='color:#DDD'>∞</span></a></h1>", $thisTemplate);
		if (! isset($post['hidelink'])) { // stop now if the hidelink flag is set
			// make a featured link at the bottom of the post, #psmod, now for either a regular URL or citekey
			$link = $post['htmlFeaturedCk'] ? $post['htmlFeaturedCk'] : "<p class='widebar featured_link'><a href='{$post['url']}'>" . prettifyURL($post['url']) . '</a></p>';
			$thisTemplate = str_replace('</article>', "{$link}\n\n</article>", $thisTemplate); // add the link to the post
		}
	}

	// next/prev: prepare next & previous post links (#psmod, sort of: developed for PS, but these have zero effect if the post template doesn't have the variables, and they could be useful for the other blogs in the future)
	global $posts; // we'll need the full post array for this
	if ($post['prev_post']) {
		$prev = $posts[$post['prev_post']];
		$prev_link = "<a href='{$prev['title_smpl']}.html'>{$prev['title']}</a>";
	}
	if (isset($post['next_post'])) {
		$next = $posts[$post['next_post']];
		$next_link = "<a href='{$next['title_smpl']}.html'>{$next['title']}</a>";
	} else {
		$next_link = 'coming soon';
	}

	$thisTemplate = str_replace('{$prev_link}', $prev_link, $thisTemplate);
	unset($prev_link);
	$thisTemplate = str_replace('{$next_link}', $next_link, $thisTemplate);
	unset($next_link);

	return $thisTemplate;
}

function auditPsids()
{ // After posts array is complete, checks for missing psids. If it finds one, it aborts the build and generates a new PSID to be manually added to the post source (often needed for new posts).
	global $posts, $psidsArr;
	// printArr($psidsArr);
	foreach ($posts as $post) {
		if ($post['psid'] == '') {
			$candidate = 6518973;
			while (in_array($candidate, $psidsArr)) {
				echo "checking $candidate …";
				$candidate = random_int(1000000, 9999999);
			}
			exit("<p class='warning' style='font-size:2em'>⚠️ No psid for '{$post['title']}'. Use:<br><br><strong>psid$candidate</strong></p>"); // this isn't the best spot for this, but it works just fine and it’s important (for now) to annoy myself about missing psids
		}
	}
}

function getdescription_audio($description_audio)
{ /* The description_audio field is a rich text field supporting a some key PubSys shorthand conventions (but not all), citation shorthands (<<epsom>>), plus MarkdownExtra, plus PHP parsing.  This example will produce a 2-para HTML description:

	It’s one thing to *think* that the “medicine” is doing something. It’s another to *feel* it doing something. I reckon *sensation-enhanced placebo* is the engine that powers most manual therapies — it’s actually *the main reason they exist*. For better or worse, this is *why* patients love to be massaged, adjusted, stretched, jostled and vibrated, taped and scraped, zapped and jabbed. And more. ……… Read more about the concept of a “<a href='<?php u('placebo') ?>#sensation-enhancement'>sensation-enhanced placebo</a>.” */
	if (inStr('<<', $description_audio)) {
		global $record;
		$description_audio = $record->parseNestedxRefs($description_audio); // The parseNestedxRefs function was originally designed to deal with an arbitrary number of citations inside another citation (the annote field of a bib record), but it works quite well for this. Once in this file, I assigned new XRefRecord() to $record, and that makes the parseNestedxRefs method available.
	}
	$description_audio = renderPhpStr($description_audio); // The description_audio field also supports inline PHP, which mostly means that cite() calls are processed.  (The function aborts if there's no PHP tags.)
	$description_audio = str_replace('………', "\n\n", $description_audio);
	$description_audio = MarkdownExtra::defaultTransform($description_audio); // parse Markdown, but…
	// if (inStr("ously light can affect us emotionally, but is t", $description_audio)) echo "`". htmlentities($description_audio) . "`";
	$description_audio = str_replace('  ', ' ', $description_audio);

	return $description_audio;
}

function getCites($post)
{
	// This extracts all citations in a post and saves them in a field.  The original use-case was to expose citations to make-bib for #blogharvest, which does a fine job of harveseting citations from article source files … but harvests blog post OUTPUT, which doesn't have any citekeys.  This was the least-awkward solution.
	//	if ($post['psid'] == "6104445") {
	// echo "<br><br><p>{$post['title']}</p>";
	$content = $post['content'];
	preg_match_all("@(?:cite|u)\([\"'](.*?)[\"']@", $content, $citations1);
	preg_match_all('@<<([^ ]+?)>>@', $content, $citations2); // not currently used, and it actually conflicts with a PubSys shorthand for images … that I never use … but maybe someday I'll resolve that
	preg_match_all('@<<(.+?) .+?>>@', $content, $citations3);
	$citations = array_unique(array_merge($citations1[1], $citations2[1], $citations3[1])); // merge and de-dupe
	// var_dump($citations);
	if (count($citations) > 0) { // if citekeys were found
		foreach ($citations as $citation) {
			$citations_final[] = "cite('$citation')"; // reformat
		}
		$citations_final = implode(' ', $citations_final); // implode into ssv
		// $citations_final = str_replace("_","-", $citations_final);
		// the result in the output looks like "Cites == cite('stretching') cite('modality_empires') cite('strain')"
		// printArr($citations_final); var_dump($post['cites']);
		return $citations_final;
	} else {
		return null;
	}
}

/** returns @string, xref metadata: finds #blogharvest'd metadata if it exists in a previously saved version of the post file  */
function get_xref_metadata ($postPath, $psid) {
/*		* Xref metadata (XM) is an accession number and optional citekey saved to the first line of the file by Make Bib.
		* e.g. "$articleId="49891-41378"; // post_test_of_lidocaine_for_neck_pain".
		* The XM is only injected by Make Bib, which has nothing to do with PubSys, into the PubSys output.
		* So it does not (and cannot) exist in the post data at this point, because PubSys parses PubSys source files.
		* But if the XM was put into a previous version of PubSys output by Make Bib, we want to preserve that by transplanting it into post content before saving it to the same file, or a new file.
		* So this subroutine looks for existing files, either with the same name or a different name but the same psid, and extracts and transplants any XM found.
		* First, look for previously written versions of this post with the same filename. If it’s found in a file we're about to re-write, it’s trivial to transplant the Xref metadata first. This is the default, and it happens in bulk with every build.
		* Second is trickier and rare, occurring when there's a slug change: look for old versions of the post with different filenames … which can only be identified because they contain PubSys's unique identifier, the PSID. IF such a file exists, AND contains XM, THEN transplant it to the post content before writing the new file.  This handles slug changes, and is rare.
			* So, for example, suppose I create a post, harvest it with make bib, so it XM gets written to the first line of that post file, and so it is also represented in srcs.bib by a record with those unique identifiers… but PubSys has no idea about any of that! If I then change the slug, and then harvest again, Make Bib would mistake that new file for a NEW POST (because it contains no XM) and give it new unique identifiers. The only way to prevent that is to extract the XM from the old file, then save it with the new file, and get rid of the old file.  Tortuous!
			* To test slug changes, just change the slug of an existing and harvested post.  This subroutine should get the XM from the old file before trashing it, and the save it with the new one.
		// exit("<pre>" . htmlspecialchars($thePost) . "</pre>");
		*/
	if (file_exists($postPath)) { // if the file already exists, check for Xref metadata
		$oldPostLines = file($postPath); // get the file (maybe someday I'll get just the first line for this)
		if (inStr('articleId', $oldPostLines[0])) { // look "$articleId=" in the first line only
			// journal("Transplanting Xref metadata to new post file from old post file with the same name.", 2, false);
			return $oldPostLines[0]; // add the first line of the old file to the top of the new file contents
		}

	} else { // if the new file does not exist, check for existing files linked to this post by psid (rare, but it happens with any change to a slug)
		$oldPostPath = exec("grep -ril '$psid' " . STAGE); // Only 1 path is returned by exec as a string, even if there are >1 results. More than 1 result can be captured into an array with exec('grep stuff', $array, $retval), but I cannot conceive of a >1 use-case. There should only be 0 or 1 results. If 0, $filepath is an empty string.
		if ($oldPostPath !== '') { // if there is a path
			$oldPostContent = file_get_contents($oldPostPath); // capture the old file content as a string
			if (inStr($psid, $oldPostContent) and inStr('$articleId=', $oldPostContent)) { // if the content contains the current post's psid AND XM…
				$oldPostLines = file($oldPostPath); // Capture the old file content as an array of lines, which makes it easy to extract the first line. (An "elegant" one-liner with preg_match should be able to check AND capture, but the regex was harder than expected and I decided this was just fine.)
				// exit ("<br>psid $psid from new post '" . basename($postPath) . "' found in old file '" . basename($oldPostPath) . "' with Xref metadata, transplanting it: '" . htmlentities($oldPostLines[0]) . "'");
				journal("Transplanting Xref metadata from old post file to new. (Psid $psid from post with new name '<strong>" . basename($postPath) . "</strong>' found in old file '<strong>" . basename($oldPostPath) . "</strong>').", 2, true);
				unlink($oldPostPath); // delete the old file! note that this is doing at least part of the job of remove_old_files(), but not all (because it’s removing only files that contain XM)
				return $oldPostLines[0]; // add the first line of the old file to the top of the new file contents
			}
		}
	}
	return false;
}

function makeTagUsageList ($posts) { // Save every single usage of every tag on every post in this build to a file. The destiny of the file is to be ingested by tag-engine so that they can be counted.
	$tag_usage_list = ''; // Initialize a string that will contain a lot of tags … every tag from every post in a complete build.
	foreach ($posts as $post) { // go through the posts
		$tag_usage_list .= $post['tags_private'] . "\n"; // append the CSV tags for each post to the string
		}
	$tag_usage_list = str_replace(",", "\n", $tag_usage_list); // bulk change every single comma to an LF
	$fn = 'tags.used.txt';
	$path = $_SERVER['DOCUMENT_ROOT'] . '/guts';
	if (fileExistsNoChange($tag_usage_list, $file))
		journal("not saving tag usage list: $fn unchanged", 2, true);
	else { // save sitemap files
		journal("saving tag usage list", 2, true);
		saveAs($tag_usage_list, "$path/$fn");
		}
	}

?>






