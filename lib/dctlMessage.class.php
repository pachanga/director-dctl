<?php 
require_once(dirname(__FILE__) . '/dctlByteBuffer.class.php');

define('NET_VALUE_TYPE_NUMBER', 1);
define('NET_VALUE_TYPE_STRING', 3);
define('NET_VALUE_TYPE_LIST', 7);

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

    $this->addUint16N(NET_VALUE_TYPE_LIST);
    //+1 is for type
    $this->addUint32N($this->total_top_fields + 1);
    //type
    $this->addUint16N(NET_VALUE_TYPE_NUMBER);
    $this->addUint32N($this->type);
  }
}
