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
  public function __construct($repositoriesPath, flavaFlatDataLoaderInterface $parseService)
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
  }

  /**
   * Fetches a repository's records.
   * 
   * @param mixed $repository 
   * @access public
   * @return object $flavaFlatDataService
   */
  public function getRepository($repository)
  {
    $this->repositoryRecords = $this->loadRepository($repository);
    $this->repositoryName = $repository;

    return $this;
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
    return $this->parseService->loadRepository($this->repositoriesPath . $repository . '.' . $this->repositoryExtension);
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
   * hasRelationDefinition 
   * 
   * @param mixed $repositoryRecords 
   * @access public
   * @return void
   */
  public function hasRelationDefinition($repositoryRecords = null)
  {
    if ($repositoryRecords === null)
    {
      $repositoryRecords = $this->repositoryRecords;
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
        $result = $this->hasRelationDefinition($record);
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
        /**
         * The requested record can't be found. This either means one of two things:
         *   - It's an invalid record request.
         *   - It's a relational record request and it's record value is defined as a foreign alias.
         *
         * Course of action: check all other repositories in $repositoriesPath for the requested (possible) foreignAlias.
         */
        
        $handle = opendir($this->repositoriesPath);

        while (false !== ($file = readdir($handle)))
        {
          // Make sure we're only grabbing files that have the extension specified by our loader service.
          $pattern = '/.*\.' . $this->parseService->getRepositoryExtension() . '/';

          if (($file !== '.' && $file !== '..') && preg_match($pattern, $file))
          {
            // We only need the repository name from here on out. We'll kill the extension here.
            $targetRepositoryName = str_replace('.' . $this->parseService->getRepositoryExtension(), '', $file);

            // Let's start searching! Though, there's no need to search the repository we're already in...
            if ($targetRepositoryName != $this->repositoryName)
            {
              $targetRepositoryRecords = $this->loadRepository($targetRepositoryName);
              
              // Check the repository to see if a relation definition exists.
              if ($this->hasRelationDefinition($targetRepositoryRecords))
              {
                // Looks like this repository has defined relations. Let's dig a bit deeper.
                foreach ($targetRepositoryRecords as $targetRepositoryRecord => $targetRepositoryProperties)
                {
                  // Let's take a look at each fo the record's properties and try to find a relation definition.
                  foreach ($targetRepositoryProperties as $targetRepositoryProperty => $value) 
                  {
                    // If it's a definition, it's an array.
                    if (is_array($value))
                    {
                      // Ensure the repository definition is identical to ours.
                      if ($value['repository'] == $this->repositoryName && $value['foreign_alias'] == $property)
                      {
                        // Ensure that our record name exists in the relation definition.
                        if (array_search($this->recordName, $value['values']) !== false)
                        {
                          // If we've made it here, we've found our record in the relation.
                          // We'll see the property to the related record's properties.
                          $this->property = $targetRepositoryProperties;
                        }
                      }
                    }
                  }
                }
              }
            }
          }
        }

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
   *   - filtered respository
   *   - repository 
   * 
   * @static
   * @access public
   * @return array
   */
  public function execute()
  {
    if ($this->property)
    {
      return $this->property;
    }

    if ($this->record)
    {
      return $this->record;
    }

    if ($this->filteredRepositoryRecords)
    {
      return $this->filteredRepositoryRecords;
    }

    if ($this->repositoryRecords)
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
