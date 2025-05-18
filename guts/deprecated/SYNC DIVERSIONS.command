#!/bin/sh

tput bel w

DOMAIN="www.susaningraham.net"

SITENAME="Diversions"

echo "

==========================================================================

	SYNC $SITENAME ($DOMAIN)

==========================================================================

	This will upload files to your live website.
	
	ONLY files that have changed will be uploaded. Even a tiny bit!

	(This has no effect on the files on your Mac.)

	PRESS ANY KEY to begin, or:

		D	dry-run (no actual uploading)
	
		R	include your RSS file (when you're sure it's ready)

		X	cancel (or just close the Terminal window)
"

read -n 1 val

printf "\a"

echo "\n"

if [ "$val" == "x" ]
then
	echo "	$SITENAME SYNC ABORTED!\n\n\tWindow will close in 3 seconds.\n\n"
	echo "=========================================================================="
	say -v Trinoids "sink aborted"
	sleep 3
	osascript -e 'tell application "Terminal" to quit'
fi

LABEL="sync"

if [ "$val" != "r" ]
then
	RSS="--exclude feed-*"
else
	LABEL="sync including RSS"
	echo "	Okay, the RSS file will be included with this sync.\n\n"
fi

if [ "$val" == "d" ]
then
	LABEL="dry run sync"
	echo "	Okay, dry run! No files will be harmed. Or uploaded.\n\n"
	DRY="--dry-run"
fi

echo "	WORKING…\n\n"

say -v Trinoids "proceeding with $LABEL"

OPTS="--verbose --progress --human-readable --stats --compress --archive --delete --checksum $DRY"

# RSS exclusion is includes here unless 
EXCLUDE="--cvs-exclude --exclude **.DS_Store --exclude **robots.txt --exclude google*.html --exclude mk --exclude www.ingraham.ca --exclude www.vancouvermassage.ca --exclude www.ephemeraltreasures.net --exclude www.susaningraham.net --exclude www.painscience.com $RSS"

echo "This is the exact ‘rsync’ that’s running:\n"
echo rsync $OPTS $EXCLUDE /Users/Susan/Sites/diversions/html/ writerly:public_html/www.susaningraham.net

echo "\n\n"

rsync $OPTS $EXCLUDE /Users/Susan/Sites/diversions/html/ writerly:public_html/www.susaningraham.net


echo "

	SYNC of $SITENAME IS DONE!

	Press any key to open $SITENAME in Safari,
	or just close the window.
	
==========================================================================
==========================================================================

"
sleep 2

say -v Trinoids "$LABEL now complete"

read -n 1 val

open -a Safari "http://$DOMAIN"
