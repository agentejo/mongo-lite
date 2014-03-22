<?php

class CollectionCRUDTest extends PHPUnit_Framework_TestCase {

    private static $collection;
    
    public static function setUpBeforeClass() {
        $database = new \MongoLite\Database();
        $database->createCollection("testdb");
        $collection = $database->selectCollection("testdb");
        
        $entry1 = ["name" => "Super cool Product", "price" => 20, "in_stock" => true];
        $entry2 = ["name" => "Another cool Product", "price" => 15, "in_stock" => false];
        $collection->insert($entry1);
        $collection->insert($entry2);
        
        self::$collection = $collection;
    }
    
    public function testCreateEntry() {
        $entry = ["name" => "Awesome Product", "price" => 100, "in_stock" => false];
        
        $this->assertEquals(2, self::$collection->find()->count());
        
        self::$collection->insert($entry);
        
        $this->assertEquals(3, self::$collection->find()->count());
        
        $entries = self::$collection->find()->toArray();
        
        $this->assertEquals("Awesome Product", $entries[2]["name"]);
        $this->assertEquals(100, $entries[2]["price"]);
    }
    
    public function testReadAllEntries() {
        $entries = self::$collection->find()->toArray();
        
        $this->assertEquals(3, count($entries));
        
        $this->assertEquals("Super cool Product", $entries[0]["name"]);
        $this->assertEquals(20, $entries[0]["price"]);
        
        $this->assertEquals("Another cool Product", $entries[1]["name"]);
        $this->assertEquals(15, $entries[1]["price"]);
        
        $this->assertEquals("Awesome Product", $entries[2]["name"]);
        $this->assertEquals(100, $entries[2]["price"]);
    }
    
    public function testUpdateEntry() {
        $entry = self::$collection->findOne(["name" => "Awesome Product"]);
        
        $this->assertEquals(100, $entry["price"]);
        
        $entry["price"] = 50;
        self::$collection->save($entry);
        $entry = self::$collection->findOne(["name" => "Awesome Product"]);
        
        $this->assertEquals(50, $entry["price"]);
    }
    
    public function testDeleteEntry() {
        $entry = self::$collection->findOne(["name" => "Awesome Product"]);
       
        $this->assertEquals(3, self::$collection->find()->count());
       
        self::$collection->remove(["_id" => $entry["_id"]]);
       
        $this->assertEquals(2, self::$collection->find()->count());
       
        $entries = self::$collection->find()->toArray();

        $this->assertEquals("Super cool Product", $entries[0]["name"]);
        $this->assertEquals(20, $entries[0]["price"]);

        $this->assertEquals("Another cool Product", $entries[1]["name"]);
        $this->assertEquals(15, $entries[1]["price"]);
    }

}