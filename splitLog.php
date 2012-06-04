<?php

/****************************************************************************

    This file is part of the slow query log filter.
    Copyright (C) 2012  Thoronador

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

  ***************************************************************************/


// error constants that can be returned by the functions
define('SPLIT_ERROR_NONE',          0);
define('SPLIT_ERROR_NOT_EXISTS',    1);
define('SPLIT_ERROR_NOT_A_FILE',    2);
define('SPLIT_ERROR_TARGET_EXISTS', 3);
define('SPLIT_ERROR_IO',            4);
define('SPLIT_ERROR_NOT_SQL',       5);
define('SPLIT_ERROR_NO_QUERY',      6);
define('SPLIT_ERROR_EOF',           7);

/* returns a brief description of the error code's meaning

   parameters:
       code - (int) the error code, usually one of the constants above
*/
function errorCodeToString($code)
{
  switch ((int)$code)
  {
    case SPLIT_ERROR_NONE:
         return 'No error.';
    case SPLIT_ERROR_NOT_EXISTS:
         return 'Log file does not exist.';
    case SPLIT_ERROR_NOT_A_FILE:
         return 'Given path does not point to a file.';
    case SPLIT_ERROR_TARGET_EXISTS:
         return 'File with target name already exists.';
    case SPLIT_ERROR_IO:
         return 'I/O error.';
    case SPLIT_ERROR_NOT_SQL:
         return 'File does not seem to be the slow query log.';
    case SPLIT_ERROR_NO_QUERY:
         return 'No query listed after statistic data.';
    case SPLIT_ERROR_EOF:
         return 'Unexpected end of file.';
    default:
         return 'Unknown error code ('.intval($code).')';
  }//swi
}//function errorCodeToString

//aux. function
function readQueryArray(&$file)
{
  $result = array();
  $nextLine = fgets($file);
  if ($nextLine===false)
  {
    //unexpected end of file or I/O error
    fclose($file);
    return SPLIT_ERROR_IO;
  }
  //should be Time line
  if (substr($nextLine, 0, 7)!=='# Time:')
  {
    fclose($file);
    return SPLIT_ERROR_NOT_SQL;
  }
  $result['time'] = $nextLine;
  //read user/host line
  $nextLine = fgets($file);
  if ($nextLine===false)
  {
    //unexpected end of file or I/O error
    fclose($file);
    return SPLIT_ERROR_IO;
  }
  //should be user/host line
  if (substr($nextLine, 0, 12)!=='# User@Host:')
  {
    fclose($file);
    return SPLIT_ERROR_NOT_SQL;
  }
  $result['user'] = $nextLine;
  //read stats line
  $nextLine = fgets($file);
  if ($nextLine===false)
  {
    //unexpected end of file or I/O error
    fclose($file);
    return SPLIT_ERROR_IO;
  }
  //should be stats line
  if (substr($nextLine, 0, 13)!=='# Query_time:')
  {
    fclose($file);
    return SPLIT_ERROR_NOT_SQL;
  }
  $result['stats'] = $nextLine;

  //prepare query lines
  $result['query'] = array();

  //read query lines
  $pos = ftell($file); //save position for later fseek()
  $go = true;
  while($go)
  {
    $nextLine = fgets($file);
    if ($nextLine===false)
    {
      //end of file or I/O error
      if (feof($file))
      {
        return $result;
      }
      fclose($file);
      return SPLIT_ERROR_IO;
    }
    if (substr($nextLine, 0, 2)!=='# ')
    {
      $result['query'][] = $nextLine;
      $pos = ftell($file);
    }
    else
    {
      $go = false;
      fseek($file, $pos); //skip back to start of current line
    }
  }//while
  if (!empty($result['query'])) return $result;
  //empty query - should not happen
  return SPLIT_ERROR_NO_QUERY;
}//function readQueryArray

/* tries to get the user name from the given string, which has to be the line
   starting with "# User@Host: ..." from the slow query log. Returns a string
   containing the user name in case of success; returns false in case of
   failure.

   parameters:
       userLine - (string) a line starting with "# User@Host: ..." from the log
*/
function getUserFromUserLine($userLine)
{
  if (substr($userLine, 0, 12)!=='# User@Host:')
  {
    return false;
  }
  $userLine = trim(substr($userLine, 12));
  $both = explode('@', $userLine);
  if (count($both)===2) return trim($both[0]);
  return false;
}//function getUserFromUserLine


/* "splits" a slow query log file by user, i.e. reads entries from origLog and
   only saves the entries matching the user $user to the file newLog. Returns
   zero in case of success or a non-zero int value in case of failure.

   parameters:
       origLog - (string) file name of the MySQL slow query log file
       newLog  - (string) name of the file where the matching log entries will
                 be saved
       user    - (string) name of the MySQL user that

   returns:
       Returns zero in case of success or a positive non-zero integer value
       indicating the type of error in case of failure.

   remarks:
       If the file at newLog already exists, the function will fail and return
       an error code, because we don't want to overwrite existing files.
*/
function splitLog($origLog, $newLog, $user)
{
  $origLog = (string) $origLog;
  $newLog = (string) $newLog;
  $user = trim((string) $user);

  //check existence
  if (file_exists($origLog)===false)
  {
    return SPLIT_ERROR_NOT_EXISTS;
  }
  //only real files, no directories
  if (is_file($origLog)===false)
  {
    return SPLIT_ERROR_NOT_A_FILE;
  }
  //we don't want to overwrite existing target files...
  if (file_exists($newLog)===true)
  {
    return SPLIT_ERROR_TARGET_EXISTS;
  }

  //open slow query log file
  $file = fopen($origLog, 'rb');
  if ($file===false)
  {
    return SPLIT_ERROR_IO;
  }
  // ---- read starting lines
  $nextLine = fgets($file);
  if ($nextLine===false)
  {
    //unexpected end of file or I/O error
    fclose($file);
    return SPLIT_ERROR_IO;
  }
  //should be starting line
  if (substr($nextLine, 0, 25)!=='/usr/sbin/mysqld, Version')
  {
    fclose($file);
    return SPLIT_ERROR_NOT_SQL;
  }
  //read tcp line
  $nextLine = fgets($file);
  if ($nextLine===false)
  {
    //unexpected end of file or I/O error
    fclose($file);
    return SPLIT_ERROR_IO;
  }
  //should be tcp line
  if (substr($nextLine, 0, 9)!=='Tcp port:')
  {
    fclose($file);
    return SPLIT_ERROR_NOT_SQL;
  }
  // read Time line
  $nextLine = fgets($file);
  if ($nextLine===false)
  {
    //unexpected end of file or I/O error
    fclose($file);
    return SPLIT_ERROR_IO;
  }
  //should be Time line
  if (substr($nextLine, 0, 5)!=='Time ')
  {
    fclose($file);
    return SPLIT_ERROR_NOT_SQL;
  }

  //prepare output file
  $target = fopen($newLog, 'wb');
  if ($target===false)
  {
    fclose($file);
    return SPLIT_ERROR_IO;
  }

  //now read the real log lines
  do
  {
    $data = readQueryArray($file);
    if (is_int($data))
    {
      fclose($file);
      fclose($target);
      return $data;
    }//if int
    else
    {
      //check user
      if (getUserFromUserLine($data['user'])===$user)
      {
        //write to target
        fwrite($target, $data['time']);
        fwrite($target, $data['user']);
        fwrite($target, $data['stats']);
        foreach($data['query'] as $ql)
        {
          fwrite($target, $ql);
        }//foreach
      }//if
    }//else - it's an array
  } while (!feof($file) && is_array($data));

  fclose($target);
  fclose($file);
  if (is_int($data))
  {
    //error occured in do-while-loop
    return $data;
  }
  //no error
  return SPLIT_ERROR_NONE;
}//function splitLog

?>
