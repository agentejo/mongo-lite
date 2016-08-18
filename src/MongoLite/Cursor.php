<?php

namespace MongoLite;
use PDO;

/**
 * Cursor object.
 */
class Cursor implements \Iterator{

    /**
     * @var boolean|integer
     */
    protected $position = false;

    /**
     * @var array
     */
    protected $data = array();

    /**
     * @var Collection
     */
    protected $collection;

    /**
     * @var string|null
     */
    protected $criteria;

    /**
     * @var array|null
     */
    protected $projection;

    /**
     * @var integer|null
     */
    protected $limit;

    /**
     * @var integer|null
     */
    protected $skip;

    /**
     * @var array|null
     */
    protected $sort;

    /**
     * Constructor
     *
     * @param object $collection
     * @param mixed $criteria
     * @param mixed $projection
     */
    public function __construct($collection, $criteria, $projection = null) {
        $this->collection  = $collection;
        $this->criteria    = $criteria;
        $this->projection  = $projection;
    }

    /**
     * Documents count
     *
     * @return integer
     */
    public function count() {

        $sql = "SELECT COUNT(*) AS C FROM " . $this->collection->name;

        if ($this->criteria) {

            $temporary_sql = array($sql);

            $temporary_sql[] = 'WHERE document_criteria("' . $this->criteria . '", document)';

            if ($this->limit) {
                $temporary_sql[] = 'LIMIT ' . $this->limit;
            }

            $sql = implode(" ", $temporary_sql);
        }

        $stmt = $this->collection->database->connection->query($sql, PDO::FETCH_ASSOC);

        $result = $stmt->fetch();

        $count = $result['C'];

        if (!intval($count)) {
            return 0;
        }

        return $count;
    }

    /**
     * Set limit
     *
     * @param  mixed $limit
     * @return \MongoLite\Cursor
     */
    public function limit($limit) {

        $this->limit = intval($limit);

        return $this;
    }

    /**
     * Set sort
     *
     * @param  mixed $sorts
     * @return \MongoLite\Cursor
     */
    public function sort($sorts) {

        $this->sort = $sorts;

        return $this;
    }

    /**
     * Set skip
     *
     * @param  mixed $skip
     * @return \MongoLite\Cursor
     */
    public function skip($skip) {

        $this->skip = $skip;

        return $this;
    }

    /**
     * Loop through result set
     *
     * @param  mixed $callable
     * @return \MongoLite\Cursor
     */
    public function each($callable) {

        foreach ($this->rewind() as $document) {
            $callable($document);
        }

        return $this;
    }

    /**
     * Get documents matching criteria
     *
     * @return array
     */
    public function toArray() {
        return $this->getData();
    }


    /**
     * Get documents matching criteria
     *
     * @return array
     */
    protected function getData() {

        $sql = array('SELECT document FROM '.$this->collection->name);

        if ($this->criteria) {
            $sql[] = 'WHERE document_criteria("'.$this->criteria.'", document)';
        }

        if ($this->sort) {

            $orders = array();

            foreach ($this->sort as $field => $direction) {
                $orders[] = 'document_key("'.$field.'", document) '.($direction==-1 ? "DESC":"ASC");
            }

            $sql[] = 'ORDER BY '.implode(',', $orders);
        }

        if ($this->limit) {
            $sql[] = 'LIMIT '.$this->limit;

            if ($this->skip) {
                $sql[] = 'OFFSET '.$this->skip;
            }
        }

        $sql = implode(' ', $sql);

        $stmt      = $this->collection->database->connection->query($sql, PDO::FETCH_ASSOC);
        $result    = $stmt->fetchAll();
        $documents = array();

        if (!$this->projection) {

            foreach($result as &$doc) {
                $documents[] = json_decode($doc["document"], true);
            }

        } else {

            $exclude = [];
            $include = [];

            foreach($this->projection as $key => $value) {

                if ($value) {
                    $include[$key] = 1;
                } else {
                    $exclude[$key] = 1;
                }
            }

            foreach($result as &$doc) {

                $item = json_decode($doc["document"], true);
                $id   = $item["_id"];

                if ($exclude) {
                    $item = array_diff_key($item, $exclude);
                }

                if ($include) {
                    $item = array_key_intersect($item, $include);
                }

                if (!isset($exclude["_id"])) {
                    $item["_id"] = $id;
                }

                $documents[] = $item;
            }
        }

        return $documents;
    }

    /**
     * Iterator implementation
     */
    public function rewind() {

        if($this->position!==false) {
            $this->position = 0;
        }
    }

    public function current() {

        return $this->data[$this->position];
    }

    public function key() {
        return $this->position;
    }

    public function next() {
        ++$this->position;
    }

    public function valid() {

        if($this->position===false) {
            $this->data     = $this->getData();
            $this->position = 0;
        }

        return isset($this->data[$this->position]);
    }

}

function array_key_intersect(&$a, &$b) {

    $array = [];

    while (list($key,$value) = each($a)) {
        if (isset($b[$key])) {
            $array[$key] = $value;
        }
    }

    return $array;
}
