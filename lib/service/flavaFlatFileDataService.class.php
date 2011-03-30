<?php

/**
 * flavaFlatFileDataService
 * 
 * Accepts an optional parsing service as an argument to the constructor. 
 * The purpose of this is allow for the parsing of formats outside of PHP.
 * Included in flavaFlatFileDataParseService.class.php is YAML functionality.
 * If no parse service is injected, the service will fallback to PHP arrays.
 *
 * @package flavaFlatFileDataPlugin
 * @version $id$
 * @author Joshua Morse <joshua.morse@iostudio.com> 
 */
class flavaFlatFileDataService
{
  protected
    $filter, // filter definition for filtering repository records.
    $filteredRelatedRepositoryRecords, // related repository with filtered records defined by current property.
    $filteredRepositoryRecords, // current repository with filtered records.
    $parseService, // placeholder for an optionally-supplied parse service.
    $property, // current property.
    $record, // current record.
    $relatedRepository, // full related repository on which to filter.
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
   * @param flavaFlatFileDataParseInterface $parseService 
   * @access public
   * @return void
   */
  public function __construct($repositoryPath, flavaFlatFileDataParseInterface $parseService = null)
  {
    $this->initFilter();
    $this->parseService = $parseService;

    if ($this->parseService !== null)
    {
      $this->repositoryExtension = $this->parseService->getRepositoryExtension();
    }
    else
    {
      $this->repositoryExtension = 'php';
    }

    $this->setRepositoryPath($repositoryPath);
  }

  /**
   * Fetches a repository's records.
   * 
   * @param mixed $repository 
   * @access public
   * @return object $flavaFlatFileDataService
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
    $path = $this->repositoryPath . $repository . '.' . $this->repositoryExtension;
    
    if ($this->repositoryExtension == 'php')
    {
      require_once($path);
      return $data;
    }
    else
    {
      return $this->parseService->parseRepository($path);
    }
  }
  
  /**
   * getRecord 
   * 
   * @param mixed $record 
   * @access public
   * @return object $flavaFlatFileDataService
   */
  public function getRecord($record)
  {
    if (!array_key_exists($record, $this->repositoryRecords))
    {
      throw new exception('Cannot find ' . $record . ' in the following repository: ' . $this->repositoryName . '.');
    }
    
    // Set the current record to the found repository record.
    $this->record = $this->repositoryRecords[$record];

    return $this;
  }

  /**
   * getProperty 
   * 
   * @param mixed $property 
   * @static
   * @access public
   * @return object $flavaFlatFileDataService
   */
  public function getProperty($property)
  {
    if ($this->record === null)
    {
      throw new exception('Cannot fetch a property without fetching a record first!');
    }

    if ($this->property === null)
    {
      // If this is our first time setting a property, we'll use the record's value.
      $this->property = $this->record[$property];
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
   * @return object $flavaFlatFileDataService
   */
  public function filter($leftValue, $operator, $rightValue)
  {
    if ($this->record !== null)
    {
      throw new exception('Cannot filter a repository from which a record is already fetched!');
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
        throw new exception('operator does not exist');
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
   * @return object flavaFlatFileDataService
   */
  public function setRepositoryPath($path)
  {
    if (!is_dir($path))
    {
      throw new sfException('Path not found: ' . $path);
    }

    $this->repositoryPath = $path;

    return $this;
  }

  /**
   * Initializes a filter.
   * 
   * @access protected
   * @return void
   */
  protected function initFilter()
  {
    $this->filter = array(
      'leftValue' => null,
      'operator' => null,
      'rightValue' => null,
    );
  }
}
