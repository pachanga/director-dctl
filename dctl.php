<?php
require_once(dirname(__FILE__) . '/../../../shared/lib/game/GameSession.class.php');
require_once(dirname(__FILE__) . '/../../../shared/lib/game/GameMessage.class.php');
require_once(dirname(__FILE__) . '/dctl.inc.php');

//{{{helper stuff

/**
* -e
* -e <value>
* --long-param
* --long-param=<value>
* --long-param <value>
* <value>
*/
function parse_argv($params, $noopt = array()) 
{
  $result = array();
  reset($params);
  while (list($tmp, $p) = each($params)) 
  {
    if($p{0} == '-') 
    {
      $pname = substr($p, 1);
      $value = true;
      if($pname{0} == '-') 
      {
        // long-opt (--<param>)
        $pname = substr($pname, 1);
        if(strpos($p, '=') !== false) 
        {
          // value specified inline (--<param>=<value>)
          list($pname, $value) = explode('=', substr($p, 2), 2);
        }
      }
      // check if next parameter is a descriptor or a value
      $nextparm = current($params);
      if(!in_array($pname, $noopt) && $value === true && $nextparm !== false && $nextparm{0} != '-') 
        list($tmp, $value) = each($params);
      $result[$pname] = $value;
    } 
    else
      // param doesn't belong to any option
      $result[] = $p;
  }
  return $result;
}

function read_from_stdin()
{
  $read   = array(STDIN);
  $write  = NULL;
  $except = NULL;
  $stdin = '';
  if(false === ($num_changed_streams = stream_select($read, $write, $except, 0))) 
    throw new Exception("Unknown stream select error happened");
  elseif ($num_changed_streams > 0) 
    $stdin = stream_get_contents(STDIN);
  return $stdin;
}
//}}}

set_time_limit(0);

$HOST = null;
$PORT = 1626;
$TIMEOUT = 5*60;

if(!sizeof($argv))
  throw new Exception("Task argument is not set");

$task = null;
$task_args = array();

foreach(parse_argv($argv) as $key => $value)
{
  if(is_numeric($key))
  {
    if($key > 0)
    {
      if($key == 1)
        $task = $value;
      else
        $task_args[] = $value;
    }
  }
  else
  {
    switch($key)
    {
      case 'h':
      case 'host':
        $HOST = $value;
      break;

      case 'p':
      case 'port':
        $PORT = $value;
      break;

      case 'timeout':
        $TIMEOUT = $value;
      break;
    }
  }
}

if(!$task)
  throw new Exception("Task name is not set");

if(is_null($HOST))
  $HOST = autoguess_host();

$stdin = read_from_stdin();
//put stdin as a first arg
if($stdin)
  array_unshift($task_args, $stdin);

$TASK = new DirectorTask($task, $task_args);

$DCTL = new DCTL($PORT, $HOST, $TIMEOUT);
exit($DCTL->run($task, $task_args));
