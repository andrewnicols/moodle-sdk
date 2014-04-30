#!/usr/bin/env sh

P=`mdk info -v path`
B=`mdk info -v stablebranch`

cd "$P"
git add .
git reset mdkscriptrun.sh
git stash save
git checkout "$B"
