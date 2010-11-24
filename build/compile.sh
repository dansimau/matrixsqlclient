#!/bin/bash

# List of files to be included in the project
FILES="lib/main.php lib/InteractiveSqlTerminal.class.php lib/SimpleReadline.class.php lib/ArrayToTextTable.class.php lib/HistoryStorage.class.php lib/DbBackend.class.php lib/DbBackend.MatrixDAL.class.php"

# Make temp file
TMPFILE="$(mktemp)"

REV=$(git rev-list --all |wc -l |tr -d ' ')
DATE=$(date +%Y-%m-%d)

# Write header
cat <<EOF >>$TMPFILE
<?php
/**
 * matrixsqlclient.php - Interactive database terminal in PHP.
 *
 * dsimmons@squiz.co.uk
 * $DATE (rev $REV)
 *
 */
\$rev = $REV;

EOF

# Write constants to the top of the file
for f in $FILES; do
	cat $f |egrep '^define' >> $TMPFILE
done

echo "" >> $TMPFILE

# Write each project file to single compiled version
for f in $FILES; do

	cat $f |egrep -v '<\?php|\?>|^require|^define' >> $TMPFILE
	echo "" >> $TMPFILE

done

# Write footer
cat <<EOF >>$TMPFILE
?>
EOF

# Copy file to build dir
cp $TMPFILE matrixsqlclient.php
