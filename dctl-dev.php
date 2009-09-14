<?php
require_once(dirname(__FILE__) . '/lib/dctl.inc.php');

set_time_limit(0);

$HOST = null;
$PORT = 1626;
$TIMEOUT = 5*60;

if(!sizeof($argv))
  throw new Exception("Task argument is not set");

$task = null;
$task_args = array();

foreach(dctl_parse_argv($argv) as $key => $value)
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
  $HOST = dctl_autoguess_host();

$stdin = dctl_read_from_stdin();
//put stdin as a first arg
if($stdin)
  array_unshift($task_args, $stdin);

$TASK = new dctlTask($task, $task_args);

$DCTL = new DCTL($PORT, $HOST, $TIMEOUT);
exit($DCTL->run($task, $task_args));
