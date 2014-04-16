<?php

class ViceSnapshot {

  // Snapshot file descriptor
  var $fp = NULL;
  var $firstModuleOffset = 0;

  var $major = NULL;
  var $minor = NULL;
  var $machineName = '';

  var $modules = array();

  const SNAPSHOT_MAGIC_STRING        = "VICE Snapshot File\032";
  const SNAPSHOT_MAGIC_LEN           = 19;
  const SNAPSHOT_MACHINE_NAME_LEN    = 16;
  const SNAPSHOT_MODULE_NAME_LEN     = 16;

  public function loadFromFile($filePath) {
    $this->fp = fopen($filePath, 'rb');
    if ($this->fp === FALSE) {
      throw new Excpetion("Can not open file [$filePath]");
    }
  }

  public function parse() {
    $this->openSnapshotFile();
    $this->readModules();
  }

  public function getModule($name) {
    if (isset($this->modules[$name])) {
      return $this->modules[$name];
    }
    return FALSE;
  }

  // Open snaphot, validate header and init snapshot info
  private function openSnapshotFile() {
    // validate magic string
    $magic = $this->readByteArray(ViceSnapshot::SNAPSHOT_MAGIC_LEN);
    if ($magic != ViceSnapshot::SNAPSHOT_MAGIC_STRING) {
      throw new Exception("File is not a valid snapshot. Magic string not found.");
    }

    // read version number.
    $this->major = $this->readByte();
    $this->minor = $this->readByte();

    // read machine name
    $this->machineName = $this->readByteArray(ViceSnapshot::SNAPSHOT_MACHINE_NAME_LEN);

    // save position
    $this->firstModuleOffset = ftell($this->fp);
  }

  private function readModules() {
    $ok = TRUE;
    while($ok) {
      $ok = $this->readModule();
    }
  }

  // returns FALSE when no more modules to read.
  private function readModule() {
    // read module name
    $name = $this->readByteArray(ViceSnapshot::SNAPSHOT_MODULE_NAME_LEN);
    if ($name === FALSE || $name === '') {
      return FALSE;
    }
    // read vesion
    $major = $this->readByte();
    if ($major === FALSE) {
      throw new Exception("Error reading major version for section [$name].");
    }
    $minor = $this->readByte();
    if ($minor === FALSE) {
      throw new Exception("Error reading minor version for section [$name].");
    }
    // read length
    $moduleSize = $this->readDWord();
    if ($moduleSize === FALSE) {
      throw new Exception("Error reading section [$name].");
    }

    $len = $moduleSize - ViceSnapshot::SNAPSHOT_MODULE_NAME_LEN - 2 - 4;
    $content = $this->readByteArray($len);
    if ($content === FALSE) {
      throw new Exception("Could not read $len bytes for section $name.");
    }

    // save content in tarra
    $key = "";
    foreach(str_split($name) as $char) {
      if ($char === "\000") {
        break;
      }
      $key .= $char;
    }
    $this->modules[$key] = $content;
    return TRUE;
  }

  // Helper function to read from file
  private function readByte() {
    $c = fgetc($this->fp);
    if ($c === FALSE) {
      return FALSE;
    }
    return ord($c);
  }

  private function readWord() {
    $lo = $this->readByte();
    $hi = $this->readByte();
    if ($lo === FALSE || $hi === FALSE) {
      return FALSE;
    }

    return $lo | ($hi << 8);
  }

  private function readDWord() {
    $lo = $this->readWord();
    $hi = $this->readWord();
    if ($lo === FALSE || $hi === FALSE) {
      return FALSE;
    }
    return $lo | ($hi << 16);
  }

  // Byte array function
  private function readByteArray($len) {
    return fread($this->fp, $len);
  }

}


class BinaryStringReader {

  var $content;
  var $pos = 0;

  public function __construct($data) {
    $this->content = $data;
  }

  public function readByte() {
    return ord($this->content[$this->pos++]);
  }

  public function readWord() {
    $lo = $this->readByte();
    $hi = $this->readByte();
    if ($lo === FALSE || $hi === FALSE) {
      return FALSE;
    }

    return $lo | ($hi << 8);
  }

  public function readDWord() {
    $lo = $this->readWord();
    $hi = $this->readWord();
    if ($lo === FALSE || $hi === FALSE) {
      return FALSE;
    }
    return $lo | ($hi << 16);
  }

  public function readByteArray($len) {
    $data = substr($this->content, $this->pos, $len);
    $this->pos += $len;
    return $data;
  }
}


class C64Snapshot {

  var $snap = NULL;

  const C64_MEMORY = 'C64MEM';
  const C64_VIC_II = 'VIC-II';

  function loadFromFile($filename) {
    $this->snap = new ViceSnapshot();
    $this->snap->loadFromFile($filename);
    $this->snap->parse();
  }

  function getMemory() {
    return $this->snap->getModule(C64Snapshot::C64_MEMORY);
  }

  function getVicInfo() {
    // reference: src/vicii/vicii-snapshot.c
    $vicii = array();
    $content = $this->snap->getModule(C64Snapshot::C64_VIC_II);
    $reader = new BinaryStringReader($content);

    // parse Vic-II info
    $vicii['allow_bad_lines']              = $reader->readByte();
    $vicii['bad_line']                     = $reader->readByte();
    $vicii['raster']['blank_enabled']      = $reader->readByte();
    $vicii['cbuf']                         = bin2hex($reader->readByteArray(40));
    $vicii['color_ram']                    = bin2hex($reader->readByteArray(1024));
    $vicii['idle_state']                   = $reader->readByte();
    $vicii['light_pen']['triggered']       = $reader->readByte();
    $vicii['light_pen']['x']               = $reader->readByte();
    $vicii['light_pen']['y']               = $reader->readByte();
    $vicii['vbuf']                         = bin2hex($reader->readByteArray(40));
    $vicii['new_dma_mask']                 = $reader->readByte();
    $vicii['ram_base']                     = $reader->readDWord();
    $vicii['raster_cycle']                 = $reader->readByte();
    $vicii['raster_line']                  = $reader->readWord();
    $vicii['register']                     = str_split($reader->readByteArray(64));
    $vicii['sprite_background_collisions'] = $reader->readByte();
    $vicii['dma_mask']                     = $reader->readByte();
    $vicii['sprite_sprite_collisions']     = $reader->readByte();
    $vicii['vbank_phi1']                   = $reader->readWord();
    $vicii['mem_counter']                  = $reader->readWord();
    $vicii['mem_counter_inc']              = $reader->readByte();
    $vicii['mem_pointer']                  = $reader->readWord();
    $vicii['irq_status']                   = $reader->readByte();

    for($i = 0; $i < 8; $i++) {
      $vicii['raster']['sprite'][$i]['memptr']     = $reader->readByte();
      $vicii['raster']['sprite'][$i]['memptr_inc'] = $reader->readByte();
      $vicii['raster']['sprite'][$i]['exp_flag']   = $reader->readByte();
    }

    return $vicii;
  }
}
