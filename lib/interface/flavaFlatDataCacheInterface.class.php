<?php

/**
 * flavaFlatDataCacheInterface 
 * 
 * @package 
 * @version $id$
 * @author Joshua Morse <joshua.morse@iostudio.com> 
 */
interface flavaFlatDataCacheInterface
{
  /**
   * Defines function for fetching data from the cache.
   * 
   * @param mixed $entry 
   * @access public
   * @return mixed
   */
  function getEntry($entry);

  /**
   * Defines functionality for setting data in the cache.
   * 
   * @param mixed $entryKey 
   * @param mixed $data 
   * @access public
   * @return void
   */
  function setEntry($entryKey, $data);
}
