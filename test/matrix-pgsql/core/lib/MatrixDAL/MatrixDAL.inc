<?php
class MatrixDAL {
	public static function dbConnect($a, $b) {
		return true;
	}
	public static function changeDb($a) {
		return true;
	}
	public static function executeSqlAssoc($a) {

		$data = array();

		if (strtoupper(substr($a, 0, 6)) === "SELECT") {

			$c = substr($a, 7, strlen($a)-8);
			if (!is_numeric($c)) $c = 5;

			for ($i=1; $i<=(int)$c; $i++) {
				$data[] = array(
					'one'		=> "some data here $i",
					'two'		=> "some data here $i",
					'three'		=> "some data here $i",
					'four'		=> "some data here $i",
					'five'		=> "some data here $i",
					'six'		=> "some data here $i",
//					'seven'		=> "some data here $i",
//					'eight'		=> "some data here $i",
//					'nine'		=> "some data here $i",
//					'ten'		=> "some data here $i",
//					'eleven'	=> "some data here $i",
//					'twelve'	=> "some data here $i",
					);
			}

			usleep(rand(0, 1500000));

		}
		elseif (strpos($a, '-- phpsqlc: tab-completion: table-names')) {

			$data = array(
				"my_table_one",
				"my_table_two",
				"my_table_three",
				"my_table_four",
				"my_table_five",
				"my_table_six",
			);
		}
		elseif (strpos($a, '-- phpsqlc: tab-completion: column-names')) {

			$data = array(
				"assetid",
				"type_code",
				"version",
				"name",
				"short_name",
				"status",
				"languages",
				"charset",
				"force_secure",
				"created",
				"created_userid",
				"updated",
				"updated_userid",
				"published",
				"published_userid",
				"status_changed",
				"status_changed_userid",
			);
		}

		return $data;
	}
}
?>
