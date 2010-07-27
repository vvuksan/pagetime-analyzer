<?php

error_reporting(E_ALL);

set_time_limit(3600);

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
$memcache = memcache_connect($memcache_server, $memcache_port);

if ($memcache) {

  if ( ! isset($_GET['secret']) ) {
    print "You can't just invoke batch_analyzer just like that :-).";
    exit(1);
  }
  
  #############################################################################
  # Get a list of instances since we store URL durations for instances
  # in separate keys
  #############################################################################
  $instances_key = 'instances';
  $instances_string = $memcache->get($instances_key);
  if ( strpos(",", $instances_string) !== FALSE ) {
    $instances[] = $instances_string;
  } else {
    $instances = explode(",", $instances_string);
  }
  
  asort($instances);

  # If datetime is supplied use that one only
  if ( isset($_GET['datetime']) ) {
  
    $date_time_list = $_GET['datetime']; 
    
  } else {

    $unprocessed_datetime_key = "unprocessed_date_time";
    $date_time_list = $memcache->get($unprocessed_datetime_key);
    
  }

  if ( $debug == 0 && $date_time_list !== false ) {
  
    if ( strpos(",", $date_time_list) !== FALSE ) {
      $datetime_array[] = $date_time_list;
    } else {
      $datetime_array = explode(",", $date_time_list);
    }
    
    # Delete the key so someone else doesn't attempt to process the datetimes
    $memcache->delete($unprocessed_datetime_key,0);

    
    foreach ( $datetime_array as $key => $datetime ) {
  
      #######################################################################################
      # First let's see if we have already computed this data
      $mc_key = $ALL_STATS_MC_PREFIX . $datetime;
      
      $allstats_string = $memcache->get($mc_key);

      # It's there
      if ( $debug == 0 && $allstats_string !== false ) {

	print "We have already computed data for " . $datetime . ". Skipping.\n";
	ob_flush();
	
      } else {
      
	print "Computing stats for " . $datetime . "\n";
	ob_flush();
	$start_time = time();

	$url_dur_array = array();
	$url_num_req = array();
	$url_total_time = array();

	#############################################################################
	# Get a list of URL hashes we have seen this time period
	#############################################################################
	$url_hashes_key = 'urllist-' . $datetime;
	$url_hashes = $memcache->get($url_hashes_key);
	$url_hashes_array = explode(",", $url_hashes);

	#############################################################################
	# Loop through all URL hashes
	foreach ( $url_hashes_array as $key2 => $hash ) {

	  # Find what URL maps to this hash
	  $mc_key = "url-"  .$hash;
	  # get the actual URL.
	  $url = $memcache->get($mc_key);
	  $url_array[$hash] = $url;

	  # Did we already calculate stats for this URL
	  $mc_key = $URL_REQ_MC_PREFIX . $datetime . "-" . $hash . "-total";
	  $url_num_req["total"][$hash] = $memcache->get($mc_key);

	  if ( $url_num_req["total"][$hash] !== false ) {
	
	    $mc_key = $URL_TOTAL_TIME_MC_PREFIX . $datetime . "-" . $hash . "-total";
	    $url_total_time["total"][$hash] = $memcache->get($mc_key);
	    
	    $mc_key = $URL_NINETIETH_MC_PREFIX. $datetime . "-" . $hash . "-total";
	    $ninetieth_response_time[$hash] = $memcache->get($mc_key);

	  # We didn't
	  } else {

	    # We don't want to analyze static URLs so we'll just look at URLs that contain
	    # following patterns
	    if ( preg_match($valid_url_patterns, $url ) ) {

	      # Reset the hash totals
	      $url_num_req["total"][$hash] = 0;
	      $url_total_time["total"][$hash] = 0;
	      $url_all_requests = array();
	      
	      # Now loop through all the instances
	      foreach ( $instances as $key => $instance ) {
	      
		# first check whether we have the data memcached
		$mc_key = $URL_REQ_MC_PREFIX . $datetime . "-" . $hash . "-" . $instance;
		
		$url_num_req[$hash][$instance] = $memcache->get($mc_key);
		# Is it present
		if ( $debug == 0 && $url_num_req[$hash][$instance] !== false ) {
		  # Get the rest of the values
		  $mc_key = $URL_TOTAL_TIME_MC_PREFIX . $datetime . "-" . $hash . "-" . $instance;
		  $url_total_time[$hash][$instance] = $memcache->get($mc_key);

		  $mc_key = $URL_NINETIETH_MC_PREFIX . $datetime . "-" . $hash . "-" . $instance;
		  $url_ninetieth_time[$hash][$instance] = $memcache->get($mc_key);
	      
		} else {
	      
		  # Fetch all the URL durations.
		  $mc_key = "urldur-" . $instance . "-" . $datetime . "-" . $hash;
		  $url_duration_string = $memcache->get($mc_key);      

		  if ( $url_duration_string !== false ) {
		  
		    # Explode the url_duration into a string
		    $url_dur_array = explode(",", $url_duration_string);
		    #
		    $url_num_req[$hash][$instance] = sizeof($url_dur_array);
		    $mc_key = $URL_REQ_MC_PREFIX . $datetime . "-" . $hash . "-" . $instance;
		    $memcache->set($mc_key, $url_num_req[$hash][$instance] , 0, 0);
		    
		    # Total time
		    $url_total_time[$hash][$instance] = array_sum($url_dur_array);
		    $mc_key = $URL_TOTAL_TIME_MC_PREFIX . $datetime . "-" . $hash . "-" . $instance;
		    $memcache->set($mc_key, $url_total_time[$hash][$instance] , 0, 0);

		    # Sort response time for a particular URL
		    asort($url_dur_array);
		    # Get the ninetieth percentile
		    $ninetieth = floor($url_num_req[$hash][$instance] * 0.9); 
		    $url_ninetieth_time[$hash][$instance] = $url_dur_array[$ninetieth];
		    $mc_key = $URL_NINETIETH_MC_PREFIX. $datetime . "-" . $hash . "-" . $instance;
		    $memcache->set($mc_key, $url_ninetieth_time[$hash][$instance] , 0, 0);
		
		    $url_all_requests = array_merge($url_all_requests, $url_dur_array);
		    # Free up memory
		    unset($url_dur_array);
		  }  // end if ( $url_duration_string
		  
		} // end of if ( $url_num_req[$hash][$instance] !== false ) 


	      } // end of foreach ($instance
	      
	      ########################################################################################
	      # Sum number requests across all nodes/hosts
	      $url_num_req["total"][$hash] = array_sum($url_num_req[$hash]);
	      $mc_key = $URL_REQ_MC_PREFIX . $datetime . "-" . $hash . "-total";
	      $memcache->set($mc_key, $url_num_req["total"][$hash], 0, 0);
	      
	      # Total time
	      $url_total_time["total"][$hash] = array_sum($url_total_time[$hash]);
	      $mc_key = $URL_TOTAL_TIME_MC_PREFIX . $datetime . "-" . $hash . "-total";
	      $memcache->set($mc_key, $url_total_time["total"][$hash] , 0, 0);
	      
	      # Calculate the 90th percentile response time
	      $ninetieth = floor($url_num_req["total"][$hash] * 0.9); 
	      $ninetieth_response_time[$hash] = $url_all_requests[$ninetieth];
	      $mc_key = $URL_NINETIETH_MC_PREFIX. $datetime . "-" . $hash . "-total";
	      $memcache->set($mc_key, $ninetieth_response_time[$hash] , 0, 0);

	      # Free up memory since url_all_requests is potentially huge
	      unset($url_all_requests);
	      
	      
		  
	      } // end of preg_match
	      
	    }

	  } // end of foreach ( $url_hashes_array


	# Sort desc by number of requests
	arsort($url_num_req["total"]);

      } // end of elseif ( $debug == 0 && $allstats_string !== false ) {

      ####################################################################
      # Print out the data
      ####################################################################
      foreach ( $url_num_req["total"] as $hash => $num_requests ) {

	# Ignore any URLs with less than a minimum hits
	if ( $num_requests > $minimum_hits_for_display ) {

	  $average_req_time = $url_total_time["total"][$hash] / $num_requests;
	  
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

      if ( $write_allstats_to_memcache == 1 && isset($all_stats) ) {

	# We want to save the computed data so we don't have to recalculate it later
	$mc_key = $ALL_STATS_MC_PREFIX . $datetime;
	$all_stats_string = serialize($all_stats);
	$memcache->set($mc_key, $all_stats_string , 0, 0);

      } else {
	print "Not writing data for " . $datetime;
      }
      
      $run_time = time() - $start_time;
      print "Total time to computer stats for " . $datetime . " is " . $run_time . " seconds\n";
      
      
    } // end of foreach ( $datetime_array as $key => $datetime
    
  } // end of if ( $debug == 0 && $datetime_list 

} else {
  print "Connection to memcached failed";
}


?>
