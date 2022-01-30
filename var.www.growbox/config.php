<!DOCTYPE html>
<?php

	// includes:
	include 'functions.php';
	include 'functions_db.php';

// Get Database
$db = get_db(SQLITE3_OPEN_READWRITE);
// Get Config
$grwconfig = get_grwconfig($db);
$socket_num = $grwconfig["NumSockets"];
$sockets = get_sockets($db, $socket_num);

/////////////////////////////
// AUX
/////////////////////////////

// GPIO's
$max_gpio = 30;

// UTC Offset in hours
$dt_now = new DateTime("now");
$utc_offset = intval($dt_now->getOffset() / 3600);

function calc_offset($hour, $offset) {
	$hour = $hour + $offset;
	if ($hour < 0) {
		$hour += 24;
	}
	if ($hour > 23) {
		$hour %= 24;
	}
	return $hour;
}
function get_local_hour($utc_hour) {
	global $utc_offset;
	return calc_offset($utc_hour, $utc_offset);
}
function get_utc_hour($local_hour) {
	global $utc_offset;
	return calc_offset($local_hour, -($utc_offset));
}

/////////////////////////////
// Config

// Controls
$socket_controls = array("Switch","Timer","Interval","Temperature","Humidity","Pump");

// Burst
$burst_gpio = 0;
$burst_sleep = 0.01;
$burst_cycles = 100;

/////////////////////////////
// Read Config
$main = $grwconfig["Main"];

// Relay
$socket_gpio = array();

// AirSensor Displayed
$air_sensors_num = $grwconfig["NumAirSensors"];

// WeightSensors Displayed
$weight_sensors_num = $grwconfig["NumWeightSensors"];

// Start Date
$start_date = $grwconfig['StartDate'];
$start_year = floor($start_date/1000);
$start_days = $start_date - ($start_year*1000);
$start_date = DateTime::createFromFormat("Y z", "{$start_year} {$start_days}");
$start_month = $start_date->format('m');
$start_day = $start_date->format('d');

// Cameras
$cameras_num = $grwconfig["NumCameras"];
//$cam_usb_devices = array( 1 => "v4l2:/dev/video0", "v4l2:/dev/video1", "v4l2:/dev/video2", "v4l2:/dev/video3");
$cam_usb_devices = array( 1 => "/dev/video0", "/dev/video1", "/dev/video2", "/dev/video3");
$cam_rotations = array(0, 90, 180, 270);
$cam_brightness = array(0, 10, 20, 30, 40, 50, 60, 70, 80,  90, 100);
$cam_contrast = array(0, 10, 20, 30, 40, 50, 60, 70, 80,  90, 100);
$cam_hres = 800;
$cam_vres = 600;
$cam_fps=60;
$cam_awb="tungsten";

/////////////////////////////
// SQL : UPDATE
$RunHandler = 0;

// Sockets
for ($i = 1; $i <= $socket_num; $i++) {
	$socket = $sockets[$i];
	$gpio = $socket['GPIO'];
	$socket_gpio[$i] = $gpio;
}

// Main Switch
if (isset($_REQUEST['main']))
{
	$main = in_range(intval($_REQUEST['main']), 0, 1, 0);
	$db->exec("UPDATE Config SET val=" . $main . " WHERE name='Main'");
	if ($main == 0)
	{
		$db->exec("UPDATE Sockets SET IsPumping=0 WHERE IsPumping=1");
		$sockets_db = $db->query('SELECT rowid,GPIO FROM Sockets');

		while ($socket = $sockets_db->fetchArray(SQLITE3_ASSOC)) {

			$gpio = $socket["GPIO"];
			exec("gpio -g mode " . $gpio . " out");
			exec("gpio -g write " . $gpio . " 1");
		}
	} else {
		$RunHandler = 1;
	}
}

// Cameras
if (isset($_REQUEST["cam"])) {
	$cam_id = in_range(intval($_REQUEST["cam"]), 1, $cameras_num, 1);
	$cam_enabled = isset($_REQUEST["enable"]) ? 1 : 0;
	$cam_usb_device = in_range(intval($_REQUEST["usb"]), 0, count($cam_usb_devices), 0);
	$cam_usb = ($cam_usb_device == 0) ? "" : $cam_usb_devices[$cam_usb_device];
	$cam_rot = in_range(intval($_REQUEST["rot"]), 0, 270, 0);
	$cam_bright = in_range(intval($_REQUEST["brightness"]), 0, 100, 50);
	$cam_cont = in_range(intval($_REQUEST["contrast"]), 0, 100, 0);
	
	$query = $db->exec("UPDATE Cameras SET Enabled=" . $cam_enabled . ",
		usb='". $cam_usb . "',
		rotation=". $cam_rot .",
		hres=" . $cam_hres .",
		vres=". $cam_vres .",
		fps=". $cam_fps .",
		brightness=". $cam_bright .",
		contrast=". $cam_cont .",
		awb='". $cam_awb ."'
		WHERE rowid=". $cam_id);
	$RunHandler = 1;
}

// AirSensors
if (isset($_REQUEST["air"])) {
	$air_id = in_range(intval($_REQUEST["air"]), 1, $air_sensors_num, 1);
	$air_name = isset($_REQUEST["name"]) ? $_REQUEST["name"] : "";
	$air_enabled = isset($_REQUEST["enable"]) ? 1 : 0;
	$h_offset = in_range(intval($_REQUEST["h_offset"]), -100, 100, 1);
	$t_offset = in_range(intval($_REQUEST["t_offset"]), -100, 100, 1);
	
	$query = $db->exec("UPDATE AirSensors SET name='" . $air_name . "',
		enabled=". $air_enabled . ",
		h_offset=". $h_offset . ",
		t_offset=". $t_offset . "
		WHERE rowid=". $air_id);
	$RunHandler = 1;
}

// WeightSensors
if (isset($_REQUEST["weight"])) {
	$weight_id = in_range(intval($_REQUEST["weight"]), 1, $weight_sensors_num, 1);
	$weight_name = isset($_REQUEST["name"]) ? $_REQUEST["name"] : "";
	$weight_enabled = isset($_REQUEST["enable"]) ? 1 : 0;
	$weight_cal = in_range(floatval($_REQUEST["cal"]), 1, 200, 1);
	$weight_offset = in_range(intval($_REQUEST["offset"]), -1000000, 1000000, 0);
	
	$query = $db->exec("UPDATE WeightSensors SET name='" . $weight_name . "',
		enabled=". $weight_enabled . ",
		cal=". $weight_cal . ",
		offset=". $weight_offset . "
		WHERE rowid=". $weight_id);
	$RunHandler = 1;
}

// Update Sockets
if (isset($_REQUEST['socket']))
{
	$socket_id = in_range(intval($_REQUEST['socket']), 1, $socket_num, 0);
	if ($socket_id != 0)
	{
		$name = isset($_REQUEST['name']) ? $_REQUEST['name'] : "";
		
		$active = isset($_REQUEST['active']) ? in_range(intval($_REQUEST['active']), 0, 1, 0) : 0;
		
		// check for correct gpio's in rpi manual
		$gpio = isset($_REQUEST['gpio']) ? in_range(intval($_REQUEST['gpio']), 0, $max_gpio, -1) : -1;

		// Turn Socket off
		if (($active == 0) && ($gpio != 0)) {
			exec("gpio -g mode " . $gpio . " out");
			exec("gpio -g write " . $gpio . " 1");
			// Reset Pump
			$query = $db->exec("UPDATE Sockets SET IsPumping=0, ToPump=0 WHERE rowid=". $socket_id);
		}
	
		$load = isset($_REQUEST['load']) ? in_range(intval($_REQUEST['load']), 0, 10000, 0) : 0;
	
		$control = isset($_REQUEST['control']) ? in_range(intval($_REQUEST['control']), 0, 5, 0) : 0;
	
		$switch = 0;
		$timer = 0;
		$interval = 0;
		$temperature = 0;
		$humidity = 0;
		$pump = 0;
		
		switch ($control) {
			case 0:
				$switch = 1;
				break;
			case 1:
				$timer = 1;
				break;
			case 2:
				$interval = 1;
				break;
			case 3:
				$temperature = 1;
				break;
			case 4:
				$humidity = 1;
				break;
			case 5;
				$pump = 1;
				break;
		}
		
		$lower_t = 0;
		$higher_t = 0;
		$lower_h = 0;
		$higher_h = 0;
		
		$flowrate = "";
		if (isset($_REQUEST['flow'])) {
			$flowrate = "FlowRate=". (in_range(floatval($_REQUEST['flow']), 0, 1000, 0)) .",";
		}
		$wsensorid = "";
		if (isset($_REQUEST['wsensorid'])) {
			$wsensorid = "WSensorID=". (in_range(floatval($_REQUEST['wsensorid']), 1, $weight_sensors_num, 0)) .",";
		}
			
		$query = $db->exec("UPDATE Sockets SET Active=" . $active . ",
			Name='". $name . "',
			Load=". $load .",
			Control=" .$control.",
			Switch=". $switch .",
			Timer=". $timer .",
			Interval=". $interval .",
			Temperature=". $temperature .",
			Humidity=". $humidity .",
			Pump=". $pump .",
			". $flowrate . $wsensorid ."
			IsPumping=0
			WHERE rowid=". $socket_id);
	}
}

if (isset($_REQUEST['light'])) {
	$light = in_range(intval($_REQUEST['light']), 1, $socket_num, 0);
	$db->exec("UPDATE Config SET val=" . $light . " WHERE name='Light'");
	if ($light != 0) {
		$socket = $sockets[$light];
		$h_on = $socket["HOn"];
		$h_off = $socket["HOff"];
		$db->exec("UPDATE Config SET val=" . $h_on . " WHERE name='LightOn'");
		$db->exec("UPDATE Config SET val=" . $h_off . " WHERE name='LightOff'");
	}
}

// Start Date
if (isset($_REQUEST['startdate'])) {
	$date = date_create_from_format('Y-m-d', $_REQUEST['startdate']);
	if ($date) {
		$start_date = ($date->format("Y") * 1000) + $date->format("z");
		$db->exec("UPDATE Config SET val=" . $start_date ." WHERE name='StartDate'");
	}
}

// Reboot
// Do not user UTC time since crontab uses local time
if (isset($_REQUEST['reboot'])) {
	$hour = get_utc_hour(in_range(intval($_REQUEST['reboot']), 0, 23, 0));
	$db->exec("UPDATE Config SET val=" . $hour . " WHERE name='Reboot'");
	$db->exec("UPDATE Config SET val=1 WHERE name='SetReboot'");
	$RunHandler = 1;
}

if (isset($_REQUEST['create'])) {
	exec('php /var/www/growbox/archive.php > /dev/null &');
}

if (isset($_REQUEST['reset'])) {

	$db->exec("DELETE FROM Images WHERE rowid>0");
	exec('rm /var/www/growbox/cam/*');

	$db->exec("DELETE FROM AirSensorData WHERE rowid>0");
	$db->exec("DELETE FROM WeightSensorData WHERE rowid>0");
	$db->exec("DELETE FROM PowerMeter WHERE rowid>0");
	$db->exec("DELETE FROM Water WHERE rowid>0");

	$RunHandler = 1;
}

//Burst Relay
if (isset($_REQUEST['burst']))
{
	$burst_gpio = isset($_REQUEST['burst_gpio']) ? in_range(intval($_REQUEST['burst_gpio']), 1, 30, 0) : 0;
	$burst_sleep = isset($_REQUEST['burst_sleep']) ? in_range(floatval($_REQUEST['burst_sleep']), 0, 10, 0) : 0;
	$burst_cycles = isset($_REQUEST['burst_cycles']) ? in_range(intval($_REQUEST['burst_cycles']), 1, 100, 0) : 0;
	
	if (($burst_gpio != 0) && ($burst_sleep != 0) && ($burst_cycles != 0)) {
		exec('gpio mode '. $burst_gpio .' out');
		for ($x = 0; $x <= $burst_cycles; $x++) {
			exec('gpio -g write '. $burst_gpio . ' 0');
			sleep($burst_sleep);
			exec('gpio -g write '. $burst_gpio . ' 1');
			sleep($burst_sleep);
		}
	}	
}

if ($RunHandler == 1) {
	$db->exec("UPDATE Config SET val=1 WHERE name='RunHandler'");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$db->close();
	header( "Location: ". substr($_SERVER['PHP_SELF'], 0, -4));
	exit ;
}

/////////////////////////////
// SQL : READ

// Cameras
$cameras = array();
$cameras_db = $db->query('SELECT rowid,* FROM Cameras');
while ($result = $cameras_db->fetchArray(SQLITE3_ASSOC))
{
	$rowid = $result['rowid'];
	if ($rowid <= $cameras_num) {
		$enabled = $result['enabled'];
		$usb = $result['usb'];
		$rotation = $result['rotation'];
		$brightness = $result['brightness'];
		$contrast = $result['contrast'];
		
		$cameras[$rowid] = array($enabled, $usb, $rotation, $brightness, $contrast);
	}
}

// AirSensor
$air_sensors = array();
$air_sensors_db = $db->query('SELECT rowid,enabled,name,gpio,h_offset,t_offset FROM AirSensors');
while ($result = $air_sensors_db->fetchArray(SQLITE3_ASSOC))
{
	$rowid = $result['rowid'];
	if ($rowid <= $air_sensors_num) {
		$enabled = $result['enabled'];
		$name = $result['name'];
		$gpio = $result['gpio'];
		$h_offset = $result['h_offset'];
		$t_offset = $result['t_offset'];
		
		if ($rowid <= $air_sensors_num) {
			$air_sensors[$rowid] = array($enabled, $name, $gpio, $h_offset, $t_offset);
		}
	}
}

// WeightSensors
$weight_sensors = array();
$weight_sensors_active = array();
$weightsensors_db = $db->query('SELECT rowid,enabled,name,data,clk,cal,offset FROM WeightSensors');
while ($result = $weightsensors_db->fetchArray(SQLITE3_ASSOC))
{
	$rowid = $result['rowid'];
	if ($rowid <= $weight_sensors_num) {
		$enabled = $result['enabled'];
		$name = $result['name'];
		$data_gpio = $result['data'];
		$clk_gpio = $result['clk'];
		$calibration = $result['cal'];
		$offset = $result['offset'];
		
		if ($rowid <= $weight_sensors_num) {
			$weight_sensors[$rowid] = array($enabled, $name, $data_gpio, $clk_gpio, $calibration, $offset);
		
			if ($enabled == 1) {
				$weight_sensors_active[$rowid] = $name;
			}
		}
	}
}

// Close Database
$db->close();

?>

<html lang="en">
<head>

<script src="script/chart.js"></script>

<title>Growbox - Config</title>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width - 30px, initial-scale=1">
<link rel="stylesheet" type="text/css" href="greenstyle.css">
<link rel="stylesheet" type="text/css" href="slider.css">
<link rel="shortcut icon" href="favicon.ico" type="image/x-icon" />
<style>
</style>
</head>
<body>

<?php
	$nav = array(
		array("Home", "growbox"),
		//array("Config", "config"),
		);
		
	$title = "Config: ". date('H:i:s');
?>

<?php include 'header.php';?>

<script type="text/javascript">
    var clockElement = document.getElementById('clock');

    function clock() {
		var dt = new Date();
        //clockElement.textContent = new Date().toString();
		clockElement.textContent = "Config: " + ("0" + dt.getHours()).slice(-2) + ":" +  ("0" + dt.getMinutes()).slice(-2) + ":" + ("0" + dt.getSeconds()).slice(-2);
    }

    setInterval(clock, 1000);
</script>


<?php
/////////////////////////////
// Main Switch
/////////////////////////////
?>
<hr>
<form action="<?php $_PHP_SELF ?>" method="post">
<input type="text" name="main" value="<?php echo (($main == 1) ? 0 : 1) ?>" hidden />
<table style="background-color:#9b2423">
	<tr>
		<th><span style="color:white">Main Switch:<span></th>
		<td>
			<label class="switch">
				<input type="checkbox" <?php if ($main==1) echo "checked"; ?> onclick="this.form.submit()">
				<span class="slider"></span>
			</label>
		</td>
	</tr>
</table>
</form>

<?php
/////////////////////////////
// Sockets
/////////////////////////////
?>
<hr>

<?php
	for ($i = 1; $i <= $socket_num; $i++) { ?>
	
	<form id="socket<?= $i ?>" action="<?php $_PHP_SELF ?>" method="post">
		<input type="text" name="socket" value="<?= $i ?>" hidden />
	</form>
	
<?php } ?>

<table align="center">
	<tr>
		<th>Socket:</th>
		<th>GPIO:</th>
		<th>Active:</th>
		<th>Name:</th>
		<th>Load (W):</th>
		<th>Control:</th>
		<th colspan="2">Settings:</th>
	</tr>


<?php for ($i = 1; $i <= $socket_num; $i++) { ?>

<?php
	$socket = $sockets[$i];
	$rowid = $socket['rowid'];
	$gpio = $socket['GPIO'];
	$name = $socket['Name'];
		
	$control = $socket['Control'];
?>
	<input form="socket<?= $rowid ?>" type="hidden" name="gpio" value="<?php echo $gpio; ?>">
	<tr>
		<td># <?= $rowid ?></td>
		<td><?php echo sprintf('%02d', $gpio); ?></td>
		
		<td>
			<label class="switch">
				<input form="socket<?= $rowid ?>" type="checkbox" name="active" value="1" <?php if (($socket['Active']==1) && ($main==1)) echo "checked";
					elseif ($main==0) echo "disabled"; ?> onclick="this.form.submit()">
				<span class="slider"></span>
			</label>
		</td>
		<td><input form="socket<?= $rowid ?>" type="text" name="name" value="<?= $socket['Name'] ?>" onfocusout="this.form.submit()" ></td>

		<td><input form="socket<?= $rowid ?>" type="number" name="load" value="<?= $socket['Load'] ?>" min="0" max="2000" onfocusout="this.form.submit()" ></td>

		<td>
			<select name="control" form="socket<?= $rowid ?>" onchange="this.form.submit()">
			<?php foreach ($socket_controls as $ic => $scontrol) { ?>
				<option value="<?= $ic ?>" <?php if ($ic == $control) echo "selected"; ?>><?= $scontrol ?></option>
			<?php } ?>
			</select>
			
		</td>
		
		<?php // Control
		
		switch ($control) {
			case 0: // Switch
			case 1: // Timer
			case 2: // Intervall
			case 3: // Temperature
			case 4: // Humidity
				echo "<td></td><td></td>";
				break;

			case 5: // Pump
		?>
				<td>Flowrate (ml/s):&nbsp;<input form="socket<?= $rowid ?>" type="number" name="flow" value="<?php echo $socket['FlowRate']; ?>" min="0" max="1000" step="0.1" onfocusout="this.form.submit()" ></td>
				
				<td>
					Weight&nbsp;Sensor:&nbsp;<select name="wsensorid" form="socket<?= $rowid ?>" onchange="this.form.submit()">
					<option value="0" <?php if ($socket['WSensorID'] == 0) { echo "selected"; }?>> - </option>
					<?php foreach ($weight_sensors_active as $wid => $name) { ?>
						<option value="<?= $wid ?>" <?php if ($wid == $socket["WSensorID"]) echo "selected"; ?>><?= $name ?></option>
					<?php } ?>
					</select>
				</td>
		<?php
				break;
		}
		?>
	</tr>
	
<?php } ?>

</table>

<?php
/////////////////////////////
// Light
/////////////////////////////
?>
<hr>

<form action="<?php $_PHP_SELF ?>" method="post">
<table>
	<tr>
		<td>Light:</td>
		<td>
			<select name="light" onchange="this.form.submit()">
				<option value="0" <?php if ($grwconfig['Light'] == 0) { echo ' selected'; }?>> - </option>
				<?php for ($i = 1; $i <= $socket_num; $i++) {
					// Check for Timer Control
					if ($sockets[$i]['Control'] == 1) {
				?>
					<option value="<?= $i ?>" <?php if ($i == $grwconfig['Light']) echo "selected"; ?>>Socket <?= $i ?></option>
				<?php } } ?>
			</select>
			<?php if ($grwconfig['Light'] != 0) { ?>
				On: <?= get_local_hour($grwconfig['LightOn']) ?>:00, Off: <?= get_local_hour($grwconfig['LightOff']) ?>:00
			<?php } ?>
		</td>
	</tr>
</table>
</form>

<?php
/////////////////////////////
// Start Date
/////////////////////////////
?>
<hr>

<form action="<?php $_PHP_SELF ?>" method="post">
<table>
	<tr>
		<td>Start Date:</td>
		<td>
			<input type="date" name="startdate" value="<?php printf('%d-%02d-%02d', $start_year, $start_month, $start_day); ?>" onchange="this.form.submit()">
		</td>
	</tr>
</table>
</form>

<?php
/////////////////////////////
// Cameras
/////////////////////////////
?>

<hr>

<?php
	foreach ($cameras as $id => $values) { ?>
	
		<form id="cam_<?= $id ?>" action="<?php $_PHP_SELF ?>" method="post">
			<input type="text" name="cam" value="<?= $id ?>" hidden />
		</form>
	
	<?php } ?>

<table align="center">
	<tr>
		<th>Camera:</th>
		<th>Enabled:</th>
		<th>PI/USB:</th>
		<th>Rotation:</th>
		<th>Brightness:</th>
		<th>Contrast:</th>
	</tr>
	<?php foreach ($cameras as $id => $values) {
		$enabled = $values[0];
		$usb = $values[1];
		$rotation = $values[2];
		$brightness = $values[3];
		$contrast = $values[4];
	?>
	<tr>
		<td># <?php echo $id; ?></td>
		
		<td>
		<input type="checkbox" form="cam_<?= $id ?>" name="enable" value="1" <?php if ($enabled==1) { echo "checked"; } ?> onchange="this.form.submit()">
		</td>
		
		<td>
		<select form="cam_<?= $id ?>" name="usb" onchange="this.form.submit()">
			<option value="0" <?php if ($usb == "") { echo "selected"; }?>>Pi Camera</option>
			<?php foreach ($cam_usb_devices as $i => $device) { ?>
				<option value="<?= $i ?>" <?php if ($usb == $device) echo "selected"; ?>><?= $device ?></option>
			<?php } ?>
		</select>
		</td>
		
		
		<td>
		<select form="cam_<?= $id ?>" name="rot" onchange="this.form.submit()">
			<?php foreach ($cam_rotations as $rot) { ?>
				<option value="<?= $rot ?>" <?php if ($rotation == $rot) echo "selected"; ?>><?= $rot ?>&deg;</option>
			<?php } ?>
		</select>
		</td>

		<td>
		<select form="cam_<?= $id ?>" name="brightness" onchange="this.form.submit()">
			<?php foreach ($cam_brightness as $value) { ?>
				<option value="<?= $value ?>" <?php if ($brightness == $value) echo "selected"; ?>><?= $value ?> %</option>
			<?php } ?>
		</select>
		</td>

		<td>
		<select form="cam_<?= $id ?>" name="contrast" onchange="this.form.submit()">
			<?php foreach ($cam_contrast as $value) { ?>
				<option value="<?= $value ?>" <?php if ($contrast == $value) echo "selected"; ?>><?= $value ?> %</option>
			<?php } ?>
		</select>
		</td>

	<tr>
	<?php }	?>
</table>

<?php
/////////////////////////////
// AirSensors
/////////////////////////////
?>

<hr>

<?php
	foreach ($air_sensors as $id => $values) { ?>
	
		<form id="air_<?= $id ?>" action="<?php $_PHP_SELF ?>" method="post">
			<input type="text" name="air" value="<?= $id ?>" hidden />
		</form>
	
	<?php } ?>

<table align="center">
	<tr>
		<th>AirSensor:</th>
		<th>Enabled:</th>
		<th>Location:</th>
		<th>Offset (%rH):</th>
		<th>Offset (&deg;C):</th>
		<th>GPIO (DHT22 DATA):</th>
	</tr>
	<?php foreach ($air_sensors as $id => $values) {
		$enabled = $values[0];
		$name = $values[1];
		$air_gpio = $values[2];
		$h_offset = $values[3];
		$t_offset = $values[4];
	?>
	<tr>
		<td># <?php echo $id; ?></td>
		<td>
			<input type="checkbox" form="air_<?= $id ?>" name="enable" value="1" <?php if ($enabled !=0 ) { echo "checked"; } ?> onchange="this.form.submit()">
		</td>
		<td>
			<input form="air_<?= $id ?>" type="text" name="name" value="<?= $name ?>" onfocusout="this.form.submit()" >
		</td>
		<td><input form="air_<?= $id ?>" type="number" name="h_offset" value="<?= $h_offset ?>" min="-100" max="100" onfocusout="this.form.submit()" ></td>
		<td><input form="air_<?= $id ?>" type="number" name="t_offset" value="<?= $t_offset ?>" min="-100" max="100" onfocusout="this.form.submit()" ></td>
		<td><?= $air_gpio ?></td>
	<tr>
	<?php }	?>
</table>

<?php
/////////////////////////////
// WeightSensors
/////////////////////////////
?>

<hr>

<?php
	foreach ($weight_sensors as $id => $values) { ?>
	
		<form id="weight_<?= $id ?>" action="<?php $_PHP_SELF ?>" method="post">
			<input type="text" name="weight" value="<?= $id ?>" hidden />
		</form>
	
	<?php } ?>

<table align="center">
	<tr>
		<th>WeightSensor:</th>
		<th>Enabled:</th>
		<th>Name:</th>
		<th>Calibration:</th>
		<th>Offset:</th>
		<th>GPIO (HX711 DATA/CLK):</th>
	</tr>
	<?php foreach ($weight_sensors as $id => $values) {
		$enabled = $values[0];
		$name = $values[1];
		$data_gpio = $values[2];
		$clk_gpio = $values[3];
		$cal = $values[4];
		$offset = $values[5];
	?>
	<tr>
		<td># <?php echo $id; ?></td>
		<td>
			<input type="checkbox" form="weight_<?= $id ?>" name="enable" value="1" <?php if ($enabled !=0 ) { echo "checked"; } ?> onchange="this.form.submit()">
		</td>
		<td>
			<input form="weight_<?= $id ?>" type="text" name="name" value="<?= $name ?>" onfocusout="this.form.submit()" >
		</td>
		<td><input form="weight_<?= $id ?>" type="number" name="cal" value="<?= $cal ?>" min="1.0" max="1000.1" onfocusout="this.form.submit()" ></td>
		<td><input form="weight_<?= $id ?>" type="number" name="offset" value="<?= $offset ?>" min="-1000000" max="1000000" onfocusout="this.form.submit()" ></td>
		<td><?= $data_gpio."/".$clk_gpio ?></td>
	</tr>
	<?php }	?>
</table>

<?php
/////////////////////////////
// Reboot
/////////////////////////////
?>
<hr>

<?php
	$reboot = get_local_hour($grwconfig["Reboot"]);
?>
<form action="<?php $_PHP_SELF ?>" method="post">
<table>
	<tr>
		<th>Reboot:</th>
		<td>
			<select name="reboot" onchange="this.form.submit()">
				<?php 
				for ($x = 0; $x < 24; $x++) {
					if ($x == $reboot) { echo "<option value=\"$x\" selected>"; }
					else { echo "<option value=\"$x\">"; }
					echo sprintf("%'.02d", $x) . ":05</option>";
				} 
				?>
			</select>
		</td>
	</tr>
</table>
</form>
				
				
				
<?php
/////////////////////////////
// Archive
/////////////////////////////
$archive = $grwconfig["Archive"];
$archivedate = $grwconfig["ArchiveDate"];
?>
<hr>
<table>
	<tr>
		<td>
<?php
	if ($archive == 0) {
?>
	<form action="<?php $_PHP_SELF ?>" method="post">
		<input type="submit" class="button conf_button" name="create" value="Backup"/>&nbsp;Save Images and Data
	</form>
<?php
	} else {
		echo "Creating Archive ...";
	}
?>
		</td>
	</tr>
		<td>
<?php
	if ($archivedate != 0) {
		$jdate = $archivedate;
		$year = (int)($jdate/1000);
		$day = $jdate - ($year*1000);
		$adate = DateTime::createFromFormat("Y z", "{$year} {$day}");
		echo "<br>";
		echo "Latest Archive ".date_format($adate, "Y-m-d").": <a href='archive/archive.zip'>archive.zip</a>";
	}
?>
		</td>
	</tr>
</table>

<?php
/////////////////////////////
// Reset
/////////////////////////////
?>
<hr>
<table>
	<tr>
		<td>
			<form action="<?php $_PHP_SELF ?>" method="post">
				<input type="submit" class="button conf_button" name="reset" value="Reset"/>&nbsp;Reset Database: (Delete Images, Delete Data, Keep Configuration)
			</form>
		</td>
	</tr>
</table>

<?php
/////////////////////////////
// Burst
/////////////////////////////
?>
<hr>
<form id="burst" action="<?php $_PHP_SELF ?>" method="post">
	<input type="text" name="burst" value="1" hidden />
</form>
<table align="center">
	<tr>
		<th>Toggle Relay</th>
	</tr>
</table>
<table align="center">
	<tr>
		<th>Socket:</th>
		<th>Sleep (s):</th>
		<th>Cycles:</th>
	</tr>
	<tr>
		<td>
			<select form="burst" name="burst_gpio">
				<option value="0" <?php if ($burst_gpio == 0) { echo ' selected'; }?>> - </option>
				<?php foreach ($socket_gpio as $i => $gpio) { ?>
					<option value="<?= $gpio ?>" <?php if ($burst_gpio == $gpio) echo "selected"; ?>># <?= $i ?></option>
				<?php } ?>
			</select>
		</td>
		<td><input form="burst" type="number" name="burst_sleep" value="<?php echo $burst_sleep; ?>" min="0" max="10" step="0.01"></td>
		<td><input form="burst" type="number" name="burst_cycles" value="<?php echo $burst_cycles; ?>" min="0" max="100"></td>
		<td><button type="submit" form="burst" value="burst_now">Burst</button></td>
	</tr>
</table>
<hr>

<?php include 'footer.php';?>

</body>
</html>
