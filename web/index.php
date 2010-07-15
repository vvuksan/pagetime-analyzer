<html>
<head>
<title>Page Analysis</title>
<link rel="stylesheet" href="css/jq.css" type="text/css" media="print, projection, screen" /> 
<link rel="stylesheet" href="css/style.css" type="text/css" id="" media="print, projection, screen" /> 
<script language="javascript" type="text/javascript" src="js/jquery.min.js"></script> 
<script type="text/javascript" src="js/jquery.tablesorter.min.js"></script> 
<style>
p.nodata {
  background-color: #AADDDD; 
  font-size: 16px;
  height: 24px;
}
</style>

</head>
<body>

<?php

error_reporting(E_ALL);

# Limit script runtime to 5 minutes
set_time_limit(300);

require_once("./config.default.php");
# If there are any overrides include them now
if ( ! is_readable('./config.php') ) {
    echo("<H2>WARNING: Configuration file config.php does not exist. Please
         notify your system administrator.</H2>");
} else
    include_once('./config.php');

######################################################
# Connect to memcache
######################################################
$memcache = memcache_pconnect($memcache_server, $memcache_port);

if ($memcache) {

  if ( ! isset($_GET['datetime']) ) {
    $datetime = date('YmdH');
    $unixtime = time() - 7200;
  } else {
    $datetime = $_GET['datetime'];
    $unixtime = strtotime($datetime . "00");
  }

$formatted_date = date('m/d/Y H', $unixtime);
$time_prev = $unixtime - 3600;
$prev_timeperiod = date('YmdH', $time_prev);
$time_after = $unixtime + 3600;
$next_timeperiod = date('YmdH', $time_after);    

?>
Shows only GET requests with more than <?php print $minimum_hits_for_display; ?> requests.

<center>Time period <?php print $formatted_date . ":00:00 to " . $formatted_date . ":59:59"; ?> </center><p>

<center><a href=?datetime=<?php print $prev_timeperiod; ?>><----</a> Jump to 
<a href=?datetime=<?php print $next_timeperiod; ?>>----></a></center>

<?php
  #######################################################################################
  # First let's see if we have already computed this data
  $mc_key = $ALL_STATS_MC_PREFIX . $datetime;
  
  $allstats_string = $memcache->get($mc_key);

  # It's there
  if ( $debug == 0 && $allstats_string !== false ) {

?>
  <table border=1 cellspacing="1" class="tablesorter">
  <thead>
  <tr><th>URL</th><th>Total requests</th><th>Total time in seconds</th><th>90th pct req time (seconds)</th><th>Avg req time (seconds)</th></tr>
  </thead>
  <tbody>
<?php

    $all_stats = unserialize($allstats_string);
     foreach ( $all_stats as $hash => $stats ) {
    
      $url_total_time["total"][$hash] = $all_stats[$hash]["total_time"];
      $url_num_req["total"][$hash] = $all_stats[$hash]["num_requests"];
      $ninetieth_response_time[$hash] = $all_stats[$hash]["ninetieth"];
      $url_array[$hash] = $all_stats[$hash]["url"];
    
    }
  
    ####################################################################
    # Print out the data
    ####################################################################
    foreach ( $url_num_req["total"] as $hash => $num_requests ) {

      # Ignore any URLs with less than a minimum hits
      if ( $num_requests > $minimum_hits_for_display ) {

	$average_req_time = $url_total_time["total"][$hash] / $num_requests;
	
	print "<tr><td><a href=url_detail.php?datetime=" .$datetime."&hash=" . $hash . ">" . $url_array[$hash] . "</a>&nbsp;" .
	"<a href=graph.php?datetime=" .$datetime."&hash=" . $hash . "><img width=15 height=15 src=graph_icon.png></a>" .
	  "</td><td align=right>" . $num_requests  . "</td><td align=right>" . 
	  number_format($url_total_time["total"][$hash], 2, ".", "") . 
	  "</td><td align=right>" . number_format($ninetieth_response_time[$hash],4) . "</td><td align=right>" . number_format($average_req_time,4) . "</td></tr>";

	if ( $write_allstats_to_memcache == 1 ) {
	  $all_stats[$hash] = array( "url" => $url_array[$hash],
	      "num_requests" => $num_requests,
	      "total_time" => $url_total_time["total"][$hash],
	      "ninetieth" => $ninetieth_response_time[$hash],
	      "average_time" => $average_req_time
	  );
	}


      } // end of if ( $num_requests > $minimum_hits_for_display )
      
    } // end of foreach ( $url_num_req["total"]

    print "</tbody></table>";

  } else {
    print "<p class=nodata>No data for this time period</p>";
  }


} else {
  print "Connection to memcached failed";
}


?>
<script type="text/javascript" id="js">
    $(document).ready(function() {
        // call the tablesorter plugin
        $("table").tablesorter({sortList: [[2,1]]});
}); </script> 
</body>
</html>
