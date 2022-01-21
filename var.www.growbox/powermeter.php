<!DOCTYPE html>

<!-- database query -->
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

$nav = array(
		array("Home", "growbox"),
		//array("PowerMeter", "powermeter"),
		);
$title = "Power Meter";
$label = 'Power Meter';

// days
$d = 7;
if (isset($_GET['d']))
{
	$d = in_range(intval($_GET['d']), 1, 28, 7);
}

// get socket names
//$sockets = $db->query('SELECT rowid,active,name FROM Sockets');

// last entry
$loads = $db->query("SELECT dt, l1, l2, l3, l4, l5, l6, l7, l8, s_mon, s_day, s_time, num_day".
					" FROM v_PowerMeter WHERE dt > datetime(\"now\",\"-" . ($d) . " days\") ORDER BY dt ASC");

$totalp = 0;

// since beginning
$totalpower = $db->querySingle('Select  SUM(l1)+SUM(l2)+SUM(l3)+SUM(l4)+SUM(l5)+SUM(l6)+SUM(l7)+SUM(l8) total from PowerMeter');
// in kWh
$totalpower /= 6000;
$day1 = $db->querySingle('Select dt from PowerMeter Limit 1');
$days_since = $db->querySingle('Select (strftime("%s", "now") - strftime("%s", dt)) / 86400 from PowerMeter Limit 1');

/*
$colors = array("rgb(255, 99, 132)", "rgb(54, 162, 235)", "rgb(255, 205, 86)", "rgb(201, 203, 207)",);
$colors = array("rgb(231, 76, 60)", "rgb(69, 179, 157)", "rgb(241, 196, 15)", "rgb(41, 128, 185)"
	,"rgb(186, 74, 0)","rgb(142, 68, 173)","rgb(149, 165, 166)","rgb(23, 32, 42)");
*/

// https://htmlcolorcodes.com/color-chart/
// Colors with only High Voltage Relays
$colors = array(
	// High Voltage
	"#FDD835", // Light (Yellow)
	"#E0E0E0", // (User) (Light Grey)
	"#A1887F", // (User) (Light Brown)
	"#4FC3F7", // Fans (Light Blue)

	"#EF9A9A", // Circulation Pump (Light Red)
	"#BA68C8", // Pump1 (Light Purple)
	"#B39DDB", // Pump2 (Light Dark Purple)

	"#81C784", // Exhaust (Light Green)
	);

// Colors with 5V Relays
/*
$colors = array(
	// High Voltage
	"#FDD835", // Light (Yellow)
	"#E0E0E0", // (User) (Light Grey)
	"#A1887F", // (User) (Light Brown)
	"#81C784", // Exhaust (Light Green)
	
	// 5V
	"#29B6F6", // Fans (Light Blue)
	"#EF9A9A", // Circulation Pump (Light Red)
	"#BA68C8", // Pump1 (Light Purple)
	"#B39DDB", // Pump2 (Light Dark Purple)
	);
*/
?>

<html lang="en">
<head>

<script src="script/chart.js"></script>

<title>Growbox - Powermeter</title>
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

<form action="<?php $_PHP_SELF ?>" method="get">
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
<br>

<canvas id="chart1" class="chartjs" width="200" height="120" style="display: block; width: 200px; height: 120px;"></canvas>

<script>
new Chart(document.getElementById('chart1').getContext('2d'), {
	type: 'line',
	data: {
		labels: [
			<?php
				while ($l = $loads->fetchArray(SQLITE3_NUM))
				{
					echo "[\"" . $l[10] .", ". $l[9] ." ".$l[12]."\",\"".$l[11]."\"],";
				}
			?>
		],
		datasets: [

			<?php for ($i = 0; $i < $socket_num; $i++) {
				
				$socket = $sockets[$i+1];
				$rowid = $socket['rowid'];
				$active = $socket['Active'];
				$name = $socket['Name'];
				
				if ($active == 1) {
				?>
			{
			label: '<?php echo $name; ?> (W)',
			steppedLine: true,
			backgroundColor: '<?php echo $colors[$i]; ?>',
			borderColor: '<?php echo $colors[$i]; ?>',
			fill:false,
			lineTension:0.1,			
			data: [
			<?php
				while ($l = $loads->fetchArray(SQLITE3_NUM))
				{
					if ($l[$rowid] > 0) { $totalp += $l[$rowid]/6; }
					echo "$l[$rowid],";
				}
			?>
			],},
			
			<?php }} ?>			
			
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
	$totalp/=1000;
?>
</script>

<hr>

<table>
	<tr>
		<th colspan="2">Power Consumption</th>
	</tr>
	<tr>
		<td><?php echo number_format($totalp,2,",","."); ?> kWh</td>
		<td>(in <?php echo $d; ?> days)</td>
	</tr>
	<tr>
		<td><?php echo number_format($totalpower,2,",","."); ?> kWh</td>
		<td>(since <?php echo substr($day1,0,-6); ?> / <?= $days_since; ?> days)</td>
	</tr>
	<tr>
		<td><?php echo number_format(($totalp/$d), 2, ",", ".")?> kWh</td>
		<td>(per day average)</td>
	</tr>

</table>
<hr>

<?php include 'footer.php';?>

</body>
</html>
