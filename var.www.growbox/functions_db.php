
<?php
// PHP DataBase Functions

function get_db($db_mode = SQLITE3_OPEN_READONLY) {
	$db = new SQLite3('/home/pi/DB/Growbox.db', $db_mode);
	return $db;
}

// Returns
// $grwconfig = get_grwconfig($db);
// $socket_num = $grwconfig["NumSockets"];
function get_grwconfig($db) {
	$config_db = $db->query('SELECT * FROM Config');
	$grwconfig = array();
	while ($conf = $config_db->fetchArray(SQLITE3_ASSOC))
	{
		$grwconfig[$conf['name']] = $conf['val'];
	}
	return $grwconfig;
}

function get_sockets($db, $socket_num) {
	$sockets = array();
	$sockets_db = $db->query('SELECT rowid,* FROM Sockets');
	while ($socket = $sockets_db->fetchArray(SQLITE3_ASSOC)) {
		$rowid = $socket['rowid'];
		if ($rowid <= $socket_num) {
			$sockets[$rowid] = $socket;
		}
	}
	return $sockets;
}

?>