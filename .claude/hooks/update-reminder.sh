#!/bin/sh
# Reminds Claude to check for Paul's updates when it's been a while.
#
# Runs before every message Susan or Bob sends (a UserPromptSubmit hook, see
# .claude/settings.json). It's the only time-based trigger for the
# check-for-updates routine in CLAUDE.md (the others are publishing and being
# asked), so sessions started close together check only once. This script
# does no network calls and needs no 1Password approval: it only looks at when
# git last fetched (the age of .git/FETCH_HEAD, which fetch and pull both
# update). When that's more than four hours ago, it prints a one-line reminder,
# which Claude sees along with the message. Claude then runs the usual routine,
# announcing the 1Password request first. The reminder repeats at most once an
# hour, so a session that can't reach GitHub isn't nagged every message.

root="${CLAUDE_PROJECT_DIR:-.}"
fetch_head="$root/.git/FETCH_HEAD"
stamp="$root/.git/update-reminder-stamp"
stale_after=$((4 * 60 * 60))
remind_every=$((60 * 60))
now=$(date +%s)

if [ -f "$fetch_head" ]; then last_fetch=$(stat -f %m "$fetch_head"); else last_fetch=0; fi
[ $((now - last_fetch)) -gt "$stale_after" ] || exit 0

if [ -f "$stamp" ]; then last_reminder=$(stat -f %m "$stamp"); else last_reminder=0; fi
[ $((now - last_reminder)) -gt "$remind_every" ] || exit 0

touch "$stamp"
echo "It's been more than four hours since the last check for Paul's updates. Before continuing, run the steps under 'Checking for updates from Paul' in CLAUDE.md: say a 1Password request is coming, then git fetch and git status -sb, and pull if behind."
exit 0
