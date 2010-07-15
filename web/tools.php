<?php

function graph_using_css ( $array ) {

  print "<table>";
  
  $max = 0;
  
  # Find the max value
  foreach ( $array as $key => $value ) {
  
    if ( $value > $max ) 
      $max = $value;
  
  }

  foreach ( $array as $key => $value ) {
  
    $pct = ( $value / $max ) * 100.0;
    
    print "<tr><td class=smallfont>" . $key . "</td>" .
      '</td><td><div class="progress-container"><div style="width: ' . $pct .  '%"></div></div></td>
      <td class=smallfont>' . $value . "</td></tr>";

  }

  print "</table>";
  
}

?>