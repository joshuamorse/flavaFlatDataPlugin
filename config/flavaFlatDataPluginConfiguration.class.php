<?php

/**
 * flavaFlatDataPluginConfiguration 
 * 
 * @uses sfPluginConfiguration
 * @package 
 * @version $id$
 * @author Joshua Morse <joshua.morse@iostudio.com> 
 */
class flavaFlatDataPluginConfiguration extends sfPluginConfiguration
{
  protected $instance;

  public function initialize()
  {
    $action = new flavaFlatDataAction();

    // Notify the dispatcher that we're going to be listening when a method can't be found.
    // Tell the dispatcher to look in our action file and see if the method in question exists there.
    $this->dispatcher->connect('component.method_not_found', array($action, 'listenComponentMethodNotFound'));
  }

  /**
   * Returns the FlatDataService.
   * 
   * @access public
   * @return object $flavaFlatDataService
   */
  public function getFlatDataService()
  {
    if ($this->instance === null)
    {
      $class = sfConfig::get(
        'flava_flat_file_data_plugin_yaml_loader',
        'flavaFlatYamlDataLoader'
      );

      $class = 'flavaFlatPhpDataLoader'; // temp

      $hydrateForeignRelations = true;
      $hydrateLocalRelations = true;
      $repositoriesPath = dirname(__FILE__) . '/data/';

      $parseService = new $class();
      $this->instance = new flavaFlatDataService($repositoriesPath, $parseService, $hydrateLocalRelations, $hydrateForeignRelations);
    }

    return $this->instance;
  }
}
