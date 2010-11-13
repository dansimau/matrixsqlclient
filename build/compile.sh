#!/bin/bash

# List of files to be included in the project
FILES="lib/main.php lib/MatrixSqlTerminal.class.php lib/SimpleReadline.class.php lib/ArrayToTextTable.class.php lib/HistoryStorage.class.php"

# Make temp file
TMPFILE="$(mktemp)"

# Write header
cat <<EOF >>$TMPFILE
<?php
/**
 * matrixsqlclient.php - Interactive database terminal in PHP.
 *
 * dsimmons@squiz.co.uk
 * $(date +%Y-%m-%d) (rev $(git rev-list --all |wc -l |tr -d ' '))
 *
 */

EOF

# Write each project file to single compiled version
for f in $FILES; do

	cat $f |egrep -v '<\?php|\?>' >> $TMPFILE
	echo "" >> $TMPFILE

done

# Write footer
cat <<EOF >>$TMPFILE
?>
EOF

# Copy file to build dir
cp $TMPFILE matrixsqlclient.php
