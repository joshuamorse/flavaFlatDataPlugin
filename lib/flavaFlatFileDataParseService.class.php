<?php

require_once dirname(__FILE__).'/flavaFlatFileDataParseInterface.class.php';

/**
 * Specific to user.
 * 
 * @uses flavaFlatFileDataParseInterface
 * @package 
 * @version $id$
 * @author Joshua Morse <joshua.morse@iostudio.com> 
 */
class flavaFlatFileDataParseService implements flavaFlatFileDataParseInterface
{
  protected $extension = 'yml';

  public function parseRepository($respository)
  {
    return sfYaml::load($respository);
  }

  public function getRepositoryExtension()
  {
    return $this->extension;
  }
}
