<?php

require_once("./config.default.php");
require_once("./tools.php");
# If there are any overrides include them now
if ( ! is_readable('./config.php') ) {
    echo("<H2>WARNING: Configuration file config.php does not exist. Please
         notify your system administrator.</H2>");
} else
    include_once('./config.php');

include 'php-ofc-library/open-flash-chart.php';

$chart = new open_flash_chart();

####################################################################################
# I have had issues passing multiple arguments via Open flash library ie. using
# graph_json.php?hash=bla&datetime=123245. It ignore anything after & thus
# a single variable
####################################################################################
$out = explode("_", $_GET['data']);

$date = substr($out[0], 0, 8);
$hash = $out[1];
if ( isset($out[2]) ) {
  $num_requests_report = 1;
} else {
  $num_requests_report = 0;
}

# Connect to memcache
$memcache = memcache_connect($memcache_server, $memcache_port);

$max_y = 0 ;

if ($memcache) {

  for ( $i = 0 ; $i < 24 ; $i++ ) {

    $datetime = $date . sprintf("%02d", $i);

    if ( $num_requests_report == 0 ) {

      $mc_key = $URL_NINETIETH_MC_PREFIX . $datetime . "-" . $hash . "-total";
      $ninetieth_time = $memcache->get($mc_key);
      if ( $ninetieth_time !== false ) {
	$values[0][$i] = (float) $ninetieth_time;
      }

      $mc_key = $URL_TOTAL_TIME_MC_PREFIX . $datetime . "-" . $hash . "-total";
      $total_time = $memcache->get($mc_key);
      
    }

    $mc_key = $URL_REQ_MC_PREFIX . $datetime . "-" . $hash . "-total";
    $num_requests = $memcache->get($mc_key);
    
    if ( $num_requests_report == 1 ) {

      $values[0][$i] = $num_requests;
      
    }

    if ( $num_requests !== false && $num_requests_report == 0 ) { 
      $values[1][$i] = $total_time / $num_requests;
    }


    if ( $values[0][$i] > $max_y ) 
      $max_y = $values[0][$i];

    if ( $num_requests_report == 0 ) {

      if ( $values[1][$i] > $max_y ) 
	$max_y = $values[1][$i];

    }


  }

}


$mc_key = "url-"  .$hash;
# get the actual URL.
$url = $memcache->get($mc_key);
      
$title = new title( "Report for " .$url . " on ". date("Y-m-d" ,strtotime($date)) );

$line_1 = new line();
$line_1->set_values( $values[0] );
if ( $num_requests_report == 1 ) {
  $line_1->set_text("Number of requests");
} else {
  $line_1->set_text("Ninetieth pct time");
}
$chart->add_element( $line_1 );

if ( $num_requests_report == 0 ) {
  $line_2 = new line();
  $line_2->set_values( $values[1] );
  $line_2->set_colour( '#DFC329' );
  $line_2->set_text("Average time");
  $chart->add_element( $line_2 );
}

$chart->set_title( $title );



//
// create an X Axis object
//
$x = new x_axis();
$x->set_steps( 1 );
//
// Add the X Axis object to the chart:
//
$chart->set_x_axis($x );

$y = new y_axis();
// grid steps:
$y->set_range( 0, $max_y * 1.05, $max_y / 10);
$chart->set_y_axis($y );

echo $chart->toPrettyString();

?>
