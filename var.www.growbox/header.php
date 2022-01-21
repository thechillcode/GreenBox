<?php
// Header Functions
// $grwconfig and $sockets are passed created

// Get the status of light
// $grwconfig = sql Config
// $sockets = sql Sockets
// $socket_num = number of 
// returns 1 = on; 0 = off; -1 = not set
function get_light($grwconfig, $sockets) {
	if (($grwconfig["Light"] != 0) && ($grwconfig["Light"] <= $grwconfig["NumSockets"])) {
		$gpio = $sockets[$grwconfig["Light"]]["GPIO"];
		// Check GPIO Status, 0 = On, 1 = Off
		if (exec('gpio -g read '. $gpio) == 0) {
			// Light is on
			return 1;
		} else {
			return 0;
		}
	}
	return -1;
}

// get_header_image
function get_header_image($grwconfig, $sockets) {
	$header_image = "";
	$light = get_light($grwconfig, $sockets);
	if ($light == 1) {
		$header_image = "sun.png";
	}
	if ($light == 0) {
		$header_image = "moon.png";
	}
	return $header_image;
}
?>

<style>
ul {
    list-style-type: none;
    margin: 0;
    padding: 2px 0px;
}

li {
    display: inline;
}

.header_container {
	display: flex;
	align-items: center;
	justify-content: center
}
.header_text {
	padding-left: 0px;
}
.header_image {
	padding-right: 20px;
}
</style>

<ul>

<?php
$navlen = count($nav);

for($x = 0; $x < $navlen; $x++) {
	echo "<li><a class='button' href=\"".$nav[$x][1]."\">".$nav[$x][0]."</a></li>\n";
	if ($x < ($navlen-1))
	{
	echo "<li>|</li>\n";
	}
}

$header_image = get_header_image($grwconfig, $sockets);

//$sdate = date('D, Y M d - H:i:s');
//$sdate = date('H:i:s');
?>

<li style="float: right;"><a class="button" href="config">Config</a></li>
</ul>


<header>

<div class="header_container">
	<div class="header_image">
		<?php if ($header_image != "") { ?>
			<img src="<?= $header_image; ?>" height="18" />
		<?php } ?>
	</div>
	<div id="clock" class="header_text">
		<?php echo $title;?>
	</div>
</div>

</header>