<html>
<head>
<script type="text/javascript" src="js/swfobject.js"></script>
</head>
<body>

Shows response times for a particular day.

<?php

$unixtime = strtotime($_GET['datetime'] . "00");

$time_prev = $unixtime - 86400;
$prev_timeperiod = date('YmdH', $time_prev);
$time_after = $unixtime + 86400;
$next_timeperiod = date('YmdH', $time_after);    

?>

<center><a href=?datetime=<?php print $prev_timeperiod . "&hash=" . $_GET['hash'] ; ?>><----</a> Jump to  
<a href=?datetime=<?php print $next_timeperiod. "&hash=" . $_GET['hash']; ?>>----></a></center>

<p>

<script type="text/javascript">
 
swfobject.embedSWF(
  "open-flash-chart.swf", "chart_response_times",
  "700", "300", "9.0.0", "expressInstall.swf",
  {"data-file":"graph_json.php?data=<?php print $_GET['datetime'] . '_' . $_GET['hash']; ?>"} );
 
</script>

<script type="text/javascript">
 
swfobject.embedSWF(
  "open-flash-chart.swf", "chart_num_requests",
  "700", "300", "9.0.0", "expressInstall.swf",
  {"data-file":"graph_json.php?data=<?php print $_GET['datetime'] . '_' . $_GET['hash'] . '_numreqs' ; ?>"} );
</script>


<div id="chart_response_times"></div>
<div id="chart_num_requests"></div>

</body>
</html>