<?php

namespace MongoLite;

/**
 * Database object.
 */
class Database {

    /**
     * @var \PDO
     */
    public $connection;

    /**
     * @var Collection[]
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
     * @param array $options
     */
    public function __construct($path = ":memory:", $options = array()) {

        $dns = "sqlite:{$path}";

        $this->path = $path;
        $this->connection = new \PDO($dns, null, null, $options);

        $database = $this;

        $this->connection->sqliteCreateFunction('document_key', function($key, $document){

            $document = json_decode($document, true);

            if (!isset($document[$key])) return '';

            return $document[$key];
        }, 2);

        $this->connection->sqliteCreateFunction('document_criteria', function($function_id, $document) use($database) {

            $document = json_decode($document, true);

            return $database->callCriteriaFunction($function_id, $document);
        }, 2);

        $this->connection->exec('PRAGMA journal_mode = MEMORY');
        $this->connection->exec('PRAGMA synchronous = OFF');
        $this->connection->exec('PRAGMA PAGE_SIZE = 4096');
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

        if (!is_array($criteria)) {
            return null;
        }

        $this->document_criterias[$id] = create_function('$document','return '.UtilArrayQuery::buildCondition($criteria).';');

        return $id;
    }

    /**
     * Execute registered criteria function
     *
     * @param  string $id
     * @param  array $document
     * @return boolean
     */
    public function callCriteriaFunction($id, $document) {

        if(!isset($this->document_criterias[$id])) {
            return false;
        }

        return $this->document_criterias[$id]($document);
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
     * @return string[]
     */
    public function getCollectionNames() {

        $stmt   = $this->connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name!='sqlite_sequence';");
        $tables = $stmt->fetchAll( \PDO::FETCH_ASSOC);

        return array_column($tables, 'name');
    }

    /**
     * Get all collections in the database
     *
     * @return \MongoLite\Collection[]
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
     * @return \MongoLite\Collection
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


class UtilArrayQuery {

    public static function buildCondition($criteria, $concat = " && ") {

        $fn = array();

        foreach ($criteria as $key => $value) {

            switch($key) {

                case '$and':
                    $fn[] = '('.self::buildCondition($value, ' && ').')';
                    break;
                case '$or':
                    $fn[] = '('.self::buildCondition($value, ' || ').')';
                    break;
                default:

                    $d = '$document';

                    if(strpos($key, ".") !== false) {
                        $keys = explode('.', $key);

                        foreach ($keys as &$k) {
                            $d .= '["'.$k.'"]';
                        }

                    } else {
                        $d .= '["'.$key.'"]';
                    }

                    $fn[] = is_array($value) ? "\\MongoLite\\UtilArrayQuery::check((isset({$d}) ? {$d} : null), ".var_export($value, true).")": "(isset({$d}) && {$d}==".(is_string($value) ? "'{$value}'": var_export($value, true)).")";
            }
        }

        return count($fn) ? trim(implode($concat, $fn)) : 'true';
    }


    public static function check($value, $condition) {

        if(is_null($value)) return false;

        $keys  = array_keys($condition);

        foreach ($keys as &$key) {
            if(!self::evaluate($key, $value, $condition[$key])) {
                return false;
            }
        }

        return true;
    }

    private static function evaluate($func, $a, $b) {
        switch ($func) {
            case '$eq' :
                return $a == $b;
            case '$not' :
                return $a != $b;
            case '$gte' :
            case '$gt' :
                if (!is_numeric($a) || !is_numeric($b)) {
                    throw new \InvalidArgumentException('Invalid argument for $gt. Both arguments must be numeric');
                }
                return $a > $b;

            case '$lte' :
            case '$lt' :
                if (!is_numeric($a) || !is_numeric($b)) {
                    throw new \InvalidArgumentException('Invalid argument for $gt. Both arguments must be numeric');
                }
                return $a < $b;

            case '$in' :
                if (!is_array($b)) {
                    throw new \InvalidArgumentException('Invalid argument for $in. Option must be array');
                }
                return in_array($a, $b);

            case '$has' :
                if (is_array($b)) {
                    throw new \InvalidArgumentException('Invalid argument for $has. Array not supported');
                }
                $a = @json_decode($a, true) ?: array();
                return in_array($b, $a);

            case '$all' :
                $a = json_decode($a, true);
                if (!$a) {
                    throw new \RuntimeException('Runtime error in $all json_decode: ' . json_last_error_msg());
                }

                if (!is_array($b)) {
                    throw new \InvalidArgumentException('Invalid argument for $all. Option must be array');
                }

                return count(array_intersect_key($a, $b)) == count($b);

            case '$regex' :
            case '$preg' :
            case '$match' :
                return (boolean)@preg_match(isset($b[0]) && $b[0] == '/' ? $b : '/' . $b . '/i', $a, $match);

            case '$size' :
                $a = json_decode($a, true);
                if (!$a) {
                    throw new \RuntimeException('Runtime error in $size json_decode: ' . json_last_error_msg());
                }
                return (int)$b == count($a);

            case '$mod' :
                if (!is_array($b)) {
                    throw new \InvalidArgumentException('Invalid argument for $mod option must be array');
                }
                list($x, $y) = each($b);
                return $a % $x == 0;

            case '$func' :
            case '$fn' :
            case '$f' :
                if (!is_callable($b)) {
                    throw new \InvalidArgumentException('Function should be callable');
                }
                return $b($a);

            default :
                throw new \ErrorException("Condition not valid ... Use {$func} for custom operations");
        }
    }
}
