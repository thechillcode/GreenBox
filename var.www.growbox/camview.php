<!DOCTYPE html>


<!-- database query -->
<?php
	// includes:
	include 'functions.php';
	include 'functions_db.php';

$db = get_db();
// Get Config
$grwconfig = get_grwconfig($db);
$socket_num = $grwconfig["NumSockets"];
$sockets = get_sockets($db, $socket_num);

// ID
$id = 1;
if (isset($_GET['id']))
{
	$id = in_range(intval($_GET['id']), 1, 4, 1);
}

// months to display
$m = 1;
if (isset($_GET['m']))
{
	$m = in_range(intval($_GET['m']), 1, 12, 1);
}

// entries
$images = $db->query("SELECT filename FROM Images WHERE id=".$id." AND datetime(dt, 'localtime') > datetime('now','-". $m . " months') ORDER BY dt DESC");
?>

<html>
<title>Growbox - Camera #<?= $id; ?> - View</title>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" type="text/css" href="greenstyle.css">
<link rel="shortcut icon" href="favicon.ico" type="image/x-icon" />

<body>

<?php
	$nav = array(
		array("Home", "growbox"),
		array("Camera #". $id, "camera?id=".$id),
		//array("Camera #". $id ." - View", "camview?id=".$id),
		);
		
	$title = "Camera #". $id ." - ViewAll";
?>

<?php include 'header.php';?>

<hr>

<form action="<?php $_PHP_SELF ?>" method="get">
	<input type="hidden" name="id" value="<?= $id; ?>">
	<select name="m" onchange="this.form.submit()">
		<?php 
			$months = array(1, 3, 6, 12);
			$monthslabel = array('1 month','3 months','6 months','1 year');

			for ($i=0; $i<count($months); $i++) {
				$num = $months[$i];
				$dlabel = $monthslabel[$i]; ?>
				
				<option value="<?= $num ?>" <?php if ($num == $m) { echo "selected"; } ?>><?= $dlabel ?></option>
			<?php } ?>
	</select>
</form>

<br>

<!-- 3xX -->

<?php 

$img = $images->fetchArray(SQLITE3_NUM);
$date = substr($img[0], 0, 10);

while ($img) {

	echo "$date<br>\n";
	echo "<div class=\"block\">\n";

	$curdate = $date;
	while ($curdate == $date) {
	
		echo "\t<div class=\"tile\">\n";
		echo "\t\t<a href=\"cam/" . $img[0] . "\"><img src=\"cam/" . $img[0] . "\" width=\"360\" height=\"auto\"></a>\n";
		echo "\t</div>\n";
		$img = $images->fetchArray(SQLITE3_NUM);
		
		if ($img) {
			$date = substr($img[0], 0, 10);
		}
		else {
			$date = "";
		}
	}
	echo '</div>';
} 
$db->close();
?>

<br>

<?php include 'footer.php';?>

</body>
</html>
