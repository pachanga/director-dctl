<?php 

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

    $this->bytes .= pack('a' . next_dividable(strlen($string), 2), $string);
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
    $str = $this->extractBytes(next_dividable($len, 2));
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
