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

// days
$d = 7;
if (isset($_GET['d']))
{
	$d = in_range(intval($_GET['d']), 1, 28, 7);
}

$debug = 0;
if (isset($_GET['debug'])) {
	$debug = intval($_GET['debug']);
}

// sensor: air or weight
$sensor = "air";
if (isset($_GET['sensor'])) {
	$sensor = $_GET['sensor'];
}

$id = 1;
if (isset($_GET['id'])) {
	$id = in_range(intval($_GET['id']), 1, 4, 1);
}

$min_1 = 100000;
$max_1 = -100000;
$min_2 = 100000;
$max_2 = -100000;
$avg_1 = 0;
$avg_2 = 0;
$num_points = 0;
$first = 0;
$last = 0;

$nav = array(
		array("Home", "growbox"),
		//array("AirSensor", "sensor?sensor=air&id=1"),
		);

$title = "Growbox - [Air Sensor]";
$label = 'Humidity (%rH)';
$label2 = 'Temperature (°C)';
//$color1 = 'rgb(75, 192, 192)';
//$color1 = 'rgb(0, 0, 255)'; //blue
//$color1 = 'rgb(30,144,255)'; //dodger blue
$color1 = '#66B2FF';
//$color2 = 'rgb(139, 0, 0)';
//$color2 = 'rgb(200, 0, 0)';
//$color2 = 'rgb(255, 0, 0)'; //red
//$color2 = 'rgb(255,69,0)'; //orange red
$color2 = '#FFB266';
$page_title = "Growbox - [Sensor]";

$db_table = "";
$query = "";
#$db_table = "AirSensorData";
#$query = 'SELECT dt,temperature,humidity FROM ' . $db_table . ' WHERE id='.$id.' AND datetime(dt) > datetime(\'now\',\'-' . ($d) . ' days\') ORDER BY dt ASC';

// weight 24H distance in points
$dist = 6*24;

switch ($sensor) {
	case "air":
		// get name
		$name = $db->querySingle('SELECT name FROM AirSensors WHERE rowid='. $id);
		$nav = array(
			array("Home", "growbox"),
			//array("Climate: ". $name, "sensor?sensor=air&id=" . $id),
			);
		$title = "Climate: " . $name;
		$label = "Humidity (%rH)";
		$label2 = "Temperature (°C)";
		$page_title = "Growbox - Climate: " . $name;

		$query = "SELECT humidity, temperature, s_mon, s_day, s_time, num_day".
			" FROM v_AirSensorData WHERE id=".$id." AND dt > datetime('now','-" . ($d) . " days') AND humidity IS NOT 0.0 AND temperature IS NOT 0.0 ORDER BY dt ASC";
		if ($debug == 1) {
			$query = "SELECT humidity, temperature, s_mon, s_day, s_time, num_day".
				" FROM v_AirSensorData WHERE id=".$id." AND dt > datetime('now','-" . ($d) . " days') ORDER BY dt ASC";
		}
		break;
		
	case "weight":
		$color1 = '#6E8EAD';
		$color2 = '#a6a6a6';
		$name = $db->querySingle('SELECT name FROM WeightSensors WHERE rowid='. $id);
		$nav = array(
			array("Home", "growbox"),
			//array("Weight: ". $name, "sensor?sensor=weight&id=" . $id),
			);
		$title = "Weight: " . $name;
		$label = "Weight (g)";
		$label2 = "Evaporation (g/24h)";
		// distance in points
		$page_title = "Growbox - Weight: " . $name;
		$db_table = "WeightSensorData";
		$query = "SELECT weight, NULL, s_mon, s_day, s_time, num_day".
			" FROM v_WeightSensorData WHERE id=".$id." AND dt > datetime('now','-" . ($d) . " days') ORDER BY dt ASC";
		break;
}

$data = array();
$sum_loss = array();
$total_loss = 0;
$prev = 0;
$results = $db->query($query);

if ($sensor == "air") {
	while ($res = $results->fetchArray(SQLITE3_NUM)) {
		//insert row into array
		array_push($data, $res);
	}
}

if ($sensor == "weight") {
	while ($res = $results->fetchArray(SQLITE3_NUM)) {
		//insert row into array
		array_push($data, $res);
		$diff = $prev - $res[0];
		$prev = $res[0];
		if (($diff > 0) && ($diff < 20)) {
			$total_loss += $diff;
		}
		array_push($sum_loss, $total_loss);
	}
}

$db->close();

$entries = count($data);
$entries_day = intval($entries/$d);
$day1 = $entries_day * 1;
$day3 = $entries_day * 3;
$day7 = $entries_day * 7;

$loss = array();
if ($sensor == "weight") {

	$last_loss = end($sum_loss);

	$loss[1] = $last_loss - $sum_loss[$entries - $day1];

	if ($entries >= $day3) {
		$loss[3] = intval(($last_loss - $sum_loss[$entries - $day3])/3);
	}

	if ($entries >= $day7) {
		$loss[7] = intval(($last_loss - $sum_loss[$entries - $day7])/7);
	}

	$loss[$d] = intval(($last_loss - $sum_loss[$entries - ($d * $entries_day)])/$d);
}

?>

<html lang="en">
<head>

<script src="script/chart.js"></script>

<title><?php echo $page_title; ?></title>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" type="text/css" href="greenstyle.css">
<link rel="shortcut icon" href="favicon.ico" type="image/x-icon" />
<style>
</style>
<script>

<?php if ($sensor == "air") { ?>
var unit1 = "%rH";
var unit2 = "°C";
<?php } elseif ($sensor == "weight") { ?>
var unit1 = "g";
var unit2 = "g/24h";
<?php } ?>
let measure_points = [0, 0];
function chartOnClick(event)
{
	let element = this.getElementAtEvent(event);
	if (element.length > 0) {
		var series= element[0]._model.datasetLabel;
		var label = unit1;
		if (element[0]._datasetIndex == 1) {
			label = unit2;
		}
		var value = this.data.datasets[element[0]._datasetIndex].data[element[0]._index];
		var target = document.getElementById("diff")
		
		measure_points[0] = measure_points[1];
		measure_points[1] = value;
		var diff = (measure_points[1] - measure_points[0]).toFixed(1);
		target.innerHTML = "";
		
		target.innerHTML = '<div class="tile"> \
			<table> \
			<tr> \
				<th>Delta (' + label + ')</th>\
			</tr> \
			<tr> \
				<td>'+ measure_points[0] + ' &rarr; ' + measure_points[1] + '&nbsp;=&nbsp;<strong>' + diff + '</strong></td> \
			</tr> \
			</table> \
		</div>'
	}
}
</script>
</head>
<body>

<?php
?>

<?php include 'header.php';?>

<hr>

<form action="<?php $_PHP_SELF ?>" method="get">
	<input name="sensor" type="hidden" value="<?php echo $sensor; ?>">
	<input name="id" type="hidden" value="<?php echo $id; ?>">
	<select name="d" onchange="this.form.submit()">
		<?php 
			$days = array(1,3,7,14,28);
			$dayslabel = array('1 day','3 days','1 week','2 weeks','4 weeks');
			for ($i=0; $i<count($days); $i++) {
				$num = $days[$i];
				$dlabel = $dayslabel[$i];
				?>
				
				<option value="<?= $num ?>" <?php if ($num == $d) { echo "selected"; } ?>><?= $dlabel ?></option>
			<?php } ?>
	</select>
</form>
<br>

<div style="width:100%;">
	<canvas id="canvas"></canvas>
</div>

<script>
var lineChartData = {
	labels: [
	<?php
		foreach ($data as $result)
		{
			$num_points++;
			echo "[\"$result[3], $result[2] $result[5]\",\"$result[4]\"],";
		}
		
	?>
	],
	datasets: [
		{
			label: '<?php echo $label; ?>',
			borderColor: '<?php echo $color1; ?>',
			backgroundColor: '<?php echo $color1; ?>',
			fill: false,
			data: [
			<?php
				foreach ($data as $result)
				{
					$avg_1 += $result[0];
					if ($result[0] < $min_1) { $min_1 = $result[0]; }
					if ($result[0] > $max_1) { $max_1 = $result[0]; }
					echo "$result[0],";
				}
			?>
			],
			yAxisID: 'y-axis-1',
		}
		<?php if ($sensor=="air") { ?>
		,{
			label: '<?php echo $label2; ?>',
			borderColor: '<?php echo $color2; ?>',
			backgroundColor: '<?php echo $color2; ?>',
			fill: false,
			data: [
			<?php
				foreach ($data as $result)
				{
					$avg_2 += $result[1];
					if ($result[1] < $min_2) { $min_2 = $result[1]; }
					if ($result[1] > $max_2) { $max_2 = $result[1]; }
					echo "$result[1],";
				}
			?>
			],
			yAxisID: 'y-axis-2'
		}
		<?php } ?>
		<?php if ($sensor=="weight") { ?>
		,{
			label: '<?php echo $label2; ?>',
			borderColor: '<?php echo $color2; ?>',
			backgroundColor: '<?php echo $color2; ?>',
			fill: false,
			data: [
			<?php
				$var = 0;
				for ($i=0; $i<$entries; $i++) {
					if ($i > $entries_day) {
						$sum_d = $sum_loss[$i] - $sum_loss[$i-$entries_day];
						echo "$sum_d,";
					} else {
						echo "null,";
					}
				
				}
			?>
			],
			yAxisID: 'y-axis-2'
		}
		<?php } ?>
	]
};

window.onload = function() {
	var ctx = document.getElementById('canvas').getContext('2d');
	window.myLine = Chart.Line(ctx, {
		data: lineChartData,
		options: {
			responsive: true,
			hoverMode: 'index',
			stacked: false,
			title: {
				display: false,
				text: '<?php echo $title; ?>'
			},
			scales: {
				yAxes: [
					{
						type: 'linear', // only linear but allow scale type registration. This allows extensions to exist solely for log scale for instance
						display: true,
						position: 'left',
						id: 'y-axis-1',
					}
					<?php if (($sensor=="air") or ($sensor == "weight")) { ?>
					,{
						type: 'linear', // only linear but allow scale type registration. This allows extensions to exist solely for log scale for instance
						display: true,
						position: 'right',
						id: 'y-axis-2',

						// grid line settings
						gridLines: {
							drawOnChartArea: false, // only want the grid lines for one axis to show up
						}
					}
					<?php } ?>
				],
			},
			onClick: chartOnClick
		}
	});
};
		
</script>

<hr>

<div class="block">

<?php if ($sensor == 'air') { ?>
	<div class="tile">
		<table>
			<tr>
				<th colspan="2">Humidity (%rH)</th>
			</tr>
				<td>Max:</td><td><?php echo $max_1; ?></td>
			<tr>
			</tr>
				<td>Min:</td><td><?php echo $min_1; ?></td>
			</tr>
			</tr>
				<td>Avg:</td><td><?php echo sprintf("%.1f", $avg_1/$num_points); ?></td>
			</tr>
		</table>
	</div>
	<div class="tile">
		<table>
			<tr>
				<th colspan="2">Temperature (°C)</th>
			</tr>
				<td>Max:</td><td><?php echo $max_2; ?></td>
			<tr>
			</tr>
				<td>Min:</td><td><?php echo $min_2; ?></td>
			</tr>
			</tr>
				<td>Avg:</td><td><?php echo sprintf("%.1f", $avg_2/$num_points); ?></td>
			</tr>
		</table>
	</div>
<?php } ?>
<?php if ($sensor == 'weight') { ?>
	<div class="tile">
		<table>
			<tr>
				<th colspan="2">Weight (g)</th>
			</tr>
				<td>Max:</td><td><?php echo $max_1; ?></td>
			<tr>
			</tr>
				<td>Min:</td><td><?php echo $min_1; ?></td>
			</tr>
		</table>
	</div>
	<div class="tile">
		<table>
			<tr>
				<th colspan="2">Evaporation (g/24h)</th>
			</tr>
			<?php foreach ($loss as $d => $val) { ?>
			</tr>
				<td><?= $d; ?> day avg:</td><td><?= $val; ?></td>
			</tr>
			<?php } ?>
		</table>
	</div>
<?php } ?>

<div id="diff"></div>

</div>

<hr>

<?php include 'footer.php';?>

</body>
</html>
