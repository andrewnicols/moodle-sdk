#!/bin/bash
#

P=`mdk info -v path`
cd "$P"
sed -e 's/rtl/ltr/' -i '' lang/en/langconfig.php
mdk purge
