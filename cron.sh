#!/bin/bash
#
# Run cron for an instance

P=`mdk info -v path`
cd "$P"

while true; do
  php admin/cli/cron.php
  sleep 5
done
