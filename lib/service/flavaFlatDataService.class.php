<?php

/**
 * flavaFlatDataService
 * 
 * Accepts an optional parsing service as an argument to the constructor. 
 * The purpose of this is allow for the parsing of formats outside of PHP.
 * Included in flavaFlatDataloaderService.class.php is YAML functionality.
 * If no parse service is injected, the service will fallback to PHP arrays.
 *
 * @package flavaFlatDataPlugin
 * @version $id$
 * @author Joshua Morse <dashvibe@gmail.com> 
 */
class flavaFlatDataService
{
  protected
    $cacheDriver, // placeholder for a cache driver,
    $filter, // filter definition for filtering repository records.
    $filteredRelatedRepositoryRecords, // related repository with filtered records defined by current property.
    $filteredRepositoryRecords, // current repository records filtered via the filter() function.
    $loader, // placeholder for an optionally-supplied parse service.
    $property, // current property.
    $record, // current record.
    $recordName, // current record name.
    $relatedRepository, // full related repository on which to filter.
    $repositoriesPath, // path where repositories are housed.
    $repositoryRecords, // current repository records.
    $repositoryExtension, // repository extension as 'php', or as defined by optional parse service.
    $repositoryName // current repository name.
  ;

  const EQUAL              = '==';
  const GREATER_THAN       = '>';
  const GREATER_THAN_EQUAL = '>=';
  const IDENTICAL          = '===';
  const LESS_THAN          = '<';
  const LESS_THAN_EQUAL    = '<=';
  const NOT_EQUAL          = '!=';
  const NOT_IDENTICAL      = '!==';

  /**
   * __construct 
   * 
   * @param mixed $repositoryPath 
   * @param flavaFlatDataLoaderInterface $loader 
   * @param flavaFlatDataCacheInterface $cacheDriver 
   * @access public
   * @return void
   */
  public function __construct($repositoriesPath, flavaFlatDataLoaderInterface $loader, flavaFlatDataCacheInterface $cacheDriver = null)
  {
    $this->filter = array(
      'leftValue' => null,
      'operator' => null,
      'rightValue' => null,
    );

    $this->loader = $loader;

    if ($this->loader !== null)
    {
      $this->repositoryExtension = $this->loader->getRepositoryExtension();
    }
    else
    {
      $this->repositoryExtension = 'php';
    }

    $this->setRepositoriesPath($repositoriesPath);

    $this->cacheDriver = $cacheDriver;
  }

  /**
   * Fetches a repository's records, set its name and hyrdates, if necessary.
   * 
   * @param mixed $repository 
   * @access public
   * @return object $flavaFlatDataService
   */
  public function getRepository($repositoryName)
  {
    $this->resetRepositoryAndRecord();
    $this->repositoryName = $repositoryName;
    $this->repositoryRecords = $this->loadRepository($repositoryName);

    $cacheEntryFound = false;

    // Has a cache driver been specified?
    if (is_object($this->cacheDriver))
    {
      // Can we find this repository's records in the cache?
      if ($repositoryRecords = $this->cacheDriver->getEntry($this->getRepositoryCacheKey($repositoryName)))
      {
        // We found it! Unserialize it and set it.
        $this->repositoryRecords = unserialize($repositoryRecords);
        $cacheEntryFound &= true;
      }
    }

    // If a cache entry can't be found, for any reason, we'll fetch the repository records here.
    if (!$cacheEntryFound)
    {
      $this->fetchRepository($repositoryName);

      // If a cache driver is defined, set the repository information.
      if (is_object($this->cacheDriver))
      {
        $this->cacheDriver->setEntry($this->getRepositoryCacheKey($repositoryName), serialize($this->repositoryRecords));
      }
    }

    return $this;
  }

  /**
   * Sets and stages a repository.
   * 
   * @param mixed $repositoryName
   * @access protected
   * @return void
   */
  protected function fetchRepository($repositoryName)
  {
    $this->resetRepositoryAndRecord();

    $this->repositoryName = $repositoryName;
    $this->repositoryRecords = $this->loadRepository($repositoryName);

    $this->hydrateLocalRelations($this->repositoryRecords);    
    $this->hydrateForeignRelations($this->repositoryRecords);
    $this->stageHydratedRelations($this->repositoryRecords);
  }

  /**
   * Unsets a number of properties that are used in querying for repository data.
   * 
   * @access protected
   * @return void
   */
  protected function resetRepositoryAndRecord()
  {
    unset($this->repositoryRecords);
    unset($this->filteredRepositoryRecords);
    unset($this->repositoryName);
    unset($this->record);
  }

  /**
   * Stages one-to-one relations in a hydrated repository records array.
   * More specifically, will convert something like this:
   * user:
   *   tester: 
   *     name: 'tester' 
   * 
   * To this: 
   * user:
   *   name: 'tester' 
   * 
   * @param array $repositoryRecords 
   * @access protected
   * @return void
   */
  protected function stageHydratedRelations(array &$repositoryRecords)
  {
    foreach ($repositoryRecords as $repositoryRecordId => $repositoryRecordProperty)
    {
      if (isset($repositoryRecordProperty['_relation_properties']))
      {
        foreach ($repositoryRecordProperty['_relation_properties'] as $relationPropertyId => $relationProperty)
        {
          // Has a type been set?
          if (isset($relationProperty['type']))
          {
            // Only proceed is the relationship is an x-to-one.
            if ($relationProperty['type'] == 'one')
            {
              // Ensure that we've only a single related record.
              if (count($repositoryRecordProperty[$relationProperty['property']]) === 1)
              {
                // We'll get rid of the key and hydrate the data here.
                $repositoryRecords[$repositoryRecordId][$relationProperty['property']] = reset($repositoryRecords[$repositoryRecordId][$relationProperty['property']]);
              }
            }
          }
        }
      }
    }
  }

  /**
   * Hydrates relational data for a supplied data repository.
   * Searches the supplied repo for any relation definitions.
   * If any are found, it attempts to find those data repos and load them, 
   * replacing the definitions with the defined data.
   * 
   * @param array $repositoryRecords 
   * @access protected
   * @return array $repositoryRecords
   */
  protected function hydrateLocalRelations(array &$repositoryRecords)
  {
    /**
     * Search through the repository records in an attempt to find relation definition.
     * We'll only iterate through the repository records if a relation is set somewhere within.
     */
    if ($this->hasLocalRelationDefinition($repositoryRecords))
    {
      foreach ($repositoryRecords as $repositoryRecord => $repositoryRecordProperties)
      {
        foreach ($repositoryRecordProperties as $repositoryRecordProperty => $repositoryRecordPropertyValue)
        {
          // Only dig deeper if we find a relation definition.
          if ($this->hasLocalRelationDefinition($repositoryRecordPropertyValue))
          {
            /**
             * We've found a relation defintion, if we've made it this far.  
             * Let's gather the data we'll need and load the target repository.
             */
            $targetRepositoryName = $repositoryRecordPropertyValue['repository'];
            $targetRepositoryRecords = $this->loadRepository($targetRepositoryName);
            $targetValues = $repositoryRecordPropertyValue['values'];

            /**
             * Clear out the relational data definition.
             * We already have the data we need and will be replacing this with actual related data.
             */
            $repositoryRecords[$repositoryRecord][$repositoryRecordProperty] = array();

            // Find the related records we'll need.
            foreach ($targetRepositoryRecords as $targetRepositoryRecord => $targetRepositoryRecordProperties)
            {
              // Is the current repository record one our target values?
              if (in_array($targetRepositoryRecord, $targetValues))
              {
                // Add the relation data to the _relation_properties property so we have a record of what we've auto-filled for the user.
                $repositoryRecords[$repositoryRecord]['_relation_properties'][] = array(
                  'property' => $repositoryRecordProperty,
                  'source' => 'local',
                );

                // Only set the type if it's been defined.
                if (isset($repositoryRecordPropertyValue['type']))
                {
                  $repositoryRecords[$repositoryRecord]['type'] = $repositoryRecordPropertyValue['type'];
                }
                else
                {
                  $repositoryRecords[$repositoryRecord]['type'] = 'many';
                }

                // Auto-fill the relation.
                $repositoryRecords[$repositoryRecord][$repositoryRecordProperty][$targetRepositoryRecord] = $targetRepositoryRecordProperties;
              }
            }
          }
        }
      }
    }
  }

  /**
   * Hydrates relations based on set foreign alias properties.
   * 
   * Searches all repositories for:
   *   - A relation definition set to the current repository's name. 
   *   - A foreign_alias definition within the relation definition. 
   * 
   * If found, the hydrateForeignRelations will add the related data to the current repository.
   * 
   * @access protected
   * @return void
   */
  protected function hydrateForeignRelations(array &$repositoryRecords)
  {
    // Get a list of all repostory names in our repository path.
    foreach ($this->getRepositoryNames() as $repositoryName)
    {
      // For each iteration, load up the repository.
      $targetRepositoryRecords = $this->loadRepository($repositoryName);

      // We're only interested in searching if the repository has a relation definition.
      if ($this->hasLocalRelationDefinition($targetRepositoryRecords))
      {
        // Iterate through each repository's record.
        foreach ($targetRepositoryRecords as $targetRepositoryRecordId => $targetRepositoryRecord)
        {
          // Iterate through each record's property.
          foreach ($targetRepositoryRecord as $repositoryRecordProperty => $repositoryRecordPropertyValue)
          {
            // We're only interested in proceeding if the property has a relation definition.
            if ($this->hasLocalRelationDefinition($repositoryRecordPropertyValue))
            {
              // Is a foreign alias set?
              if (isset($repositoryRecordPropertyValue['foreign_alias']))
              {
                // Does the set repository property match our current repository?
                if ($repositoryRecordPropertyValue['repository'] = $this->repositoryName)
                {
                  // Iterate through our current repository's records.
                  foreach ($repositoryRecords as $repositoryRecordId => $repositoryRecord)
                  {
                    // Do we have any matching records in the relation definition's values property?
                    if (in_array($repositoryRecordId, $repositoryRecordPropertyValue['values']))
                    {
                      // Add the relation data to the _relation_properties property so we have a record of what we've auto-filled for the user.
                      $repositoryRecords[$repositoryRecordId]['_relation_properties'][] = array(
                        'source' => 'foreign',
                      );

                      if (isset($repositoryRecordPropertyValue['foreign_alias']))
                      {
                        $repositoryRecords[$repositoryRecordId]['property'] = $repositoryRecordPropertyValue['foreign_alias'];
                      }

                      //if (isset($repositoryRecordPropertyValue['foreign_type']))
                      //{
                        //$repositoryRecords[$repositoryRecordId]['type'] = $repositoryRecordPropertyValue['foreign_type'];
                      //}

                      // We've got a match. Let's kill any relation definitions in the foreign relation definition.
                      $targetRepositoryRecord = $this->removeRelationDefinitions($targetRepositoryRecord);

                      // Then add it to our repository's records.
                      $repositoryRecords[$repositoryRecordId][$repositoryRecordPropertyValue['foreign_alias']][$targetRepositoryRecordId] = $targetRepositoryRecord;
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
    //$tgt = $repositoryRecords[$repositoryRecordId][$repositoryRecordPropertyValue['foreign_alias']];
  }

  /**
   * removeRelationDefinitions 
   * 
   * @param array $repositoryRecords 
   * @access protected
   * @return array $repositoryRecords
   */
  protected function removeRelationDefinitions(array $repositoryRecords)
  {
    if (!$this->hasLocalRelationDefinition($repositoryRecords))
    {
      return $repositoryRecords;
    }

    foreach ($repositoryRecords as $repositoryRecordId => $repositoryRecordProperty)
    {
      if ($this->hasLocalRelationDefinition($repositoryRecordProperty))
      {
        unset($repositoryRecords[$repositoryRecordId]);
      }
    }

    return $repositoryRecords;
  }

  /**
   * getRepositoryNames 
   * 
   * @access public
   * @return void
   */
  public function getRepositoryNames()
  {
    $handle = opendir($this->repositoriesPath);

    while (false !== ($file = readdir($handle)))
    {
      // Make sure we're only grabbing files that have the extension defined by our loader service.
      $pattern = '/.*\.' . $this->loader->getRepositoryExtension() . '/';

      if (($file !== '.' && $file !== '..') && preg_match($pattern, $file))
      {
        // We only need the repository name from here on out. We'll kill the extension here.
        $targetRepositoryName = str_replace('.' . $this->loader->getRepositoryExtension(), '', $file);
        $repositoryNames[] = $targetRepositoryName;
      }
    }

    return $repositoryNames;
  }

  /**
   * Attempts to load a repository.
   * If a parse service was supplied upon instantiation, it is used. 
   * Otherwise, will fall back to PHP arrays defined under the $data var.
   * 
   * @param mixed $repository 
   * @access public
   * @return array
   */
  public function loadRepository($repository)
  {
    return $this->loader->loadRepository(
      $this->repositoriesPath . $repository . '.' . $this->repositoryExtension
    );
  }
  
  /**
   * getRecord 
   * 
   * @param mixed $record 
   * @access public
   * @return object $flavaFlatDataService
   */
  public function getRecord($record)
  {
    if (!array_key_exists($record, $this->repositoryRecords))
    {
      throw new Exception(sprintf('Cannot find "%s" in the following repository: "%s"', $record, $this->repositoryName));
    }
    
    // Set the current record to the found repository record.
    $this->record = $this->repositoryRecords[$record];
    $this->recordName = $record;

    return $this;
  }

  /**
   * Recursively searches a supplied repository for the
   * existence of a relation definition.
   * 
   * @param mixed $repositoryRecords 
   * @access public
   * @return boolean
   */
  public function hasLocalRelationDefinition($repositoryRecords)
  {
    if (!is_array($repositoryRecords))
    {
      return false;
    }

    $needle = 'repository';
    $result = array_key_exists($needle, $repositoryRecords);

    if ($result)
    {
      return $result;
    }

    foreach ($repositoryRecords as $record)
    {
      if (is_array($record))
      {
        $result = $this->hasLocalRelationDefinition($record);
      }

      if ($result)
      {
        return $result;
      }
    }

    return $result;
  }

  /**
   * getProperty 
   * 
   * @param mixed $property 
   * @static
   * @access public
   * @return object $flavaFlatDataService
   */
  public function getProperty($property)
  {
    if ($this->record === null)
    {
      throw new Exception('Cannot fetch a property without fetching a record first!');
    }

    // If this is our first time setting a property, we'll attempt to use the record's value.
    if ($this->property === null)
    {
      if (array_key_exists($property, $this->record))
      {
        // We've found a record! Hooray!
        $this->property = $this->record[$property];
      }
      else
      {
        
        // If we didn't find a relation, we didn't find anything; looks like an invalid request!
        if ($this->property === null)
        {
          throw new Exception(sprintf('Property named "%s" was not found in record named "%s"!',
            $property,
            $this->recordName
          ));
        }
      }
    }
    else
    {
      // Otherwise, we'll get the property of the current property.
      $this->property = $this->property[$property];
    }

    // Is this a related property?
    if (isset($this->property['repository']))
    {
      // Load up the related repository
      $this->relatedRepository = $this->loadRepository($this->property['repository']);
      $this->filteredRelatedRepositoryRecords = array();

      // Filter the repository based on defined values.
      // At this point, $this->property is defined as such in the repository at hand (e.g. not the related repo).
      foreach ($this->property['values'] as $propertyId => $relatedRecordId)
      {
        // Attempt to find the related record id in the related repository.
        if (array_key_exists($relatedRecordId, $this->relatedRepository))
        {
          // If we find one, we'll add it to our filtered related repository results array.
          $this->filteredRelatedRepositoryRecords[$relatedRecordId] = $this->relatedRepository[$relatedRecordId];
        }
      }

      // Set the current property to the filtered repository records we've found.
      $this->property = $this->filteredRelatedRepositoryRecords;
    }

    return $this;
  }

  /**
   * Filters records in a repository. 
   * 
   * @param mixed $leftValue 
   * @param mixed $operator 
   * @param mixed $rightValue 
   * @static
   * @access public
   * @return object $flavaFlatDataService
   */
  public function filter($leftValue, $operator, $rightValue)
  {
    if (isset($this->record))
    {
      throw new Exception('Cannot filter a repository from which a record is already fetched!');
    }

    // Init the filteredRelatedRepositoryRecords var with the current repository records.
    $this->filteredRepositoryRecords = $this->repositoryRecords;

    foreach ($this->filteredRepositoryRecords as $filteredRepositoryRecordId => $filteredRepositoryRecord)
    {
      $this->setFilter(array(
        'leftValue' => $filteredRepositoryRecord[$leftValue],
        'operator' => $operator,
        'rightValue' => $rightValue,
      ));

      // Run the filter on the record/repository and update the filteredRepositoryRecords var.
      $this->filteredRepositoryRecords = $this->runFilter(
        $this->filter,
        $this->filteredRepositoryRecords,
        $filteredRepositoryRecordId
      );
    }

    return $this;
  }

  /**
   * Checks to see if a supplied filter is valid.
   * Unsets the record in question from the supplied repository, if applicable.
   * 
   * @param array $filter 
   * @param array $repository 
   * @param mixed $id 
   * @access protected
   * @return array $repositoryRecords
   */
  protected function runFilter(array $filter, array $repositoryRecords, $recordId)
  {
    // Does the record pass the filter?
    if (!$this->passesFilter($filter))
    {
      // The current record doesn't pass the filter. We'll unset it from our results.
      unset($repositoryRecords[$recordId]);
    }

    return $repositoryRecords;
  }

  /**
   * Sets a filter.
   * 
   * @param array $filterOptions 
   * @access public
   * @return void
   */
  public function setFilter(array $filterOptions)
  {
    $this->filter = $filterOptions;
  }

  /**
   * Determines whether or not a passed filter is valid.
   * 
   * @param mixed $leftValue 
   * @param mixed $operator 
   * @param mixed $rightValue 
   * @access protected
   * @return void
   */
  protected function passesFilter(array $filter)
  {
    $leftValue = $filter['leftValue'];
    $operator = $filter['operator'];
    $rightValue = $filter['rightValue'];

    switch ($operator)
    {
      case self::GREATER_THAN:
        return $leftValue > $rightValue;
      case self::GREATER_THAN_EQUAL:
        return $leftValue >= $rightValue;
      case self::LESS_THAN:
        return $leftValue < $rightValue;
      case self::LESS_THAN_EQUAL:
        return $leftValue <= $rightValue;
      case self::NOT_EQUAL:
        return $leftValue != $rightValue;
      case self::EQUAL:
        return $leftValue == $rightValue;
      case self::NOT_IDENTICAL:
        return $leftValue !== $rightValue;
      case self::IDENTICAL:
        return $leftValue === $rightValue;
      default:
        throw new Exception('operator does not exist');
    }

    return $statement;
  }

  /**
   * Executes a yaml data query.
   * Returns data based on order of prescedence: 
   *   - property 
   *   - record 
   *   - filtered repository records
   *   - repository records
   * 
   * @static
   * @access public
   * @return array
   */
  public function execute()
  {
    if (isset($this->property))
    {
      return $this->property;
    }

    if (isset($this->record))
    {
      return $this->record;
    }

    if (isset($this->filteredRepositoryRecords))
    {
      return $this->filteredRepositoryRecords;
    }

    if (isset($this->repositoryRecords))
    {
      return $this->repositoryRecords;
    }
  }

  /**
   * Verifies and sets a repository path.
   * Throws an exception if the suplied path can't be found. 
   * 
   * @param mixed $path 
   * @access public
   * @return object flavaFlatDataService
   */
  public function setRepositoriesPath($path)
  {
    if (!is_dir($path))
    {
      throw new sfException('Path not found: ' . $path);
    }

    $this->repositoriesPath = $path;

    return $this;
  }

  /**
   * Generates a cache key for the current repository.
   * 
   * @param mixed $repositoryName 
   * @access protected
   * @return void
   */
  protected function getRepositoryCacheKey($repositoryName)
  {
    return 'flava_flat_data_repository_' . $repositoryName;
  }
}
