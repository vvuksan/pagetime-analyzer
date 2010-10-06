<html>
<head>
 <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Page Time Analyzer</title>
    <link href="layout.css" rel="stylesheet" type="text/css"></link>
    <!--[if IE]><script language="javascript" type="text/javascript" src="js/excanvas.min.js"></script><![endif]-->
    <script language="javascript" type="text/javascript" src="js/jquery.min.js"></script>
    <script language="javascript" type="text/javascript" src="js/jquery.flot.js"></script>
 </head>
<body>

Shows response times for a particular day.

<?php

require_once("./config.default.php");
require_once("./tools.php");
# If there are any overrides include them now
if ( ! is_readable('./config.php') ) {
    echo("<H2>WARNING: Configuration file config.php does not exist. Please
         notify your system administrator.</H2>");
} else
    include_once('./config.php');

$unixtime = strtotime($_GET['datetime'] . "0000");
$date = $_GET['datetime'];
$hash = $_GET['hash'];

$time_prev = $unixtime - 86400;

$prev_timeperiod = date('Ymd', $time_prev);
$time_after = $unixtime + 86400;
$next_timeperiod = date('Ymd', $time_after);    

# Connect to memcache
$memcache = memcache_connect($memcache_server, $memcache_port);

if ($memcache) {

  # Loop through hours
  for ( $i = 0 ; $i < 24 ; $i++ ) {

    # Create MC key suffix
    $datetime = $date . sprintf("%02d", $i);
    
    $mc_key = $URL_NINETIETH_MC_PREFIX . $datetime . "-" . $hash . "-total";
    $nine_time = $memcache->get($mc_key);
    if ( $nine_time !== false ) {
      $nine_time = (float) $nine_time;
      $ninetieth_time[] = "[" .  $i . ", " . $nine_time . "]";
      
      # We assume that
      $mc_key = $URL_TOTAL_TIME_MC_PREFIX . $datetime . "-" . $hash . "-total";
      $total_time[$i] = $memcache->get($mc_key);
	
      $mc_key = $URL_REQ_MC_PREFIX . $datetime . "-" . $hash . "-total";
      $num_requests[$i] = $memcache->get($mc_key);
      
      $avg_time[] = "[" .  $i . ", " . $total_time[$i] / $num_requests[$i] . "]";

      $num_requests_json[] = "[" .  $i . ", " . $num_requests[$i] . "]";

    }
  }
}
?>

<center><a href=?datetime=<?php print $prev_timeperiod . "&hash=" . $_GET['hash'] ; ?>><----</a> Jump to  
<a href=?datetime=<?php print $next_timeperiod. "&hash=" . $_GET['hash']; ?>>----></a></center>

<p>
<?php
$mc_key = "url-"  .$hash;
# get the actual URL.
$url = $memcache->get($mc_key);
      
print "<div id=title><center><h2>Report for " .$url . " for ". date("Y-m-d" ,strtotime($date)) . "</h2></center></div>";
?>
<p>
<div id="graph_times" style="width:800px;height:400px;"></div>
<script id="source" language="javascript" type="text/javascript">

 var options = {
        series: {
            lines: { show: true },
            points: { show: true }
        },
        legend: { noColumns: 2 },
        xaxis: { tickDecimals: 0 },
        yaxis: { min: 0 },
        selection: { mode: "x" }
    };


$(function () {

    var line_90th = [ <?php  print join(",", $ninetieth_time);  ?> ];

    // a null signifies separate line segments
    var line_avg = [<?php  print join(",", $avg_time);  ?>];
    
    $.plot($("#graph_times"), [ 
      { label: "90th percentile time", data: line_90th}, 
      { label: "Average time", data: line_avg} ], 
      options);
});
</script>
</div>

<p>

<div id="graph_requests" style="width:800px;height:400px;"></div>
<script id="source" language="javascript" type="text/javascript">
$(function () {

    var num_requests = [ <?php  print join(",", $num_requests_json);  ?> ];
    
    $.plot($("#graph_requests"), [ 
      { label: "Number requests", data: num_requests} ],    
     options
      );
});
</script>
</div>



</body>
</html>