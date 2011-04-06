<?php

/**
 * flavaFlatDataService
 * 
 * Accepts an optional parsing service as an argument to the constructor. 
 * The purpose of this is allow for the parsing of formats outside of PHP.
 * Included in flavaFlatDataParseService.class.php is YAML functionality.
 * If no parse service is injected, the service will fallback to PHP arrays.
 *
 * @package flavaFlatDataPlugin
 * @version $id$
 * @author Joshua Morse <joshua.morse@iostudio.com> 
 */
class flavaFlatDataService
{
  protected
    $filter, // filter definition for filtering repository records.
    $filteredRelatedRepositoryRecords, // related repository with filtered records defined by current property.
    $filteredRepositoryRecords, // current repository with filtered records.
    $hydrateLocalRelations,
    $hydrateForeignRelations,
    $parseService, // placeholder for an optionally-supplied parse service.
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
   * @param flavaFlatDataParseInterface $parseService 
   * @access public
   * @return void
   */
  public function __construct($repositoriesPath, flavaFlatDataLoaderInterface $parseService, $hydrateLocalRelations = true, $hydrateForeignRelations = true)
  {
    $this->filter = array(
      'leftValue' => null,
      'operator' => null,
      'rightValue' => null,
    );

    $this->parseService = $parseService;

    if ($this->parseService !== null)
    {
      $this->repositoryExtension = $this->parseService->getRepositoryExtension();
    }
    else
    {
      $this->repositoryExtension = 'php';
    }

    $this->setRepositoriesPath($repositoriesPath);
    $this->hydrateLocalRelations = $hydrateLocalRelations;
    $this->hydrateForeignRelations = $hydrateForeignRelations;
  }

  /**
   * Fetches a repository's records, set its name and hyrdates, if necessary.
   * 
   * @param mixed $repository 
   * @access public
   * @return object $flavaFlatDataService
   */
  public function getRepository($repository)
  {
    $this->resetRepositoryAndRecord();

    $this->repositoryName = $repository;
    $this->repositoryRecords = $this->loadRepository($repository);

    if ($this->hydrateLocalRelations)
    {
      $this->repositoryRecords = $this->hydrateLocalRelations($this->repositoryRecords);    
    }

    if ($this->hydrateForeignRelations)
    {
      $this->repositoryRecords = $this->hydrateForeignRelations($this->repositoryRecords);
    }

    return $this;
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
   * Hydrates relational data for a supplied data repository.
   * Searches the supplied repo for any relation definitions.
   * If any are found, it attempts to find those data repos and load them, 
   * replacing the definitions with the defined data.
   * 
   * @param array $repositoryRecords 
   * @access protected
   * @return array $repositoryRecords
   */
  protected function hydrateLocalRelations(array $repositoryRecords)
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
                $repositoryRecords[$repositoryRecord][$repositoryRecordProperty][$targetRepositoryRecord] = $targetRepositoryRecordProperties;
              }
            }
          }
        }
      }
    }

    return $repositoryRecords;
  }

  /**
   * Hydrates relations based on set foreign_alias parameters.
   * 
   * Searches all repositories for:
   *   - A relation definition set to the current repository's name. 
   *   - A foreign_alias definition within the relation definition. 
   * 
   * If found, the function will add the related data to the current repository.
   * 
   * @access protected
   * @return void
   */
  protected function hydrateForeignRelations(array $repositoryRecords)
  {
    foreach ($this->getRepositoryNames() as $repositoryName)
    {
      $targetRepositoryRecords = $this->loadRepository($repositoryName);

      if ($this->hasLocalRelationDefinition($targetRepositoryRecords))
      {
        foreach ($targetRepositoryRecords as $targetRepositoryRecordId => $targetRepositoryRecord)
        {
          foreach ($targetRepositoryRecord as $repositoryRecordProperty => $repositoryRecordPropertyValue)
          {
            if ($this->hasLocalRelationDefinition($repositoryRecordPropertyValue))
            {
              if ($repositoryRecordPropertyValue['repository'] = $this->repositoryName)
              {
                //var_dump($repositoryRecordPropertyValue['values']); die;

                foreach ($repositoryRecords as $repositoryRecordId => $repositoryRecord)
                {
                  if (in_array($repositoryRecordId, $repositoryRecordPropertyValue['values']))
                  {
                    // current repo record exists in foreign relation values
                    // take foreign related repo and add it to current repo record as related property

                    // @todo get rid of - this repository name - from the below array (relational data).
                    $repositoryRecords[$repositoryRecordId][$repositoryRecordPropertyValue['foreign_alias']][$targetRepositoryRecordId] = $this->removeRelationDefinitions($targetRepositoryRecord);
                    //$repositoryRecords[$repositoryRecordId][$repositoryRecordPropertyValue['foreign_alias']][$targetRepositoryRecordId] = $targetRepositoryRecord;
                  }
                }
              }
            }
          }
        }
      }

      return $repositoryRecords;
    }
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
      $pattern = '/.*\.' . $this->parseService->getRepositoryExtension() . '/';

      if (($file !== '.' && $file !== '..') && preg_match($pattern, $file))
      {
        // We only need the repository name from here on out. We'll kill the extension here.
        $targetRepositoryName = str_replace('.' . $this->parseService->getRepositoryExtension(), '', $file);
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
    return $this->parseService->loadRepository(
      sprintf('%s%s.%s',
        $this->repositoriesPath,
        $repository,
        $this->repositoryExtension
    ));
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
   * hasForeignRelationDefinition 
   * 
   * @param mixed $repositoryRecords 
   * @access public
   * @return void
   */
  public function hasForeignRelationDefinition($repositoryRecords)
  {
  }

  /**
   * Recursively searches a supplied repository for the
   * existence of a relation definition.
   * 
   * @param array $repositoryRecords 
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
    if ($this->record !== null)
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
        $statement = $leftValue > $rightValue;
        break;
      case self::GREATER_THAN_EQUAL:
        $statement = $leftValue >= $rightValue;
        break;
      case self::LESS_THAN:
        $statement = $leftValue < $rightValue;
        break;
      case self::LESS_THAN_EQUAL:
        $statement = $leftValue <= $rightValue;
        break;
      case self::NOT_EQUAL:
        $statement = $leftValue != $rightValue;
        break;
      case self::EQUAL:
        $statement = $leftValue == $rightValue;
        break;
      case self::NOT_IDENTICAL:
        $statement = $leftValue !== $rightValue;
        break;
      case self::IDENTICAL:
        $statement = $leftValue === $rightValue;
        break;
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
   *   - filtered repository
   *   - repository 
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
}
