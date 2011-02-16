#!/bin/bash

# List of files to be included in the project
FILES="lib/main-facebook.php lib/TerminalDisplay.class.php lib/InteractiveSqlTerminal.class.php lib/SimpleReadline.class.php lib/ArrayToTextTable.class.php lib/HistoryStorage.class.php lib/DbBackend.class.php lib/DbBackendPlugin.class.php lib/DbBackend.Facebook.class.php"

# Make temp file
TMPFILE="$(mktemp -t $(basename $0).XXXXXXXX)" || exit 1

REV=$(git rev-list --all |wc -l |tr -d ' ')
DATE=$(date +%Y-%m-%d)

# Write header
cat <<EOF >>$TMPFILE
#!/usr/bin/php
<?php
/**
 * phpsqlc.php - Interactive database terminal in PHP.
 * https://github.com/dansimau/matrixsqlclient
 *
 * Copyright 2011, Daniel Simmons <dan@dans.im>
 *
 * Licensed under the MIT license.
 * http://opensource.org/licenses/mit-license.php
 *
 * Contains Array to Text Table Generation Class, copyright 2009 Tony Landis.
 * Licensed under the BSD license.
 * http://www.opensource.org/licenses/bsd-license.php
 *
 * $DATE (rev $REV)
 *
 */
\$rev = "r$REV";

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
cp $TMPFILE facebooksqlclient.php

# Make it executable
chmod +x facebooksqlclient.php
