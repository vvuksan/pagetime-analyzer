<html>
<head>
<title>Page Analysis</title>
<link rel="stylesheet" href="css/jq.css" type="text/css" media="print, projection, screen" /> 
<link rel="stylesheet" href="css/style.css" type="text/css" id="" media="print, projection, screen" /> 
<script language="javascript" type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js"></script> 
<script type="text/javascript" src="js/jquery.tablesorter.min.js"></script> 
<style>
td.smallfont {
  font-size: 10px;
}

div.progress-container {
  border: 1px solid #ccc; 
  width: 500px; 
  margin: 1px 1px 1px 0; 
  padding: 0px; 
  float: left; 
  background: white;
}
div.progress-container > div {
  background-color: #ACE97C; 
  height: 10px
}
p.graphheading {
  background-color: #AADDAA;   
  font-size: 18px;
}
</style>
</head>
<body>
<?php

error_reporting(E_ALL);

require_once("./config.default.php");
require_once("./tools.php");
# If there are any overrides include them now
if ( ! is_readable('./config.php') ) {
    echo("<H2>WARNING: Configuration file config.php does not exist. Please
         notify your system administrator.</H2>");
} else
    include_once('./config.php');

$today = date('Ymd');

######################################################
# Connect to memcache
######################################################
$memcache = memcache_connect($memcache_server, $memcache_port);

if ($memcache) {

  $datetime = $_GET['datetime'];
  $hash = $_GET['hash'];

  $mc_key = "url-"  .$hash;
    # get the actual URL. 
  $url = $memcache->get($mc_key); 


?>
<h3>Showing results for <?php print $url; ?></h3><p>

  <table border=1 cellspacing="1" class="tablesorter">
  <thead>
  <tr><th>Server</th><th>Total requests</th><th>Total time in seconds</th><th>90th pct req time (seconds)</th><th>Avg req time (seconds)</th></tr>
  </thead>
  <tbody>

<?php

  $instances_key = 'instances';
  $instances_string = $memcache->get($instances_key);
  if ( strpos(",", $instances_string) !== FALSE ) {
    $instances[] = $instances_string;
  } else {
    $instances = explode(",", $instances_string);
  }
  
  
  asort($instances);

  # Now loop through all the instances
  foreach ( $instances as $key => $instance ) {
      
    # first check whether we have the data memcached
    $mc_key = $URL_REQ_MC_PREFIX . $datetime . "-" . $hash . "-" . $instance;
    
    $url_num_req = $memcache->get($mc_key);
    # Is it present
    if ( $url_num_req !== false ) {
      # Get the rest of the values
      $mc_key = $URL_TOTAL_TIME_MC_PREFIX . $datetime . "-" . $hash . "-" . $instance;
      $url_total_time = $memcache->get($mc_key);

      $mc_key = $URL_NINETIETH_MC_PREFIX . $datetime . "-" . $hash . "-" . $instance;
      $url_ninetieth_time = $memcache->get($mc_key);
      
      $avg_time = $url_total_time / $url_num_req;
      
      $graph_data_num_req[$instance] = $url_num_req;
      $graph_data_ninetieth_time[$instance] = number_format($url_ninetieth_time,4);
      $graph_data_average_time_time[$instance] = number_format($avg_time,4);
  
      print "<tr><td>". $instance . 
      "</a></td><td align=right>" . $url_num_req  . "</td><td align=right>" . 
      number_format($url_total_time, 2, ".", "") .       "</td><td align=right>" . 
      number_format($url_ninetieth_time,4) . "</td><td align=right>" . 
      number_format($avg_time,4) . "</td></tr>";

    } // end
  } 

} else {
  print "Connection to memcached failed";
}


?>
</table>
<script type="text/javascript" id="js">
    $(document).ready(function() {
        // call the tablesorter plugin
        $("table").tablesorter();
}); </script>
<hr>

<p class=graphheading>Number of Requests<p>
<?php
graph_using_css($graph_data_num_req);
?>

<hr>

<p class=graphheading>Ninetieth percentile time<p>
<?php
graph_using_css($graph_data_ninetieth_time);
?>
<hr>

<p class=graphheading>Average response time<p>
<?php
graph_using_css($graph_data_average_time_time);
?>


</body>
</html>