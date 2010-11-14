<?php
class MatrixDAL {
	public static function dbConnect($a, $b) {
		return true;
	}
	public static function changeDb($a) {
		return true;
	}
	public static function executeSqlAssoc($a) {
		if (strtoupper(substr($a, 0, 5))) {
			return array(
							array('one', 'two', 'three', 'four'),
							array('one', 'two', 'three', 'four'),
						);
		}
	}
}
?>
