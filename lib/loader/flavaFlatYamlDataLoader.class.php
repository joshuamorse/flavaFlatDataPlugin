<?php

/**
 * Specific to user.
 * 
 * @uses flavaFlatDataParseInterface
 * @package 
 * @version $id$
 * @author Joshua Morse <joshua.morse@iostudio.com> 
 */
class flavaFlatYamlDataLoader implements flavaFlatDataLoaderInterface
{
  protected $extension = 'yml';
  protected $useCache = true;

  public function loadRepository($respository)
  {
    return sfYaml::load($respository);
  }

  public function getRepositoryExtension()
  {
    return $this->extension;
  }
}
