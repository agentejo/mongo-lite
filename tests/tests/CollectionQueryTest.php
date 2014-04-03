<?php

class CollectionQueryTest extends PHPUnit_Framework_TestCase {

    private static $collection;

    public static function setUpBeforeClass() {
        $database = new \MongoLite\Database();
        $database->createCollection("testdb");
        $collection = $database->selectCollection("testdb");

        $entry1 = ["name" => "Super cool Product", "price" => 20, "in_stock" => true];
        $entry2 = ["name" => "Another cool Product", "price" => 15, "in_stock" => false];
        $entry3 = ["name" => "Awesome Product", "price" => 50, "in_stock" => false];
        $collection->insert($entry1);
        $collection->insert($entry2);
        $collection->insert($entry3);

        self::$collection = $collection;
    }

    public function testCountEntries() {
        $result = self::$collection->find()->count();

        $this->assertEquals(3, $result);
    }

    public function testFindEntryByField() {
        $entries = self::$collection->find(["price" => 15])->toArray();

        $this->assertEquals(1, count($entries));
        $this->assertEquals("Another cool Product", $entries[0]["name"]);

        $entries = self::$collection->find(["in_stock" => true])->toArray();
        $this->assertEquals(1, count($entries));
        $this->assertEquals("Super cool Product", $entries[0]["name"]);

        $entries = self::$collection->find(["in_stock" => false])->toArray();
        $this->assertEquals(2, count($entries));
        $this->assertEquals("Another cool Product", $entries[0]["name"]);
        $this->assertEquals("Awesome Product", $entries[1]["name"]);
    }

    public function testFindEntryWithGreaterLessThanCriteria() {
        $entries = self::$collection->find(["price" => ['$gt' => 15]])->toArray();

        $this->assertEquals(2, count($entries));
        $this->assertEquals("Super cool Product", $entries[0]["name"]);
        $this->assertEquals("Awesome Product", $entries[1]["name"]);

        $entries = self::$collection->find(["price" => ['$lt' => 20]])->toArray();

        $this->assertEquals(1, count($entries));
        $this->assertEquals("Another cool Product", $entries[0]["name"]);
    }
}