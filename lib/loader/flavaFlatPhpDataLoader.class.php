<?php

/**
 * Specific to user.
 * 
 * @uses flavaFlatDataParseInterface
 * @package 
 * @version $id$
 * @author Joshua Morse <joshua.morse@iostudio.com> 
 */
class flavaFlatPhpDataLoader implements flavaFlatDataLoaderInterface
{
  protected $extension = 'php';

  public function loadRepository($repository)
  {
    if (!file_exists($repository))
    {
      throw new Exception(sprintf('Repository "%s" cannot be found!', $repository));
    }

    require($repository);

    if (!is_array($data))
    {
      throw new Exception('PHP repositories need be defined in array format!');
    }

    return $data;
  }

  public function getRepositoryExtension()
  {
    return $this->extension;
  }
}
