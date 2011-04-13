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

    $this->connectTests();
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

      $loaderClass = 'flavaFlatPhpDataLoader'; // temp
      $loader = new $loaderClass();

      $cacheClass = 'flavaFlatSfFileCache';
      $cacheDriver = new $cacheClass(sfConfig::get('sf_cache_dir'));

      $repositoriesPath = dirname(__FILE__) . '/data/';

      $this->instance = new flavaFlatDataService($repositoriesPath, $loader, $cacheDriver);
    }

    return $this->instance;
  }
}
