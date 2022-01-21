<!DOCTYPE html>

<?php
	// includes:
	include 'functions.php';
	include 'functions_db.php';

// Get Database
$db = get_db();
// Get Config
$grwconfig = get_grwconfig($db);
$socket_num = $grwconfig["NumSockets"];
$sockets = get_sockets($db, $socket_num);

$id = 0;
if (isset($_GET['id']))
{
	$id = in_range(intval($_GET['id']), 1, $socket_num, 0);
}

// days
$d = 14;
if (isset($_GET['d']))
{
	$d = in_range(intval($_GET['d']), 1, 28, 14);
}

// Connect DB
$pumps = array();
$name = "Irrigation";
// get pumps
$query = $db->query('SELECT rowid, Name FROM Sockets WHERE Control=5 ORDER BY name ASC');
while ($pmp = $query->fetchArray(SQLITE3_NUM)) {
	if ($id==0) {
		$id = $pmp[0];
	}
	if ($id == $pmp[0]) {
		$name = $pmp[1];
	}
	$pumps[] = array($pmp[0], $pmp[1]);
}

$nav = array(
		array("Home", "growbox"),
		//array("Irrigation: ". $name, "water?id=".$id),
		);
$title = "Irrigation: ". $name;
$label = 'Water (ml)';

$totalml = 0;

// Database Query
$waters = $db->query("SELECT dt, ml, s_mon, s_day, s_time, num_day".
					" FROM v_Water WHERE id=". $id . " AND dt > datetime('now','-". ($d) . " days') ORDER BY dt ASC");

$s_dates = "";
$s_ml = "";
while ($water = $waters->fetchArray(SQLITE3_NUM)) {
	$s_dates = $s_dates . "[\"$water[3], $water[2] $water[5]\",\"$water[4]\"],";
	$s_ml = $s_ml . $water[1] . ",";
	
	$totalml += $water[1];
}

?>

<html lang="en">
<head>

<script src="script/chart.js"></script>

<title>Growbox - Irrigation: <?= $name; ?></title>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" type="text/css" href="greenstyle.css">
<link rel="shortcut icon" href="favicon.ico" type="image/x-icon" />
<style>
</style>
</head>
<body>

<?php
?>

<?php include 'header.php';?>

<hr>

<table>

<tr>
<td>
<form action="<?php $_PHP_SELF ?>" method="get">
	<input name="id" type="hidden" value="<?php echo $id; ?>">
	<select name="d" onchange="this.form.submit()">
		<?php 
			$days = array(1,3,7,14,28);
			$dayslabel = array('1 day','3 days','1 week','2 weeks','4 weeks');

			for ($i=0; $i<count($days); $i++) {
				$num = $days[$i];
				$dlabel = $dayslabel[$i]; ?>
				
				<option value="<?= $num ?>" <?php if ($num == $d) { echo "selected"; } ?>><?= $dlabel ?></option>
			<?php } ?>
	</select>
</form>
</td>
<td>
<form action="<?php $_PHP_SELF ?>" method="get">
	<select name="id" onchange="this.form.submit()">
		<?php
			for ($i = 0; $i < count($pumps); $i++) {
				if ($pumps[$i][0] == $id) { echo "<option value=\"".$id."\" selected>"; }
				else { echo "<option value=\"".$pumps[$i][0]."\">"; }
				echo $pumps[$i][1]."</option>";
			} 
		?>
	</select>
</form>
</td>
</tr>
</table>

<br>

<canvas id="chart1" class="chartjs" width="200" height="120" style="display: block; width: 200px; height: 120px;"></canvas>

<script>
new Chart(document.getElementById('chart1').getContext('2d'), {
	type: 'bar',
	data: {
		labels: [
			<?php
				echo $s_dates;
			?>
		],
		datasets: [
			{
			label: 'Water (ml)',
			steppedLine: true,
			backgroundColor: 'rgb(0, 96, 255)',
			borderColor: 'rgb(0, 96, 255)',
			fill:false,
			lineTension:0.1,			
			data: [
			<?php
				echo $s_ml;
			?>
			],},			
			
			]},
	options: {
			
		tooltips: {
			mode: 'index',
			intersect: false
		},
		responsive: true,
		scales: {
			xAxes: [{
				stacked: true,
			}],

			yAxes: [{
				stacked: true
			}]
		}
			
		}});
			
			
<?php
	$db->close();
?>
</script>

<hr>

<table>
	<tr>
		<th colspan="2">Water Consumption</th>
	</tr>
	<tr>
		<td><?php echo number_format($totalml/1000,2,",","."); ?> l</td>
		<td>(<?php echo $d; ?> days)</td>
	</tr>
	<tr>
		<td><?php echo number_format(($totalml/$d/1000), 2, ",", "."); ?> l</td>
		<td>(per day average)</td>
	</tr>
</table>

<hr>

<?php include 'footer.php'; ?>

</body>
</html>
