#!/bin/bash
#

P=`mdk info -v path`
cd "$P"
sed -e 's/ltr/rtl/' -i '' lang/en/langconfig.php
mdk purge
