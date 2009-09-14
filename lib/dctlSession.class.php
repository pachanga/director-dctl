<?php
require_once(dirname(__FILE__) . '/dctlMessage.class.php');

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
  protected $ignore_unexpected = true;
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

  function ignoreUnexpectedPackets($flag = true)
  {
    $this->ignore_unexpected = $flag;
  }

  function addIncludeFilter($type)
  {
    if(is_array($type))
    {
      foreach($type as $item)
        $this->include_filter[$item] = true;
    }
    else
      $this->include_filter[$type] = true;
  }

  function markForbidden($type)
  {
    if(is_array($type))
    {
      foreach($type as $item)
        $this->forbidden[$item] = true;
    }
    else
      $this->forbidden[$type] = true;
  }

  function addExcludeFilter($type)
  {
    if(is_array($type))
    {
      foreach($type as $item)
        $this->exclude_filter[$item] = true;
    }
    else
      $this->exclude_filter[$type] = true;
  }

  function resetExcludeFilters()
  {
    $this->exclude_filter = array();
  }

  function resetIncludeFilters()
  {
    $this->include_filter = array();
  }

  function resetFiltredMessages()
  {
    $this->filtered = array();
  }

  function getFilteredMessages($type = null)
  {
    if($type && isset($this->filtered[$type]))
      return $this->filtered[$type];

    $all = array();
    foreach($this->filtered as $key => $arr)
      foreach($arr as $msg)
        $all[] = $msg;
    return $all;
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
    return GameMessage :: readFromSession($this, /*raw*/false);
  }

  // For portal
  function recvOneMsg() 
  {
    return $this->_doRecvMsgFromSocket($this->socket);
  }

  function recvMsg($timeout = null)
  {
    $time_start = microtime(true) * 1000;
    do
    {
      $msg = $this->_filterMsg($this->_doRecvMsgFromSocket($this->socket));
      if($timeout && $timeout < (microtime(true) * 1000 - $time_start))
        throw new Exception("Message recieval timeout expired");
    } 
    while($msg === null);

    return $msg;
  }

  protected function _filterMsg($msg)
  {
    if($this->forbidden && isset($this->forbidden[$msg->getType()]))
      throw new Exception("Message of type '" . $msg->getType() . "' is forbidden");

    if($this->include_filter && !isset($this->include_filter[$msg->getType()]))
    {
      $this->_addToFiltered($msg);
      return null;
    }

    if(isset($this->exclude_filter[$msg->getType()]))
    {
      $this->_addToFiltered($msg);
      return null;
    }

    return $msg;
  }

  protected function _addToFiltered($msg)
  {
    if(!isset($this->filtered[$msg->getType()]))
      $this->filtered[$msg->getType()] = array();

    $this->filtered[$msg->getType()][] = $msg;
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

    if($this->ignore_unexpected)
    {
      $time_start = microtime(true) * 1000;
      while(true)
      {
        $msg = $this->recvMsg($timeout);

        $mapped_type = GamePacket :: mapStringType2Number($type);
        if($mapped_type === null)
          throw new Exception("Could not map string type '$type' to any packet(forgot to build map?)");

        if($msg->getType() == $mapped_type)
          break;
        else if(defined('VERBOSE'))
          echo "({$this->socket}) packet '" . GamePacket :: mapToPacket($msg)->getName() . "' was ignored\n";

        if($timeout < (microtime(true) * 1000 - $time_start))
          throw new Exception("Packet '$type' recieval timeout expired");
      }
    }
    else
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
