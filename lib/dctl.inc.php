<?php

define('DCTL_NET_VALUE_TYPE_NUMBER', 1);
define('DCTL_NET_VALUE_TYPE_STRING', 3);
define('DCTL_NET_VALUE_TYPE_LIST',   7);

class dctlByteBuffer
{
  protected $bytes;
  protected $cursor;

  function __construct($bytes = '')
  {
    $this->bytes = $bytes;
    $this->cursor = $bytes;
  }

  function getBytes()
  {
    return $this->bytes;
  }

  function getCursor()
  {
    return $this->cursor;
  }

  function dump($print_ascii = false)
  {
    $this->_dump($this->bytes, $print_ascii);
  }

  function getSize()
  {
    return strlen($this->bytes);
  }

  function addBytes($bytes)
  {
    $this->bytes .= $bytes;
  }

  function addString($string)
  {
    $this->bytes .= pack('A' . (strlen($string)) . 'x', $string);
  }

  function addPaddedString($string, $network = false)
  {
    if($network)
      $this->addUint32N(strlen($string));
    else
      $this->addUint32(strlen($string));

    $this->bytes .= pack('a' . dctl_next_dividable(strlen($string), 2), $string);
  }

  function addPaddedStringN($string)
  {
    $this->addPaddedString($string, true);
  }

  function addInt8($value)
  {
    $this->bytes .= pack('c', $value);
  }
  
  function addUint8($value)
  {
    $this->bytes .= pack('C', $value);
  }

  function addInt16($value)
  {
    $this->bytes .= pack('s', $value);
  }
 
  function addUint16($value)
  {
    $this->bytes .= pack('S', $value);
  }

  function addUint16N($value)
  {
    $this->bytes .= pack('n', $value);
  }

  function addInt32($value)
  {
    $this->bytes .= pack('l', $value);
  }

  function addInt32N($value)
  {
    $this->bytes .= pack('N', $value);
  }

  function addUint32($value)
  {
    $this->bytes .= pack('L', $value);
  }

  function addUint32N($value)
  {
    $this->bytes .= pack('N', $value);
  }

  function addFloat($value)
  {
    $this->bytes .= pack('l', (int)round($value*1000));
  }

  function extractString()
  {
    //TODO: there is some bug in the code(which is way faster) below
    //$null_pos = strpos($this->cursor, "\x00");
    //if($null_pos === false)
    //  throw new Exception("Could not extract string from the cursor");
    //$this->cursor = substr($this->cursor, $null_pos+1);
    //return substr($this->cursor, 0, $null_pos+1);
    
    $res = '';
    $null_found = false;
    while(true)
    {
      if(!$this->cursor)
        break;

      $byte = substr($this->cursor, 0, 1);
      $this->cursor = substr($this->cursor, 1);

      if(!ord($byte))
      {
        $null_found = true;
        break;
      }

      $res .= $byte;
    }

    if(!$null_found)
      throw new Exception("Could not extract string from the cursor");

    return $res;
  }

  function extractPaddedString($network = false)
  {
    $len = $network ? $this->extractUint32N() : $this->extractUint32();
    $str = $this->extractBytes(dctl_next_dividable($len, 2));
    return substr($str, 0, $len);
  }

  function extractPaddedStringN()
  {
    return $this->extractPaddedString(true);
  }

  function extractBytes($num)
  {
    $bytes = substr($this->cursor, 0, $num);
    $this->cursor = substr($this->cursor, $num);
    return $bytes;
  }

  function extractInt8()
  {
    $arr = @unpack('cv', $this->cursor);
    if($arr === false)
      throw new Exception("Could not unpack 1 byte from the cursor");
    $this->cursor = substr($this->cursor, 1);
    return $arr['v'];
  }
  
  function extractUint8()
  {
    $arr = @unpack('Cv', $this->cursor);
    if($arr === false)
      throw new Exception("Could not unpack 1 byte from the cursor");
    $this->cursor = substr($this->cursor, 1);
    return $arr['v'];
  }

  function extractInt16()
  {
    $arr = @unpack('sv', $this->cursor);
    if($arr === false)
      throw new Exception("Could not unpack 2 bytes from the cursor");
    $this->cursor = substr($this->cursor, 2);
    return $arr['v'];
  }
  
  function extractInt16N()
  {
    $arr = @unpack('nv', $this->cursor);
    if($arr === false)
      throw new Exception("Could not unpack 2 bytes from the cursor");
    $this->cursor = substr($this->cursor, 2);
    $tmp = unpack('sv', pack('S', $arr['v']));
    return $tmp['v'];
  }
  
  function extractUint16($network = false)
  {
    $arr = @unpack(($network ? 'n' : 'S') . 'v', $this->cursor);
    if($arr === false)
      throw new Exception("Could not unpack 2 bytes from the cursor");
    $this->cursor = substr($this->cursor, 2);
    return $arr['v'];
  }

  function extractUint16N()
  {
    return $this->extractUint16(true);
  }

  function extractInt32()
  {
    $arr = @unpack('lv', $this->cursor);
    if($arr === false)
      throw new Exception("Could not unpack 4 bytes from the cursor");
    $this->cursor = substr($this->cursor, 4);
    return $arr['v'];
  }
  
  function extractInt32N()
  {
    $arr = @unpack('Nv', $this->cursor);
    if($arr === false)
      throw new Exception("Could not unpack 4 bytes from the cursor");
    $this->cursor = substr($this->cursor, 4);
    $tmp = unpack('lv', pack('L', $arr['v']));
    return $tmp['v'];
  }
  
  function extractUint32($network = false)
  {
    $arr = @unpack(($network ? 'N' : 'L') . 'v', $this->cursor);
    if($arr === false)
      throw new Exception("Could not unpack 4 bytes from the cursor");
    $this->cursor = substr($this->cursor, 4);

    //fix for PHP unsupporting unsigned int32
    if($arr['v'] < 0)
      return sprintf('%u', $arr['v'])*1.0;
    else
      return $arr['v'];
  }

  function extractUint32N()
  {
    return $this->extractUint32(true);
  }

  function extractFloat($precision = 1)
  {
    $arr = @unpack('lv', $this->cursor);
    $this->cursor = substr($this->cursor, 4);
    
    if($precision)
      return round($arr['v']/1000, $precision);
    else
      return $arr['v']/1000;
  }

  function reset()
  {
    $this->cursor = $this->bytes;
  }

  protected function _dump($bytes, $print_ascii = false)
  {
    $len = strlen($bytes);
    $data = "Data: \n";
    for($i=0;$i<$len;$i++) 
    {
      $byte = substr($bytes, $i, 1);
      $data .= sprintf("\x%02x", ord($byte)); 
      //$data .= '(' . ord($byte) . ')';

      if($print_ascii)
        $data .= '(' . $byte . ')';
    }
    $data .= "\n(total $len bytes)";
    echo "\n";
    var_dump($data); 
    echo "\n";
  }

}

class dctlMessage extends dctlByteBuffer
{
  const HEADER_SIZE = 6;

  protected $error = 0;
  protected $timestamp = 0;
  protected $subject = " ";
  protected $sender = " ";
  protected $recipients = array(" ");

  protected $type;
  protected $total_top_fields = 0;

  function __construct($type_or_bytes, $total_top_fields = 0, $is_raw = false)
  {
    $this->total_top_fields = $total_top_fields;

    if(is_int($type_or_bytes))
    {
      $this->type = $type_or_bytes;
      parent :: __construct('');
    }
    elseif(is_string($type_or_bytes))
    {
      parent :: __construct($type_or_bytes);
      if($is_raw)
        $this->_extractSystemDataBasic();
      else
        $this->_extractSystemData();
      $this->bytes = $this->cursor;
    }
    else
      throw new Exception("Wrong constructor argument, can be an integer(type) or a string(array of bytes)");
  }

  function dump($print_ascii = false)
  {
    $bytes = $this->pack();
    $len = strlen($bytes);
    $data = 'Message type ' . $this->type . ", data: \n";
    for($i=0;$i<$len;$i++) 
    {
      $byte = substr($bytes, $i, 1);
      $data .= sprintf("\x%02x", ord($byte)); 
      //$data .= '(' . ord($byte) . ')';

      if($print_ascii)
        $data .= '(' . $byte . ')';
    }
    $data .= "\n(total $len bytes)";
    echo "\n";
    var_dump($data); 
    echo "\n";
  }

  function getType()
  {
    return $this->type;
  }

  function getError()
  {
    return $this->error;
  }

  function getTimeStamp()
  {
    return $this->timestamp;
  }

  function getSender()
  {
    return $this->sender;
  }

  function getSubject()
  {
    return $this->subject;
  }

  function getRecipients()
  {
    return $this->recipients;
  }

  function pack()
  {
    $copy = new dctlMessage($this->type);
    $copy->_addSystemData();
    $copy->addBytes($this->getBytes());
    $header = "r\x00" . pack('N', $copy->getSize());
    $bytes = $header . $copy->getBytes(); 
    //var_dump(decode_bytes($bytes));
    return $bytes;
  }

  static function unpack($bytes)
  {
    $header = substr($bytes, 0, self::HEADER_SIZE);
    $arr = unpack('vtag/Nsize', $header);

    if(!isset($arr['size']) || !isset($arr['tag']))
      throw new Exception("Header has invalid format");

    $size = $arr['size'];

    if(substr($header, 0, 2) != "r\x00")
      throw new Exception("Header tag is invalid:\n" . decode_bytes(substr($header, 0, 2)));

    return new dctlMessage(substr($bytes, self::HEADER_SIZE));
  }

  static function readFromSession($session, $raw = false)
  {
    $header = $session->read(self :: HEADER_SIZE);
    if($header === false)
      throw new Exception("Could not read header packet from socket");

    $arr = unpack('vtag/Nsize', $header);

    if(!isset($arr['size']) || !isset($arr['tag']))
      throw new Exception("Header has invalid format");

    if(substr($header, 0, 2) != "r\x00")
      throw new Exception("Header tag is invalid:\n" . decode_bytes(substr($header, 0, 2)));

    return new dctlMessage($session->read($arr['size']), 0, $raw);
  }

  protected function _extractSystemDataBasic()
  {
    //error
    $this->error = $this->extractUint32N();
    //stamp
    $this->timestamp = $this->extractUint32N();
    //subj
    $this->subject = $this->extractPaddedStringN();
    //sender
    $this->sender = $this->extractPaddedStringN();
    //recipients.num
    $num = $this->extractUint32N();
    for($i=0;$i<$num;$i++)
      $this->recipients[] = $this->extractPaddedStringN();
  }

  protected function _extractSystemData()
  {
    $this->_extractSystemDataBasic();
    
    //extract opening list contents
    $this->extractUint16N();
    $this->extractUint32N();

    //msg type
    $this->extractUint16N();
    $this->type = $this->extractUint32N();
  }

  protected function _addSystemDataBasic()
  {
    //error
    $this->addUint32N($this->error);
    //stamp
    $this->addUint32N($this->timestamp);
    //subj
    $this->addPaddedStringN($this->subject);
    //sender
    $this->addPaddedStringN($this->sender);
    //recipients
    $this->addUint32N(count($this->recipients));
    foreach($this->recipients as $r)
      $this->addPaddedStringN($r);
  }

  protected function _addSystemData()
  {
    $this->_addSystemDataBasic();

    $this->addUint16N(DCTL_NET_VALUE_TYPE_LIST);
    //+1 is for type
    $this->addUint32N($this->total_top_fields + 1);
    //type
    $this->addUint16N(DCTL_NET_VALUE_TYPE_NUMBER);
    $this->addUint32N($this->type);
  }
}

class dctlSession
{
  protected static $default_recv_packet_timeout = 5000;
  protected static $default_options = array(
              array(SOL_SOCKET, SO_RCVTIMEO, array('sec' => 5, 'usec' => 0)),
              array(SOL_SOCKET, SO_SNDTIMEO, array('sec' => 5, 'usec' => 0))
              );

  protected $socket;
  protected $buffer = '';
  protected $exclude_filter = array();
  protected $include_filter = array();
  protected $filtered = array();
  protected $forbidden = array();

  function __construct($socket)
  {
    $this->socket = $socket;
    foreach(self::$default_options as $opt)
      $this->setSocketOption($opt);
  }

  static function create($port, $host)
  {                         
    $sock = dctlSession :: createSocket($port, $host);
    return new dctlSession($sock);
  }

  static function setDefaultRecvPacketTimeout($timeout)
  {
    self::$default_recv_packet_timeout = $timeout;
  }

  static function setDefaultSocketOptions($options)
  {
    self::$default_options = $options;
  }

  function setSocketOption($option)
  {
    return socket_set_option($this->socket, $option[0], $option[1], $option[2]);
  }

  function setSocketRcvTimeout($sec, $microsec = 0)
  {
    $this->setSocketOption(array(SOL_SOCKET, SO_RCVTIMEO, array('sec' => $sec, 'usec' => $microsec)));
  }

  function setSocketSndTimeout($sec, $microsec = 0)
  {
    $this->setSocketOption(array(SOL_SOCKET, SO_SNDTIMEO, array('sec' => $sec, 'usec' => $microsec)));
  }

  function getSocket()
  {
    return $this->socket;
  }

  function exists()
  {
    return is_resource($this->socket);
  }

  function close()
  {
    if(is_resource($this->socket))
      socket_close($this->socket);
    else
      throw new Exception("Socket is not valid");
  }

  function write($bytes)
  {
    $socket = $this->socket;
    if(!is_resource($socket))
      throw new Exception("Passed socket is not a valid resource");
    $len = strlen($bytes);
    $offset = 0;
    while($offset < $len) 
    {
      $sent = socket_write($socket, substr($bytes, $offset), $len - $offset);
      if($sent === false) 
        throw new Exception('Could not write packet into socket. Socket last error: ' . socket_strerror(socket_last_error($socket)));
      $offset += $sent;
    } 
  }

  function read($size)
  {
    $socket = $this->socket;
    if(!is_resource($socket))
      throw new Exception("Passed socket is not a valid resource");
    $bytes = '';
    while($size) 
    {
      $read = socket_read($socket, $size);
      if($read === false)
        throw new Exception('Failed read from socket! Socket last error: '.socket_strerror(socket_last_error($socket)));
      else if($read === "") 
        throw new Exception('Failed read from socket! No more data to read.');
      $bytes .= $read;
      $size -= strlen($read);
    }
    return $bytes;
  }

  function writeMsg($msg)
  {
    $this->write($msg->pack());
  }

  function writePacket($packet)
  {
    if(defined('VERBOSE'))
    {
      $at = '';
      if(function_exists('debug_backtrace'))
      {
        $debug = debug_backtrace();
        $at = '(' . basename($debug[0]['file']) . '@' . $debug[0]['line'] . ')';
      }
      echo "({$this->socket}) send '{$packet->getName()}' $at\n";
    }

    $this->writeMsg($packet->createMessage());
  }

  protected function _doRecvMsgFromSocket($sock)
  {
    return dctlMessage::readFromSession($this, /*raw*/false);
  }

  function recvMsg($timeout = null)
  {
    $time_start = microtime(true) * 1000;
    do
    {
      $msg = $this->_doRecvMsgFromSocket($this->socket);
      if($timeout && $timeout < (microtime(true) * 1000 - $time_start))
        throw new Exception("Message recieval timeout expired");
    } 
    while($msg === null);

    return $msg;
  }

  function recvPacket($type, $timeout = null)
  {
    if(is_null($timeout))
      $timeout = self::$default_recv_packet_timeout;

    if(!is_string($type))
      throw new Exception("String name of a packet expected");

    if(defined('VERBOSE'))
    {
      $at = '';
      if(function_exists('debug_backtrace'))
      {
        $debug = debug_backtrace();
        $at = '(' . basename($debug[0]['file']) . '@' . $debug[0]['line'] . ')';
      }
      echo "({$this->socket}) wait '$type' $at...\n";
    }

    $msg = $this->recvMsg($timeout);

    $class = 'Packet_' . $type;
    $packet = new $class($msg);
    return $packet;
  }

  function isClosed()
  {
    if(!$this->socket)
      return true;

    $clients = array($this->socket);
    for($i=0;$i<10;$i++)
    {
      if(!is_resource($this->socket))
        return true;

      $read = $clients;
      if(socket_select($read, $write = NULL, $except = NULL, 0) < 1)
      {
        usleep(300);
        continue;    
      }

      foreach($read as $read_sock) 
      {
        //TODO: read data should be placed into buffer
        //$data = @socket_read($read_sock, 1, PHP_BINARY_READ);
        $data = @socket_read($read_sock, 1024);
        if($data === "")
          return true;
        $this->buffer .= $data;
      }
    }
    return false;
  }

  static function createSocket($port, $host)
  {
    $sock = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if($sock === false)
      throw new Exception("Could not create a socket\n");

    socket_set_block($sock);

    if(!@socket_connect($sock, $host, $port))
      throw new Exception("Could not connect to host '{$host}' at port '{$port}'\n");

    return $sock;
  } 
}

/**
* -e
* -e <value>
* --long-param
* --long-param=<value>
* --long-param <value>
* <value>
*/
function dctl_parse_argv($params, $noopt = array()) 
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

function dctl_read_from_stdin()
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

    $out->addUint16N(DCTL_NET_VALUE_TYPE_LIST);
    //+1 is for task name
    $out->addUint32N(count($this->args) + 1);
    $out->addUint16N(DCTL_NET_VALUE_TYPE_STRING);
    $out->addPaddedStringN($this->name);
    foreach($this->args as $arg)
    {
      $out->addUint16N(DCTL_NET_VALUE_TYPE_STRING);
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
    if($type != DCTL_NET_VALUE_TYPE_LIST)
      throw new Exception("Content is not a list");
    $list_size = $msg->extractUint32N();
    for($i=0;$i<$list_size;++$i)
    {
      $type = $msg->extractUint16N();
      if($type != DCTL_NET_VALUE_TYPE_STRING)
        throw new Exception("Content item at '$i' is not a string");

      $this->args[] = $msg->extractPaddedStringN();
    }
  }
}
