<?php

namespace MongoLite;

/**
 * Collection object.
 */
class Collection {

    /**
     * @var object Database
     */
    public $database;

    /**
     * @var string
     */
    public $name;

    /**
     * Constructor
     * 
     * @param string $name    
     * @param object $database
     */
    public function __construct($name, $database) {
        $this->name = $name;
        $this->database = $database;
    }

    /**
     * Drop collection
     */
    public function drop() {
        $this->database->dropCollection($this->name);
    }

    /**
     * Insert document
     * 
     * @param  array $document
     * @return mixed
     */
    public function insert(&$document) {
        
        $table           = $this->name;
        $document["_id"] = uniqid($table);
        $data            = array("document" => json_encode($document, JSON_NUMERIC_CHECK));

        $fields = array();
        $values = array();

        foreach($data as $col=>$value){
            $fields[] = "`{$col}`";
            $values[] = (is_null($value) ? 'NULL':$this->database->connection->quote($value));
        }
        
        $fields = implode(',', $fields);
        $values = implode(',', $values);

        $sql = "INSERT INTO {$table} ({$fields}) VALUES ({$values})";

        $res = $this->database->connection->exec($sql);

        if($res){
            return $this->database->connection->lastInsertId();
        }else{
            trigger_error('SQL Error: '.implode(', ', $this->database->connection->errorInfo()).":\n".$sql);
            return false;
        }
    }

    /**
     * Save document
     * 
     * @param  array $document
     * @return mixed
     */
    public function save(&$document) {

        return isset($document["_id"]) ? $this->update(array("_id" => $document["_id"]), $document) : $this->insert($document);
    }

    /**
     * Update documents
     * 
     * @param  mixed $criteria
     * @param  array $data    
     * @return integer
     */
    public function update($criteria, $data) {

        $sql    = 'SELECT id, document FROM '.$this->name.' WHERE document_criteria("'.$this->database->registerCriteriaFunction($criteria).'", document)';
        $stmt   = $this->database->connection->query($sql);
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach($result as &$doc) {
            
            $document = array_merge(json_decode($doc["document"], true), $data);

            $sql = "UPDATE ".$this->name." SET document=".$this->database->connection->quote(json_encode($document,JSON_NUMERIC_CHECK))." WHERE id=".$doc["id"];

            $this->database->connection->exec($sql);
        }

        return count($result);
    }

    /**
     * Remove documents
     * 
     * @param  mixed $criteria
     * @return mixed
     */
    public function remove($criteria) {

        $sql = 'DELETE FROM '.$this->name.' WHERE document_criteria("'.$this->database->registerCriteriaFunction($criteria).'", document)';

        return $this->database->connection->exec($sql);
    }

    /**
     * Count documents in collections
     * 
     * @return integer
     */
    public function count() {
        
        $stmt   = $this->database->connection->query("SELECT COUNT(*) AS C FROM ".$this->name);
        $res = $stmt->fetch( \PDO::FETCH_ASSOC);

        return intval(isset($res['C']) ? $res['C']:0);
    }

    /**
     * Find documents
     * 
     * @param  mixed $criteria
     * @return object Cursor
     */
    public function find($criteria = null) {
        return new Cursor($this, $this->database->registerCriteriaFunction($criteria));
    }

    /**
     * Find one document
     * 
     * @param  mixed $criteria
     * @return array
     */
    public function findOne($criteria = null) {
        $items = $this->find($criteria)->limit(1)->toArray();

        return isset($items[0]) ? $items[0]:null; 
    }
}