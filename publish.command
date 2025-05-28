#!/bin/zsh

# Script builds a summary of changes in a Git repo residing in the same folder where it is run, and then prompts for either exit or confirmation to proceed with a commit and a push. The commit subject is a generic summary of changes.  It is built to integrate with PubSys, and has little to no utility outside that context. It is intended to be "idiot proof," enabling my non-technical parents to get the benefits of a Git repo with near zero exposure to Git itself.

# Exit immediately if any command exits with a non-zero status
set -e

# Change to the directory where this script is located
# This assumes the script lives in the root of the Git repo
cd "$(dirname "$0")"

# Derive a simple name for this repo from its root directory
repo_name=$(basename "$(git rev-parse --show-toplevel)")
repo_name=${repo_name:u}

# Sanity check: make sure we're inside a Git working tree
if ! git rev-parse --is-inside-work-tree > /dev/null 2>&1; then
  echo "Not a git repository. Exiting."
  exit 1
fi

# Stage all new, modified, and deleted files
git add -A

# If nothing is staged for commit, exit quietly
if git diff --cached --quiet; then
  echo "No changes to commit."
  exit 0
fi

# Get list of all staged files (added, copied, modified, renamed)
# This will be reused for directory-specific counts
staged=$(git diff --cached --name-only --diff-filter=ACMR)

# Count newly added files (status = A)
new_count=$(git diff --cached --name-only --diff-filter=A | wc -l | tr -d ' ')

# Count modified files (status = M)
updated_count=$(git diff --cached --name-only --diff-filter=M | wc -l | tr -d ' ')

# Total is the sum of new and updated files (ignore copies/renames for simplicity)
total_count=$((new_count + updated_count))

# Helper function: count how many staged files are in a given top-level directory
count_files_in_dir() {
  echo "$staged" | grep "^$1/" | wc -l | tr -d ' '
}

# Count files in specific tracked directories
post_count=$(count_files_in_dir "posts")
html_count=$(count_files_in_dir "html")
incs_count=$(count_files_in_dir "incs")
guts_count=$(count_files_in_dir "guts")

echo "
==========================================================================

	PUBLISHING: $repo_name

==========================================================================
	
This script publishes all changes since the last time you published.  (Specifically, it “commits” and “pushes” to a Git repository stored on GitHub.)

Total files new/changed: $total_count

$new_count — new files

$updated_count — changed files

$post_count — new/changed files in “posts” (the files you edit)

$html_count — new/changed HTML files (the files that actually get published)

$guts_count — settings files (“guts”, normal for a few to change)

$incs_count — new/changed code files (in the “incs” folder, usually zero)

PRESS ANY KEY to proceed, or X to cancel.
"
read -k 1 val

printf "\a"

echo "\n"

if [ "$val" = "x" ]
then
	echo "Aborting.\n\tWindow will close in 2 seconds.\n\n"
	echo "=========================================================================="
	sleep 2
	exit 0
fi

echo "Proceeding…"

# Commit with a detailed message summarizing what changed
commit_msg="$total_count files committed ($updated_count updated, $new_count new, $post_count post files, $html_count html files, $incs_count incs files, $guts_count guts files)"
git commit -m "$commit_msg"

# Push to the current upstream branch
git push

read -n 1 val

