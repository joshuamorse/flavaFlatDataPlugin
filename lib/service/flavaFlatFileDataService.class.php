<?php

/**
 * flavaFlatFileDataService 
 * 
 * @package 
 * @version $id$
 * @author Joshua Morse <joshua.morse@iostudio.com> 
 */
class flavaFlatFileDataService
{
  protected
    $filter, // filter definition for filtering repository records.
    $filteredRelatedRepositoryRecords, // related repository with filtered records defined by current property.
    $filteredRepositoryRecords, // current repository with filtered records.
    $parseService,
    $property, // current property.
    $record, // current record.
    $relatedRepository, // full related repository on which to filter.
    $repository, // current repository.
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
   * @param mixed $modelPath 
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
   * getInstance 
   * 
   * @static
   * @access public
   * @return void
   */
  public static function getInstance()
  {
    if (self::$instance === null)
    {
      self::$instance = new ioYamlModelService();
    }

    return self::$instance;
  }

  /**
   * getRepository 
   * 
   * @param mixed $repository 
   * @access public
   * @return void
   */
  public function getRepository($repository)
  {
    $this->repository = $this->loadRepository($repository);
    $this->repositoryName = $repository;

    return $this;
  }

  /**
   * loadRepository 
   * 
   * @param mixed $repository 
   * @access public
   * @return void
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
   * @return void
   */
  public function getRecord($record)
  {
    if (!array_key_exists($record, $this->repository))
    {
      throw new exception('Cannot find ' . $record . ' in the following repository: ' . $this->repositoryName . '.');
    }

    $this->record = $this->repository[$record];

    return $this;
  }

  /**
   * getProperty 
   * 
   * @param mixed $property 
   * @static
   * @access public
   * @return void
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
   * @return void
   */
  public function filter($leftValue, $operator, $rightValue)
  {
    if ($this->record !== null)
    {
      throw new exception('Cannot filter a repository from which a record is already fetched!');
    }

    $this->filteredRepositoryRecords = $this->repository;

    foreach ($this->filteredRepositoryRecords as $id => $record)
    {
      $this->setFilter(array(
        'leftValue' => $record[$leftValue],
        'operator' => $operator,
        'rightValue' => $rightValue,
      ));

      $this->filteredRepositoryRecords = $this->runFilter($this->filter, $this->filteredRepositoryRecords, $id);
    }

    return $this;
  }

  /**
   * runFilter 
   * 
   * @param array $filter 
   * @param array $repository 
   * @param mixed $id 
   * @access protected
   * @return array $repository
   */
  protected function runFilter(array $filter, array $repository, $id)
  {
    // Does the record pass the filter?
    if (!$this->passesFilter($filter))
    {
      // The current record doesn't pass the filter. We'll unset it from our results.
      unset($repository[$id]);
    }

    return $repository;
  }

  /**
   * setFilter 
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
   * passesFilter 
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
   * @return void
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

    if ($this->repository)
    {
      return $this->repository;
    }
  }

  /**
   * setModelPath 
   * 
   * @param mixed $path 
   * @access public
   * @return void
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
   * initFilter 
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
