#!/bin/bash
FILENAME=cards.csv #../php/lib/cards.csv
URL="https://docs.google.com/spreadsheets/d/e/2PACX-1vSwFEaoVii1M6f4w77Tf_r4TWUwxm5cejnYtzfboBbzCDGx15kul7CAOSsPXwrFr2kmUEVFqu-050p8/pub?gid=0&single=true&output=csv"

curl -L $URL > $FILENAME
cat $FILENAME
pushd ../php/lib
php convert_cards.php
popd
