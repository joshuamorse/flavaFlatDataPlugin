<?php

/**
 * Cache driver implementation for sfFileCache; specific to the Symfony framework.
 * 
 * @uses flavaFlatDataCacheInterface
 * @package 
 * @version $id$
 * @author Joshua Morse <joshua.morse@iostudio.com> 
 */
class flavaFlatSfFileCache implements flavaFlatDataCacheInterface
{
  protected $driver;

  /**
   * __construct 
   * 
   * @access public
   * @return void
   */
  public function __construct($dir)
  {
    if (!is_dir($dir))
    {
      throw new sfException(sprintf('The directory "%s" cannot be found!', $dir));
    }

    $this->driver = new sfFileCache(array('cache_dir' => $dir));
  }

  /**
   * getEntry 
   * 
   * @param mixed $key 
   * @access public
   * @return void
   */
  public function getEntry($key)
  {
    return $this->driver->get($key);
  }

  /**
   * setEntry 
   * 
   * @param mixed $key 
   * @param mixed $data 
   * @access public
   * @return void
   */
  public function setEntry($key, $data)
  {
    return $this->driver->set($key, $data);
  }
}
