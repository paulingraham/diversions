#!/bin/zsh

cd ~/Sites/diversions
git pull

# I do not want the terminal window spawned for this script to close on completion, because I always want the option to conveniently review the output.
print  "\nDone! Press RETURN to exit and close the window."
read -n 1 val 