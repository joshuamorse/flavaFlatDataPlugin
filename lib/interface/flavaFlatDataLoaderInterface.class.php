<?php

/**
 * flavaFlatDataLoaderInterface 
 * 
 * @package 
 * @version $id$
 * @author Joshua Morse <joshua.morse@iostudio.com> 
 */
interface flavaFlatDataLoaderInterface
{
  /**
   * Defines how the loader should parse a repository, once found.
   * Example: if you're wanting to parse a YAML file via sfYaml, your function might look something like this:
   *
   *   return sfYaml::load($respository);
   * 
   * 
   * @param mixed $respository 
   * @access public
   * @return void
   */
  function loadRepository($respository);

  /**
   * Returns the extension of your repository files.
   * Example: if you were planning on parsing YAML files, you'd return 'yml' here.
   * 
   * @access public
   * @return string
   */
  function getRepositoryExtension();
}
