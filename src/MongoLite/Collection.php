<?php

namespace MongoLite;

/**
 * Collection object.
 */
class Collection {

    /**
     * @var \MongoLite\Database
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
     * @return mixed last_insert_id for single document or
     * count count of inserted documents for arrays
     */
    public function insert(&$document) {

        if (!isset($document[0])) {
            return $this->_insert($document);
        }

        $this->database->connection->beginTransaction();

        foreach ($document as &$doc) {

            if(!is_array($doc)) continue;

            $res = $this->_insert($doc);
            if(!$res) {
                $this->database->connection->rollBack();
                return $res;
            }
        }

        $this->database->connection->commit();

        return count($document);
    }

    /**
     * Insert document
     *
     * @param  array $document
     * @return integer|false
     */
    protected function _insert(&$document) {

        $table           = $this->name;
        $document["_id"] = uniqid().'doc'.rand();
        $data            = array("document" => json_encode($document, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE));

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

        if(!$res) {
            trigger_error('SQL Error: '.implode(', ', $this->database->connection->errorInfo()).":\n".$sql);
            return false;
        }

        return $this->database->connection->lastInsertId();
    }

    /**
     * Save document
     *
     * @param  array $document
     * @return mixed
     */
    public function save(&$document) {

        if (!isset($document["_id"])) {
            $this->insert($document);
        }

        return $this->update(array("_id" => $document["_id"]), $document);
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

            $sql = "UPDATE ".$this->name." SET document=".$this->database->connection->quote(json_encode($document,JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE))." WHERE id=".$doc["id"];

            $this->database->connection->exec($sql);
        }

        return count($result);
    }

    /**
     * Remove documents
     *
     * @param  mixed $criteria
     * @return integer|false
     */
    public function remove($criteria) {

        $sql = 'DELETE FROM '.$this->name.' WHERE document_criteria("'.$this->database->registerCriteriaFunction($criteria).'", document)';

        return $this->database->connection->exec($sql);
    }

    /**
     * Count documents in collections
     *
     * @param  mixed $criteria
     * @return integer
     */
    public function count($criteria = null) {

        return $this->find($criteria)->count();
    }

    /**
     * Find documents
     *
     * @param  mixed $criteria
     * @param mixed $projection
     * @return \MongoLite\Cursor
     */
    public function find($criteria = null, $projection = null) {
        return new Cursor($this, $this->database->registerCriteriaFunction($criteria), $projection);
    }

    /**
     * Find one document
     *
     * @param  mixed $criteria
     * @param mixed $projection
     * @return array
     */
    public function findOne($criteria = null, $projection = null) {

        $items = $this->find($criteria, $projection)->limit(1)->toArray();

        if (!isset($items[0])) {
            return null;
        }

        return $items[0];
    }

    /**
     * Rename Collection
     *
     * @param  string $new_name [description]
     * @return boolean
     */
    public function renameCollection($new_name) {

        if (!in_array($new_name, $this->database->getCollectionNames())) {
            return false;
        }

        $this->database->connection->exec("ALTER TABLE '.$this->name.' RENAME TO {$new_name}");

        $this->name = $new_name;

        return true;
    }
}