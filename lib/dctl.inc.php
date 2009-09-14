<?php
require_once(dirname(__FILE__) . '/dctlSession.class.php');
require_once(dirname(__FILE__) . '/dctlMessage.class.php');

function dctl_udate($format, $utimestamp = null)
{
  if(is_null($utimestamp))
    $utimestamp = microtime(true);

  $timestamp = floor($utimestamp);
  $milliseconds = round(($utimestamp - $timestamp) * 1000000);

  return date(preg_replace('`(?<!\\\\)u`', $milliseconds, $format), $timestamp);
} 

function dctl_log($str)
{
  echo dctl_udate("H:i:s.u") . " : $str\n";
}

function dctl_hex_stream_to_bytes($stream)
{
  return pack('H*', $stream);
}

function dctl_autoguess_host()
{
  exec('ipconfig', $out, $ret);

  foreach($out as $line)
    if(preg_match('~\s+IP-.*:\s+(\d+\.\d+\.\d+\.\d+)~', $line, $m))
      return $m[1];

  return "127.0.0.1";
}

function dctl_next_dividable($num, $divider)
{
  return (floor(($num - 1) / $divider) + 1) * $divider;
} 

class DCTL
{
  private $host;
  private $port;
  private $timeout;
  private $session;

  function __construct($port = 1626, $host = null, $timeout = 300)
  {
    if(!$host)
      $host = dctl_autoguess_host();

    $this->host = $host;
    $this->port = $port;
    $this->timeout = $timeout;
  }

  function run($task_name, $task_args = array())
  {
    $this->_ensureConnection();

    $task = new dctlTask($task_name, $task_args);

    dctl_log("Running task '" . $task->name . "' [" . implode(',', $task->args) . "]");

    $bench = microtime(true);
    $this->session->write($task->makePacket());
    $reply = new dctlTaskReply(dctlMessage::readFromSession($this->session, /*raw*/true));

    dctl_log("Task '" . $task->name . "' executed(" .  round(microtime(true)-$bench, 2) . " sec) " .  $reply->error);

    //sending raw reply to stderr so that it can be parsed
    $reply_string = implode("\n", $reply->args);
    if($reply_string)
    {
      $reply_string = "-------------------------\n$reply_string";
      fwrite(STDERR, "$reply_string\n");
    }

    return ($reply->error == "OK" ? 0 : 1);
  }

  private function _ensureConnection()
  {
    if(is_object($this->session))
      return;

    $this->session = dctlSession::create($this->port, $this->host);
    $this->session->setSocketRcvTimeout($this->timeout);//timeout in seconds, TODO: make some sort of pong message in dctl

    $this->session->write(self::_makeLogonPacket()); 
    $msg = dctlMessage::readFromSession($this->session, /*raw*/true);

    //if there is an error try to login one more time
    if($msg->getSubject() != "Logon")
    {
      $this->session->write(self::_makeLogonPacket()); 
      $msg = dctlMessage::readFromSession($this->session, /*raw*/true);
    }

    if($msg->getSubject() != "Logon")
      throw new Exception("Could not logon onto Director server '{$this->host}:{$this->port}'");

    dctl_log("Logged onto Director server '{$this->host}:{$this->port}' OK");
  }

  private static function _makeLogonPacket()
  {
    $out = new dctlByteBuffer();
    $out->addUint32N(0); //error
    $out->addUint32N(0); //timestamp
    $out->addPaddedStringN("Logon");//subject
    $out->addPaddedStringN("job");//sender
    $out->addUint32N(1);
    $out->addPaddedStringN("System");//recepients

    //blowfished content: #userID: "myname2", #password: "password", #movieID: "simpleMovie"
    //TODO: find a way to make it dynamically
    $encrypted = "8cb061ca1153a057f86cd8c7124795590fbf24033c764a5ac6a43" . 
                "436d3dd2c6c64089e67ce3cf16efd0ba9817354a3862a49ae9b";
    $out->addBytes(dctl_hex_stream_to_bytes($encrypted));

    $header = new dctlByteBuffer();
    $header->addBytes("r\x00");
    $header->addUint32N($out->getSize());

    return $header->getBytes() . $out->getBytes();
  }
}

class dctlTask
{
  public $name;
  public $args = array();

  function __construct($name, $args = array())
  {
    $this->name = $name;
    $this->args = $args;
  }

  function makePacket()
  {
    $out = new dctlByteBuffer();
    $out->addUint32N(0); //error
    $out->addUint32N(0); //timestamp
    //encoding task in a subject for now since the contents is blowfished :(
    $out->addPaddedStringN("TASK");//subject
    $out->addPaddedStringN("myname2");//sender
    $out->addUint32N(1);
    $out->addPaddedStringN(" ");//recepients

    $out->addUint16N(NET_VALUE_TYPE_LIST);
    //+1 is for task name
    $out->addUint32N(count($this->args) + 1);
    $out->addUint16N(NET_VALUE_TYPE_STRING);
    $out->addPaddedStringN($this->name);
    foreach($this->args as $arg)
    {
      $out->addUint16N(NET_VALUE_TYPE_STRING);
      $out->addPaddedStringN($arg);
    }

    $header = new dctlByteBuffer();
    $header->addBytes("r\x00");
    $header->addUint32N($out->getSize());

    return $header->getBytes() . $out->getBytes();
  }
}

class dctlTaskReply
{
  public $error;
  public $args = array();

  function __construct(dctlMessage $msg)
  {
    $this->error = $msg->getSubject();

    $type = $msg->extractUint16N();
    if($type != NET_VALUE_TYPE_LIST)
      throw new Exception("Content is not a list");
    $list_size = $msg->extractUint32N();
    for($i=0;$i<$list_size;++$i)
    {
      $type = $msg->extractUint16N();
      if($type != NET_VALUE_TYPE_STRING)
        throw new Exception("Content item at '$i' is not a string");

      $this->args[] = $msg->extractPaddedStringN();
    }
  }
}
