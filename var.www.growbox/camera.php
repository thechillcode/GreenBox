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

// ID
$id = 1;
if (isset($_GET['id']))
{
	$id = in_range(intval($_GET['id']), 1, 4, 1);
}

// entries
$images = $db->query("SELECT filename FROM Images WHERE id=".$id." AND datetime(dt, 'localtime') > datetime('now','-1 month') ORDER BY dt ASC");
?>

<html>
<title>Growbox - Camera #<?= $id ?></title>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" type="text/css" href="w3.css">
<link rel="stylesheet" type="text/css" href="greenstyle.css">
<link rel="shortcut icon" href="favicon.ico" type="image/x-icon" />
<style>
.mySlides {display:none;}
.center {
	padding: 10px;
	text-align: center;
}
.slidebutton {
	padding: 10px 30px;
	width: 300px;
}
</style>
<body>

<?php
	$nav = array(
		array("Home", "growbox"),
		//array("Camera #". $id, "camera?id=".$id),
		array("Camera #". $id ." - ViewAll", "camview?id=".$id),
		);
		
	$title = "Camera #". $id;
?>

<?php include 'header.php';?>

<hr>

<div class="block">
	<div class="w3-content w3-display-container">

		<img class="mySlides" src="tmp/image-<?php echo $id;?>.jpg" width="100%" height="auto">
	<?php
		while($img = $images->fetchArray(SQLITE3_NUM))
		{
			echo "\t\t<img class='mySlides' src='cam/$img[0]' width='100%' height='auto'>\n";
		}
		$db->close();
	?>
	  <button class="w3-button w3-black w3-display-left" onclick="plusDivs(-1)">&#10094;</button>
	  <button class="w3-button w3-black w3-display-right" onclick="plusDivs(1)">&#10095;</button>
	</div>
</div>

<div class="center">
	<button id="slidebutton" class="button slidebutton" onclick="showSlides(1)">Slideshow</button>
</div>

<script>
var auto=0;
var slideIndex = 1;
showDivs(slideIndex);

function plusDivs(n) {
  showDivs(slideIndex += n);
}

function showDivs(n) {
  var i;
  var x = document.getElementsByClassName("mySlides");
  if (n > x.length) {slideIndex = 1}    
  if (n < 1) {slideIndex = x.length}
  for (i = 0; i < x.length; i++) {
     x[i].style.display = "none";  
  }
  x[slideIndex-1].style.display = "block";  
}

var show = 0;
function showSlides(x) {
	if (x != null) {
		show = x;
	}
	if (show == 0) {
		var button = document.getElementById("slidebutton");
		button.onclick = function() { showSlides(1); };
		button.innerHTML = "Slideshow";
		return;
	}
	if (show == 1) {
		var button = document.getElementById("slidebutton");
		button.onclick = function() { showSlides(0); };
		button.innerHTML = "Stop";
	}
  var i;
  var slides = document.getElementsByClassName("mySlides");
  for (i = 0; i < slides.length; i++) {
    slides[i].style.display = "none";
  }
  slideIndex++;
  if (slideIndex > slides.length) {slideIndex = 1}
  slides[slideIndex-1].style.display = "block";
  setTimeout(function(){ showSlides(); }, 500); // Change image every x milliseconds
}
</script>

<hr>

<?php include 'footer.php';?>

</body>
</html>
