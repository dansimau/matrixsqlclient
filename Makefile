.PHONY: all test clean

all:
	build/compile.sh

test:
	php ./matrixsqlclient.php test/matrix-pgsql || reset

clean:
	rm -f matrixsqlclient.php
