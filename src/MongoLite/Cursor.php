<?php

namespace MongoLite;

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
     * @var Collection object
     */
    protected $collection;
    
    /**
     * @var string|null
     */
    protected $criteria;

    /**
     * @var null|integer
     */
    protected $limit;

    /**
     * @var null|array
     */
    protected $order;

    /**
     * Constructor
     * 
     * @param object $collection [description]
     * @param mixed $criteria   [description]
     */
    public function __construct($collection, $criteria) {
        $this->collection = $collection;
        $this->criteria   = $criteria;
    }

    /**
     * Documents count
     * 
     * @return integer
     */
    public function count() {
        
        if (!$this->criteria) {
            $c = $this->collection->count();

            return !is_null($this->limit) ? ($this->limit<$c ? $this->limit:$c) : $c;

        } else {

            $sql = array('SELECT COUNT(*) AS C FROM '.$this->collection->name);
            
            $sql[] = 'WHERE document_criteria("'.$this->criteria.'", document)';

            if ($this->limit) {
                $sql[] = 'LIMIT '.$this->limit;
            }

            $stmt = $this->collection->database->connection->query(implode(" ", $sql));
            $res  = $stmt->fetch(\PDO::FETCH_ASSOC);

            return intval(isset($res['C']) ? $res['C']:0);
        }
    }

    /**
     * Set limit
     * 
     * @param  mixed $limit [description]
     * @return object       Cursor
     */
    public function limit($limit) {

        $this->limit = intval($limit);

        return $this;
    }

    /**
     * Set order
     * 
     * @param  mixed $orders [description]
     * @return object       Cursor
     */
    public function order($orders) {
        
        $this->order = $orders;

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

        if ($this->order) {
            
            $orders = array();

            foreach ($this->order as $field => $direction) {
                $orders[] = 'document_key("'.$field.'", document) '.($direction==-1 ? "DESC":"ASC");
            }

            $sql[] = 'ORDER BY '.implode(',', $orders);
        }

        if ($this->limit) {
            $sql[] = 'LIMIT '.$this->limit;
        }

        $sql = implode(' ', $sql);

        $stmt      = $this->collection->database->connection->query($sql);
        $result    = $stmt->fetchAll( \PDO::FETCH_ASSOC);
        $documents = array();

        foreach($result as $doc) {
            $documents[] = json_decode($doc["document"], true);
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
