<?php

namespace MongoLite;

/**
 * Database object.
 */
class Database {

    /**
     * @var PDO object
     */
    public    $connection;

    /**
     * @var array
     */
    protected $collections = array();

    /**
     * @var string
     */
    protected $path;

    /**
     * @var array
     */
    protected $document_criterias = array();


    /**
     * Constructor
     * 
     * @param string $path   
     * @param array  $options
     */
    public function __construct($path = ":memory:", $options = array()) {
        
        $dns = "sqlite:{$path}";

        $this->path = $path;
        $this->connection = new \PDO($dns, null, null, $options);

        $database = $this;

        $this->connection->sqliteCreateFunction('document_key', function($key, $document){
            
            $document = json_decode($document, true);

            return isset($document[$key]) ? $document[$key] : '';
        }, 2);

        $this->connection->sqliteCreateFunction('document_criteria', function($funcid, $document) use($database) {
            
            $document = json_decode($document, true);

            return $database->callCriteriaFunction($funcid, $document);
        }, 2);

    }

    /**
     * Register Criteria function
     * 
     * @param  mixed $criteria
     * @return mixed          
     */
    public function registerCriteriaFunction($criteria) {

        $id = uniqid("criteria");

        if (is_callable($criteria)) {
           $this->document_criterias[$id] = $criteria;
           return $id;
        }

        if (is_array($criteria)) {
            
            $fn = array();

            foreach ($criteria as $key => $value) {
                $fn[] = "(\$document['{$key}']==".(is_string($value) ? "'{$value}'": $value).")";
            }

            $fn = trim(implode(" && ", $fn));

            $this->document_criterias[$id] = create_function('$document','return '.$fn.';');
            return $id;
        }

        return null;
    }

    /**
     * Execute registred criteria function
     * 
     * @param  string $id      
     * @param  array $document
     * @return boolean
     */
    public function callCriteriaFunction($id, $document) {

        return isset($this->document_criterias[$id]) ? $this->document_criterias[$id]($document):false;
    }

    /**
     * Vacuum database
     */
    public function vacuum() {
        $this->connection->query('VACUUM');
    }

    /**
     * Drop database
     */
    public function drop() {
        if ($this->path != ":memory:") {
            unlink($this->path);
        }
    }

    /**
     * Create a collection
     * 
     * @param  string $name
     */
    public function createCollection($name) {
        $this->connection->exec("CREATE TABLE {$name} ( id INTEGER PRIMARY KEY AUTOINCREMENT, document TEXT )");
    }

    /**
     * Drop a collection
     * 
     * @param  string $name
     */
    public function dropCollection($name) {
        $this->connection->exec("DROP TABLE `{$name}`");
    }

    /**
     * Get all collection names in the database
     * 
     * @return array
     */
    public function getCollectionNames() {
        
        $stmt   = $this->connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name!='sqlite_sequence';");
        $tables = $stmt->fetchAll( \PDO::FETCH_ASSOC);
        $names  = array();

        foreach($tables as $table) {
            $names[] = $table["name"];
        }

        return $names;
    }

    /**
     * Get all collections in the database
     * 
     * @return array
     */
    public function listCollections() {

        foreach ($this->getCollectionNames() as $name) {
            if(!isset($this->collections[$name])) {
                $this->collections[$name] = new Collection($name, $this);
            }
        }

        return $this->collections;
    }

    /**
     * Select collection
     * 
     * @param  string $name
     * @return object
     */
    public function selectCollection($name) {

        if(!isset($this->collections[$name])) {
            
            if(!in_array($name, $this->getCollectionNames())) {
                $this->createCollection($name);
            }

            $this->collections[$name] = new Collection($name, $this);
        }

        return $this->collections[$name];
    }

    public function __get($collection) {
        
        return $this->selectCollection($collection);
    }    
}