flavaFlatDataPlugin
===================
A solution for defining and querying for read-only, flat-file data. Can read from PHP 
and YAML sources and is expandable from there.

Relation Options
----------------
- repository: specifies the target repository to which the given entry is related.
- type: if set to 'one', will stage a single relation by surpressing the record's key. defaults to 'many'.
- values: denotes the related records
- foreign_alias: links and defines how this relation is referred to in the target repository.


General Usage
-------------
You'll begin by defining various data repositories. For this example, we'll say you
have a bunch projects you'd like to define in YAML:


    project1:
      name: 'Totally Awesome Project'
      about: 'Everything about this project.'
      is_featured: false
      counter: 12

    project2:
      name: 'Charlie and the Sausage Factory'
      about: 'Everything about this project.'
      is_featured: true
      counter: 46

    project3:
      name: 'Snail Trail, Inc.'
      about: 'Everything about this project.'
      is_featured: true
      counter: 80


Given the above data, let's say you want to fetch all the projects you have defined
in your project repository. We'll assume your repository is named project.yml:


    function executeYourAction(sfWebRequest $request)
    {
        $this->projects = $this->getFlatDataService()
          ->getRepository('project')
          ->execute()
        ;
    }


There you have it; an array containing data for all of your projects in your project
repository. But what if you want to a get a bit more granular and filter your data? 
The answer: use the filter function in your query. Let's grab all projects that are
featured:


    $this->projects = $this->getFlatDataService()
      ->getRepository('project')
      ->filter('is_featured', '==', true)
      ->execute()
    ;


Note that you can only filter on the records within a repository. You're also not limited 
to '==', either. For example, the following would return project3:


    $this->projects = $this->getFlatDataService()
      ->getRepository('project')
      ->filter('counter', '>', 79)
      ->execute()
    ;


You can also grab a specific record from a repository. Obviously this is especially useful 
when you're accessing a record based on a request paramter:


    function executeShow(sfWebRequest $request)
    {
      $this->forward404Unless($project_slug = $request->getParameter('project_slug'));

      $this->project = getFlatDataService()
        ->getRepository('project')
        ->getRecord($project_slug)
        ->execute()
      ;
    }
  

Relational Data
---------------
A little bit of extra data is required to set up a relation from one repository to the next. 
Let's define a user repository:


  user1:
    name: 'Jumping Jack'

  user2:
    name: 'Sal Amander'

  user3:
    name: 'Mr. Anon'


Say we want to set up a relation in our project records to relate to users that belong to those 
projects; a one-to-many relationship. We'll assume we've named our user repository user.yml:


   project1:
      name: 'Totally Awesome Project'
      about: 'Everything about this project.'
      is_featured: false
      counter: 12
      users:
        repository: user
        values: [user1, user2]

    project2:
      name: 'Charlie and the Sausage Factory'
      about: 'Everything about this project.'
      is_featured: true
      counter: 46
      users:
        repository: user
        values: [user3]

    project3:
      name: 'Snail Trail, Inc.'
      about: 'Everything about this project.'
      is_featured: true
      counter: 80
      users:
        repository: user
        values: [user1, user2, user3]


So we've set up our relations, but there's a problem. The relations we've just set up only 
exist within the context of the project repository. In other words, we can fetch the users 
for any project we choose without a problem:


    $this->projectUsers = $this->getFlatDataService()
      ->getRepository('project')
      ->getRecord('project3')
      ->getProperty('users')
      ->execute()
    ;


The problem arises when we attempt to do the inverse. That is, fetch the projects for a given 
user:


    $this->userProjects = $this->getFlatDataService()
      ->getRepository('user')
      ->getRecord('user1')
      ->getProperty('projects')
      ->execute()
    ;


Remember when set up user relations to project records within the context of the project 
repository? To alleviate our little problem, we'll have to do the same for projects to users:


    user1:
      name: 'Jumping Jack'
      projects:
        repository: project
        values: [project1, project2] 


We only have to do this with many-to-many relationships. For one-to-many relationships, it's a 
different story. Let's assume that a user can only be assigned to one project. That is, there is 
now a one-to-many relationship from projects to user. Our repository would reflect these changes 
as follows:


    project2:
      name: 'Charlie and the Sausage Factory'
      about: 'Everything about this project.'
      is_featured: true
      counter: 46
      users:
        foreign_alias: project
        repository: user
        values: [user3]


Now that we've set a one-to-many relationship to users from the project repository alongside setting 
up a foreign alias, we can now reference that foreign alias when we query the user repository as if 
it were a regular property:


    $this->userProject = $this->getFlatDataService()
      ->getRepository('work_item')
      ->getRecord('work_item_a')
      ->getProperty('project')
      ->execute()
    ;




PHP Repositories Definition Example
-----------------------------------
Example:

    return array(
      'user1' => array(
        'name' => 'mr man',
        'location' => 'wherever, whenever',
      ),
      'user2' => array(
        'name' => 'mrs woman',
        'location' => 'whenever, wherever',
      ),
    );


Terminology
-----------
Repository -> Records -> Properties
