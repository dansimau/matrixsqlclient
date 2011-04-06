<?php
//$db_conf = array('db2' => array('type' => 'pgsql', 'DSN' => 'test'));
$db_conf = array('db2' => array('type' => 'pgsql', 'DSN' => '(DESCRIPTION =(ADDRESS = (PROTOCOL = TCP)(HOST = HLRAC01-VIP)(PORT = 1521))(ADDRESS = (PROTOCOL = TCP)(HOST = HLRAC02-VIP)(PORT = 1521))(LOAD_BALANCE = yes)(CONNECT_DATA =(SERVER = DEDICATED)(SERVICE_NAME = ORARAC)(FAILOVER_MODE =(TYPE = SELECT)(METHOD = BASIC)(RETRIES = 180)(DELAY = 5))))'));
?>
