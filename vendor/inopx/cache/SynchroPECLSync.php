<?php
namespace inopx\cache;

/**
 * Synchronization through PECL Sync library
 *
 * @author INOVUM Tomasz Zadora
 */
class SynchroPECLSync extends \inopx\cache\AdapterInterfaceSynchro {
  
  /**
   * PECL Sync Class
   * 
   * @var \SyncReaderWriter 
   */
  protected $syncReaderWriter;
  
  /**
   * Timeout in milliseconds
   * 
   * @var int 
   */
  protected $timeout;
  
  /**
   * Current key used in debug mode
   * @var mixed 
   */
  private $key;
  
  /**
   * Set to true for testing
   * @var boolean 
   */
  private $debug;
  
  /**
   * Set to true, of there is no SYNC library present
   * @var boolean 
   */
  private $noSynchro;
  
  /**
   * 
   * @param string $key   - synchro key
   * @param int $timeout  - timeout in milliseconds (1/1000 sec).
   */
  public function __construct($key, $timeout = 30000, $debug = null) {
    
    $this->debug = $debug;
    
    if ($this->debug) { 
      $this->key = $key;
    }
    
    if (!class_exists('syncReaderWriter')) {
      $this->noSynchro = true;
      error_log('WARNING - Sync library is not present', E_WARNING);
    }
    else {
      $this->syncReaderWriter = new \SyncReaderWriter($key);
    }
    
    $this->timeout = $timeout;
  }
  
  public function readLock() {
    
    if ($this->noSynchro) {
      return true;
    }
    
    if ($this->debug) {
      error_log('readLock() timeout '.$this->timeout.' for '.$this->key);
    }
    
    return $this->syncReaderWriter->readlock( $this->timeout );
  }

  public function readUnlock() {
    
    if ($this->noSynchro) {
      return true;
    }
    
    if ($this->debug) {
      error_log('readUnlock() for '.$this->key);
    }
    
    return $this->syncReaderWriter->readunlock();
  }

  public function writeLock() {
    
    if ($this->noSynchro) {
      return true;
    }
    
    if ($this->debug) {
      error_log('writeLock() timeout '.$this->timeout.' for '.$this->key);
    }
    
    return $this->syncReaderWriter->writelock( $this->timeout );
  }

  public function writeUnlock() {
    
    if ($this->noSynchro) {
      return true;
    }
    
    if ($this->debug) {
      error_log('writeUnlock() for '.$this->key);
    }
    
    return $this->syncReaderWriter->writeunlock();
  }

  
}
