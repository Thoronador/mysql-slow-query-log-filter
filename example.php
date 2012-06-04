<?php

/********
 * This file contains two rather simple examples of how to use the splitLog.php
 * script.
 */


/**** First example
 *
 * This example tries to extract the log entries of the user foo[bar] from the
 * slow query log and writes those entries to the file /temp/foobar.log.
 */

  //include splitLog.php - required
  require_once('splitLog.php');

  //calls the main function
  $result = splitLog('/path/to/slow_query.log', '/tmp/foobar.log', 'foo[bar]');
  //check return value - zero or SPLIT_ERROR_NONE means no error
  if ($result===SPLIT_ERROR_NONE)
  {
    echo 'All went fine, as expected! New file was created.';
  }
  else
  {
    //put error code to standard output
    echo 'An error occured. Error code: '.$result."\n";
    //... and get a brief string describing the error
    echo 'Error description: '.errorCodeToString($result);
  }




/**** Second example
 *
 * This example tries to get the number of log entries for each user in the
 * slow query log and echoes those statistics.
 */

  //include splitLog.php - required (unless included before)
  require_once('splitLog.php');

  //get statistics of slow query log
  $stat = getLogUserStatistics('/path/to/slow_query.log');
  //check return value's type - it is integer in case of failure and array in
  // case of success
  if (!is_int($stat))
  {
    echo "All went fine!\nUser statistics are as follows:\n";
    //loop through all array elements
    foreach ($stat as $key => $value)
    {
      // array entries:
      // key is the user name (string) and value is the number of entries (int)
      echo 'User "'.$key .'": '.$value." entries\n";
    }//foreach
  }
  else
  {
    //put error code to standard output
    echo 'An error occured within the statistics function. Error code: '.$result."\n";
    //... and get a brief string describing the error
    echo 'Error description: '.errorCodeToString($stat);
  }

?>
