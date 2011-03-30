<?php

/**
 * flavaFlatFileDataPluginConfiguration 
 * 
 * @uses sfPluginConfiguration
 * @package 
 * @version $id$
 * @author Joshua Morse <joshua.morse@iostudio.com> 
 */
class flavaFlatFileDataPluginConfiguration extends sfPluginConfiguration
{
  protected $instance;
  protected $repositoryPath;
  protected $parseService;

  public function initialize()
  {
    $action = new flavaFlatFileDataAction();

    // Notify the dispatcher that we're going to be listening when a method can't be found.
    // Tell the dispatcher to look in our action file and see if the method in question exists there.
    $this->dispatcher->connect('component.method_not_found', array($action, 'listenComponentMethodNotFound'));

    $this->repositoryPath = dirname(__FILE__) . '/data/';
  }

  /**
   * Returns the flatFileDataService.
   * 
   * @access public
   * @return object $flavaFlatFileDataService
   */
  public function getFlatFileDataService()
  {
    if ($this->instance === null)
    {
      $class = sfConfig::get(
        'flava_flat_file_service_plugin_parse_service_class',
        'flavaFlatFileDataParseService'
      );

      $this->parseService = new $class();
      $this->instance = new flavaFlatFileDataService($this->repositoryPath, $this->parseService);
    }

    return $this->instance;
  }
}
