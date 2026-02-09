#!/bin/bash
FILENAME=cards.csv #../php/lib/cards.csv
curl -L "https://docs.google.com/spreadsheets/d/e/2PACX-1vSwFEaoVii1M6f4w77Tf_r4TWUwxm5cejnYtzfboBbzCDGx15kul7CAOSsPXwrFr2kmUEVFqu-050p8/pub?output=csv" > $FILENAME
cat $FILENAME
pushd ../php/lib
php convert_cards.php
popd
