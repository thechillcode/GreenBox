<!DOCTYPE html>

<!-- database query -->
<?php
	// includes:
	include 'functions.php';
	include 'functions_db.php';

// Const
$max_gpio = 30;
$execution_int = 10; // 10 minutes

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

$db_mode = SQLITE3_OPEN_READONLY;
if (isset($_REQUEST['id'])) {
	$db_mode = SQLITE3_OPEN_READWRITE;
}
// Get Database
$db = get_db($db_mode);
// Get Config
$grwconfig = get_grwconfig($db);
$socket_num = $grwconfig["NumSockets"];

// AirSensorData
$airsensors = array();
$airsensors_db = $db->query('SELECT rowid,* FROM AirSensors');
while ($result = $airsensors_db->fetchArray(SQLITE3_ASSOC))
{
	if ($result['enabled'] == 1) {
		$data = array("-", 0.0, 0.0);
		$query = $db->query('SELECT strftime("%Y-%m-%d %H:%M", datetime(dt, "localtime")), temperature,humidity FROM AirSensorData WHERE id=' . $result['rowid'] . ' ORDER BY dt DESC LIMIT 1');
		$data = $query->fetchArray();
		$airsensors[] = array($result['rowid'], $result['name'], $data[0], $data[1], $data[2]);
	}
}

// WeightSensorData
$weightsensors = array();
$weightsensors_db = $db->query('SELECT rowid,* FROM WeightSensors');
while ($result = $weightsensors_db->fetchArray(SQLITE3_ASSOC))
{
	if ($result['enabled'] == 1) {
		$data = array("-", 0);
		$query = $db->query('SELECT strftime("%Y-%m-%d %H:%M", datetime(dt, "localtime")), weight FROM WeightSensorData WHERE id=' . $result['rowid'] . ' ORDER BY dt DESC LIMIT 1');
		$data = $query->fetchArray();
		$weightsensors[] = array($result['rowid'], $result['name'], $data[0], $data[1]);
	}
}

// Cameras
$cameras = array();
$cameras_t = $db->query('SELECT rowid,enabled,usb FROM Cameras');
while ($result = $cameras_t->fetchArray(SQLITE3_ASSOC))
{
	if ($result['enabled'] == 1) {
		$cameras[] = array($result['rowid'], $result['usb']);
	}
}

// Input
$RunHandler = 0;
if (isset($_REQUEST['id']))
{
	$rowid = in_range(intval($_REQUEST['id']), 1, $grwconfig['NumSockets'], 1);
	$gpio = in_range(intval($_REQUEST['gpio']), 1, $max_gpio, 0);
	
	if (isset($_REQUEST['switch'])) {
		$state = in_range(intval($_REQUEST['switch']), 0, 1, 0);
		$db->exec('UPDATE Sockets SET State=' . $state . ' WHERE rowid='. $rowid);
		$vstate = ($state == 0) ? 1 : 0;
		exec('gpio -g write '. $gpio . ' ' . $vstate);
	}

	if (isset($_REQUEST['hon']) && isset($_REQUEST['hoff'])) {
		$hon = get_utc_hour(in_range(intval($_REQUEST['hon']), 0, 23, 0));
		$hoff = get_utc_hour(in_range(intval($_REQUEST['hoff']), 0, 23, 0));
		$db->exec('UPDATE Sockets SET HOn=' . $hon . ', HOff='. $hoff .' WHERE rowid='. $rowid);
		if ($rowid == $grwconfig['Light']) {
			$query = $db->exec('UPDATE Config SET val=' . $hon . ' WHERE name="LightOn"');
			$query = $db->exec("UPDATE Config SET val=" . $hoff . " WHERE name='LightOff'");
		}
		$RunHandler = 1;
	}
	
	if (isset($_REQUEST['power']) && isset($_REQUEST['pause'])) {
		$power = in_range(intval($_REQUEST['power']), 0, 300, 0);
		$pause = in_range(intval($_REQUEST['pause']), 0, 300, 0);
		$db->exec('UPDATE Sockets SET Power=' . $power . ', PowerCnt='. $power .', Pause='. $pause .', PauseCnt=0 WHERE rowid='. $rowid);
		$RunHandler = 1;
	}
	
	if (isset($_REQUEST['t_lower'])) {
		$t_lower = in_range(intval($_REQUEST['t_lower']), 0, 40, 0);
		$db->exec('UPDATE Sockets SET TLower=' . $t_lower . ' WHERE rowid='. $rowid);
		$RunHandler = 1;
	}
	
	if (isset($_REQUEST['t_higher'])) {
		$t_higher = in_range(intval($_REQUEST['t_higher']), 0, 40, 0);
		$db->exec('UPDATE Sockets SET THigher=' . $t_higher . ' WHERE rowid='. $rowid);
		$RunHandler = 1;
	}

	if (isset($_REQUEST['h_lower'])) {
		$h_lower = in_range(intval($_REQUEST['h_lower']), 0, 100, 0);
		$db->exec('UPDATE Sockets SET HLower=' . $h_lower . ' WHERE rowid='. $rowid);
		$RunHandler = 1;
	}

	if (isset($_REQUEST['h_higher'])) {
		$h_higher = in_range(intval($_REQUEST['h_higher']), 0, 100, 0);
		$db->exec('UPDATE Sockets SET HHigher=' . $h_higher . ' WHERE rowid='. $rowid);
		$RunHandler = 1;
	}
	
	if (isset($_REQUEST['thpower'])) {
		$thpower = in_range(intval($_REQUEST['thpower']), 0, 300, 0);
		$db->exec('UPDATE Sockets SET THPower=' . $thpower . ', THPowerCnt=0 WHERE rowid='. $rowid);
		$RunHandler = 1;
	}
	
	if (isset($_REQUEST['days']) && isset($_REQUEST['time']) && isset($_REQUEST['ml'])) {
		$days = in_range(intval($_REQUEST['days']), -1, 7, 0);
		$time = in_range(intval($_REQUEST['time']), -2, 23, -1);
		if ($time >= 0) {
			$time = get_utc_hour($time);
		}
		$ml = in_range(intval($_REQUEST['ml']), 0, 5000, 0);
		$dayscnt = $days;
		if ($days == -1) { $dayscnt = 0; }
		$db->exec('UPDATE Sockets SET DaysCnt=' . $dayscnt . ', Days=' . $days . ', Time='. $time .', MilliLiters='. $ml .' WHERE rowid='. $rowid);
		if ($time == -2) {
			$RunHandler = 1;
		}
	}
	
	if (isset($_REQUEST['minweight']) && isset($_REQUEST['maxweight']) && isset($_REQUEST['time'])) {
		$minweight = in_range(intval($_REQUEST['minweight']), 0, 20000, 0);
		$maxweight = in_range(intval($_REQUEST['maxweight']), 0, 20000, 0);
		$time = in_range(intval($_REQUEST['time']), -2, 23, -1);
		if ($time >= 0) {
			$time = get_utc_hour($time);
		}
		$db->exec('UPDATE Sockets SET MinWeight='. $minweight .', MaxWeight='. $maxweight .', Time='. $time .' WHERE rowid='. $rowid);
		if ($time == -2) {
			$RunHandler = 1;
		}
	}

	if ($RunHandler == 1) {
		$db->exec("UPDATE Config SET val=".$RunHandler." WHERE name='RunHandler'");
	}

	$db->close();
	header( "Location: ". substr($_SERVER['PHP_SELF'], 0, -4));
	exit ;
}

// Sockets
$sockets = get_sockets($db, $socket_num);

// Functions:
function get_interval_time($minutescnt) {
	global $execution_int;
	$s_cnt = "";
	$cnt = $minutescnt + $execution_int;
	$minutes = $cnt % 60;
	if ($minutes > 0) {
		$s_cnt = $minutes . "m";
	}
	$hours = (int)($cnt / 60);
	if ($hours > 0) {
		$s_cnt = $hours . "h" . $s_cnt;
	}
	return "&lt;&thinsp;" . $s_cnt;
}

?>

<html lang="en">
<head>
<title>Growbox</title>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" type="text/css" href="greenstyle.css">
<link rel="stylesheet" type="text/css" href="slider.css">
<link rel="shortcut icon" href="favicon.ico" type="image/x-icon" />
<style>
th
{
	padding: 5px 2px;
}
</style>
</head>
<body>

<?php
	$nav = array(
		array("Home", "growbox"),
		);
		
	$title = date('H:i:s');
?>

<?php include 'header.php';?>

<script type="text/javascript">
    var clockElement = document.getElementById('clock');

    function clock() {
		var dt = new Date();
        //clockElement.textContent = new Date().toString();
		clockElement.textContent = ("0" + dt.getHours()).slice(-2) + ":" +  ("0" + dt.getMinutes()).slice(-2) + ":" + ("0" + dt.getSeconds()).slice(-2);
    }

    setInterval(clock, 1000);
</script>

<hr>

<!-- Info -->
<div class="block">
	<?php if (count($airsensors) > 0) { ?>
	<div class="tile">
		<table style="float: left">
			<tr>
				<th>Climate</th>
				<th>(%rH)</th>
				<th>(&deg;C)</th> 
			</tr>
			<?php for ($i = 0; $i < count($airsensors); $i++) {
				$rowid = $airsensors[$i][0];
				$name = $airsensors[$i][1];
				$dt = $airsensors[$i][2];
				$t = $airsensors[$i][3];
				$h = $airsensors[$i][4];
				if ($t == 0.0 && $h == 0.0) { // no or false reading
					$t = '-';
					$h = '-';
				} else {
					$h = number_format($h, 1);
					$t = number_format($t, 1);
				}
			?>
			<tr>
				<td><a class="button" href='sensor?sensor=air&id=<?php echo $rowid; ?>'><?php echo $name; ?></a></td>
				<td><?php echo $h;?></td>
				<td><?php echo $t;?></td>
			</tr>
			<?php } ?>
		</table>
	</div>
	<?php } ?>
	<?php if (count($weightsensors) > 0) { ?>
	<div class="tile">
		<table style="float: left">
			<tr>
				<th>Weight</th>
				<th>(g)</th> 
			</tr>
			<?php for ($i = 0; $i < count($weightsensors); $i++) {
				$rowid = $weightsensors[$i][0];
				$name = $weightsensors[$i][1];
				$dt = $weightsensors[$i][2];
				$w = $weightsensors[$i][3];
			?>
			<tr>
				<td><a class="button" href='sensor?sensor=weight&id=<?php echo $rowid; ?>'><?php echo $name; ?></a></td>
				<td class='weightval'><?php echo $w;?></td> 
			</tr>
			<?php } ?>
		</table>
	</div>
	<?php } ?>
	<div class="tile">
		<table style="float: left">
			<tr>
				<td colspan="3" class='seninf'><a class="button" href='powermeter'>Power Meter</a></td>
			</tr>
			<tr>
				<td colspan="3" class='seninf'><a class="button" href='water'>Irrigation</a></td>
			</tr>
		</table>
	</div>
</div>
<?php if (count($cameras) > 0) { ?>
<div class="block">
	<?php for ($i=0; $i<count($cameras); $i++) {
		$id = $cameras[$i][0];
	?>
	<div class="tile">
		<a href="camera?id=<?php echo $id?>"><img src="tmp/image-<?php echo $id;?>.jpg" alt="Current" width="100%" height="auto"></a>
	</div>
	<?php } ?>
</div>
<?php } ?>
<hr>

<!-- Setup -->
<div id="settings" class="block">

<!-- Sockets -->
<?php for ($i = 1; $i <= $socket_num; $i++) { ?>

<?php
		$socket = $sockets[$i];
		$rowid = $socket['rowid'];
		$name = $socket['Name'];
		$gpio = $socket["GPIO"];
		
		if (($socket['Active'] == 1) && ($gpio > 0)) { ?>
		
		<!-- Check GPIO Status, 0 = On, 1 = Off -->
		<?php	$status = 0;
				if (exec('gpio -g read '. $gpio) == 0) { $status = 1; } ?>
		
		<div class="tile min-height">
		<form action="<?php $_PHP_SELF ?>" method="post">
			<span class="dot" <?php if ($status == 1) { echo 'style="background-color:green"'; } ?>></span><font size="4"><b>&nbsp;<u><?php echo $name; ?></u></b></font>
			<?php if ($socket["IsPumping"] == 1) {
				$pumped = $socket["Pumped"]/1000;
				if ($socket["ToPump"] != -1) {
					$to_pump = $socket["ToPump"]/1000;
					echo "<img style=\"position:relative; left:10px; top:5px;\" src=\"water_can.png\"/>&nbsp;&nbsp;&nbsp;&nbsp;($pumped&thinsp;l&nbsp;/&nbsp;$to_pump&thinsp;l)";
					//echo "<img style=\"position:relative; left:10px; top:5px;\" src=\"water_can.png\"/>&nbsp;&nbsp;&nbsp;&nbsp;&larr; ($to_pump l)";
				} else {
					echo "<img style=\"position:relative; left:10px; top:5px;\" src=\"water_can.png\"/>&nbsp;&nbsp;&nbsp;&nbsp;($pumped&thinsp;l)";
				}
			} ?>
			
			<!-------------------------------------------->
			<!-- ID Value Hidden -->
			<!-------------------------------------------->

			<input type="hidden" name="id" value="<?php echo $rowid; ?>">
			<input type="hidden" name="gpio" value="<?php echo $gpio; ?>">

			<div class="block">
				
				<!-------------------------------------------->
				<!-- Switch -->
				<!-------------------------------------------->
				
				<?php	$state = $socket['State'];
						$vstate = ($state == 0) ? 1 : 0;
						if ($socket['Switch'] == 1) { ?>

					<div class="tile">
						Off&nbsp;<label class="switch">
							<input type="checkbox" <?php if ($state==1) echo "checked"; ?> onclick="this.form.submit()">
							<span class="slider"></span>
						</label>&nbsp;On
						<input type="hidden" name="switch" value="<?php echo $vstate; ?>">
					</div>
				<?php } ?>

				<!-------------------------------------------->
				<!-- Timer -->
				<!-------------------------------------------->
				
				<?php	$hon = get_local_hour($socket['HOn']);
						$hoff = get_local_hour($socket['HOff']);
						if ($socket['Timer'] == 1) { ?>

					<div class="tile">
						<b>On:</b><br><br>
						<select name="hon" onchange="this.form.submit()">
							<?php 
								for ($x = 0; $x < 24; $x++) {
									if ($x == $hon) { echo "<option value=\"$x\" selected>"; }
									else { echo "<option value=\"$x\">"; }
									echo sprintf("%'.02d", $x) . ":00</option>";
								} 
							?>
						</select>
					</div>
					<div class="tile">
						<b>Off:</b><br><br>
						<select name="hoff" onchange="this.form.submit()">
							<?php 
								for ($x = 0; $x < 24; $x++) {
									if ($x == $hoff) { echo "<option value=\"$x\" selected>"; }
									else { echo "<option value=\"$x\">"; }
									echo sprintf("%'.02d", $x) . ":00</option>";
								} 
							?>
						</select>
					</div>
				<?php } ?>
				
				<!-------------------------------------------->
				<!-- Interval -->
				<!-------------------------------------------->
				
				<?php	$power = $socket['Power'];
						$pause = $socket['Pause'];
						if ($socket['Interval'] == 1) {
							$steps = array(0, 10, 20, 30, 40, 50, 60, 120, 180, 240, 300);
							$vals = array('-', '10min', '20min', '30min', '40min', '50min', '1h', '2h', '3h', '4h', '5h');
							$len = count($steps);
							$s_pwrcnt = "";
							$s_pauscnt = "";
							if ($status == 1) { // on
								$s_pwrcnt = get_interval_time($socket['PowerCnt']);
							} else {
								$s_pauscnt = get_interval_time($socket['PauseCnt']);
							}
						?>

					<div class="tile">
						<b>Power: </b><?= $s_pwrcnt; ?><br><br>
						<select name="power" onchange="this.form.submit()">
						<?php 
							for($x = 0; $x < $len; $x++) {
								if ($steps[$x] == $power) { echo "<option value=\"$steps[$x]\" selected>"; }
								else { echo "<option value=\"$steps[$x]\">"; }
								echo "$vals[$x]</option>";
							}
						?>
						</select>
					</div>
					<div class="tile">
						<b>Pause: </b><?= $s_pauscnt; ?><br><br>
						<select name="pause" onchange="this.form.submit()">
						<?php 
							for ($x = 0; $x < $len; $x++) {
								if ($steps[$x] == $pause) { echo "<option value=\"$steps[$x]\" selected>"; }
								else { echo "<option value=\"$steps[$x]\">"; }
								echo "$vals[$x]</option>";
							} 
						?>
						</select>
					</div>
				<?php } ?>
				
				<!-- Display Temp Humidity Power -->
				<?php $thpwr = 0; ?>
				
				<!-------------------------------------------->
				<!-- Temperature -->
				<!-------------------------------------------->
				
				<?php	if ($socket['Temperature'] == 1) {
							$thpwr = 1;
							$t_lower = $socket['TLower'];
							$t_higher = $socket['THigher']; ?>
						
					<div class="tile">
						<b>Temperature:</b><br><br>
						<select name="t_lower" onchange="this.form.submit()">
						<option value="0">-</option>
						<?php 
							for ($x = 10; $x <= 30; $x+=2) {
								if ($x == $t_lower) { echo "<option value=\"$x\" selected>"; }
								else { echo "<option value=\"$x\">"; }
								echo "< $x&deg;C</option>";
							} 
						?>
						</select>
						<select name="t_higher" onchange="this.form.submit()">
						<option value="0">-</option>
						<?php 
							for ($x = 10; $x <= 30; $x+=2) {
								if ($x == $t_higher) { echo "<option value=\"$x\" selected>"; }
								else { echo "<option value=\"$x\">"; }
								echo "> $x&deg;C</option>";
							} 
						?>
						</select>
					</div>
				<?php } ?>
				
				<!-------------------------------------------->
				<!-- Higher Temp -->
				<!-------------------------------------------->
				
				<?php	if ($socket['Humidity'] == 1) {
							$thpwr = 1;
							$h_lower = $socket['HLower'];
							$h_higher = $socket['HHigher']; ?>
						
					<div class="tile">
						<b>Humidity:</b><br><br>
						<select name="h_lower" onchange="this.form.submit()">
						<option value="0">-</option>
						<?php 
							for ($x = 10; $x <= 90; $x+=5) {
								if ($x == $h_lower) { echo "<option value=\"$x\" selected>"; }
								else { echo "<option value=\"$x\">"; }
								echo "< $x%</option>";
							} 
						?>
						</select>
						<select name="h_higher" onchange="this.form.submit()">
						<option value="0">-</option>
						<?php 
							for ($x = 10; $x <= 90; $x+=5) {
								if ($x == $h_higher) { echo "<option value=\"$x\" selected>"; }
								else { echo "<option value=\"$x\">"; }
								echo "> $x%</option>";
							} 
						?>
						</select>
					</div>
				<?php } ?>
				
				<!-------------------------------------------->
				<!-- THPwr -->
				<!-------------------------------------------->
				
				<?php	$thpower = $socket['THPower'];
						if ($thpwr == 1) {
							$steps = array(0, 10, 20, 30, 40, 50, 60, 120, 180, 240, 300);
							$vals = array('-', '10min', '20min', '30min', '40min', '50min', '1h', '2h', '3h', '4h', '5h');
							$len = count($steps);
						?>

					<div class="tile">
						<b>Power:</b><br><br>
						<select name="thpower" onchange="this.form.submit()">
						<?php 
							for($x = 0; $x < $len; $x++) {
								if ($steps[$x] == $thpower) { echo "<option value=\"$steps[$x]\" selected>"; }
								else { echo "<option value=\"$steps[$x]\">"; }
								echo "$vals[$x]</option>";
							}
						?>
						</select>
					</div>
				<?php } ?>				
				
				
				<!-------------------------------------------->
				<!-- Pump -->
				<!-------------------------------------------->
				
				<?php	$days = $socket['Days'];
						$time = $socket['Time'];
						if (($socket["IsPumping"] == 1) && ($time == -2)) {
							$time = -1;
						}
						if ($time >= 0) {
							$time = get_local_hour($time);
						}
						$ml = $socket['MilliLiters'];
						$flowrate = $socket['FlowRate'];
						$daycnt = $socket["DaysCnt"];
						$wsensorid = $socket["WSensorID"];
						$minweight = $socket["MinWeight"];
						$maxweight = $socket["MaxWeight"];
						if ($socket['Pump'] == 1) {
							$steps = array(100, 200, 300, 400, 500, 600, 700, 800, 900,
								1000, 1200, 1400, 1600, 1800, 
								2000, 2200, 2400, 2600, 2800, 
								3000, 3200, 3400, 3600, 3800,
								4000, 4200, 4400, 4600, 4800,
								5000);
							$len = count($steps);

							$date_now = new DateTime("now");
							$h = $date_now->format('H');
							$p_date = "";
							// IF Time is Water Now
							if ($time == -2) {
								$min = $date_now->format('i');	
								$min = ($min + 1) % 60;
								if ($min == 0) {
									$h += 1;
									$h %= 24;
								}									
								$p_date = $date_now->format('D, M d - ').sprintf( '%02d:', $h).sprintf( '%02d', $min);
							}
							// If Time is set and no WeightSensor set
							else if (($time > -1) && ($wsensorid == 0))  {
								if ($days > 0) {
									$date_now->add(new DateInterval("P".$daycnt."D"));
									$p_date = $date_now->format('D, M d - ').' '.sprintf( '%02d', $time).':00';	
								}
								// If $days == Once
								if ($days == -1) {
									if ($time <= $h) {
										$date_now->add(new DateInterval("P1D"));
									}
									$p_date = $date_now->format('D, M d - ').' '.sprintf( '%02d', $time).':00';
								}
							}
							$interval = array(-1, 0, 1, 2, 3, 4, 5, 6, 7);
							$int_label = array("1x", "-", "1 d", "2 d", "3 d", "4 d", "5 d", "6 d", "7 d");

				?>

					<?php if ($wsensorid != 0) { ?>
						<div class="tile">
							<b>Min Weight:</b><br><br>
							<input type="number" min="0" max="20000" step="100" onfocusout="this.form.submit()" name="minweight" value="<?php echo $minweight; ?>">&thinsp;g
						</div>
						<div class="tile">
							<b>Max Weight:</b><br><br>
							<input type="number" min="0" max="20000" step="100" onfocusout="this.form.submit()" name="maxweight" value="<?php echo $maxweight; ?>">&thinsp;g
						</div>
					<?php } else { ?>
						<div class="tile">
							<b>Interval:</b><br><br>
							<select name="days" onchange="this.form.submit()">
							<?php 
								for ($x = 0; $x < count($interval); $x++) {
									$val = $interval[$x];
									$label = $int_label[$x];
									echo "<option value=\"$val\"";
									if ($val == $days) { echo " selected"; }
									echo ">$label</option>";
								}
							?>
							</select>
						</div>
					<?php } ?>

					<div class="tile">
						<b>Time:</b><br><br>
						<select name="time" onchange="this.form.submit()">
						<option value="<?php echo (($socket["IsPumping"] == 1) ? -1 : -2); ?>">Now</option>
						<option value="-1" <?php if ($time == -1) { echo 'selected'; } ?>>-</option>
						<?php 
							for ($x = 0; $x < 24; $x++) {
								if ($x == $time) { echo "<option value=\"$x\" selected>"; }
								else { echo "<option value=\"$x\">"; }
								echo sprintf("%'.02d", $x) . ":00</option>";
							} 
						?>
						</select>
					</div>
					
					<?php if ($wsensorid == 0) { ?>
						<div class="tile">
							<b>Liters:</b><br><br>
							<select name="ml" onchange="this.form.submit()">
							<?php
								for ($x = 0; $x < $len; $x++) {
									if ($steps[$x] == $ml) { echo "<option value=\"$steps[$x]\" selected>"; }
									else { echo "<option value=\"$steps[$x]\">"; }
									echo sprintf("%.1f", ($steps[$x]/1000)) . " l</option>";
								}
							?>
							</select>
						</div>
					<?php } ?>
					
					<?php if ($p_date != "") { ?>
					<div class="tile">
						<b>Scheduled:</b><br><br>
						<?php echo $p_date; ?>
					</div>
					<?php } ?>

				<?php } ?>
				
			</div>
			
		</form>
		</div>
	
<?php } ?>
<?php } ?>

</div>

<hr>

<?php include 'footer.php';?>

</body>

<?php $db->close(); ?>

</html>
