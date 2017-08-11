MongoLite
=========

Schemaless database on top of SqLite


### Sample Usage

``` php
$client     = new MongoLite\Client(PATH_TO_WRITABLE_FOLDER);
$database   = $client->testdb;
$collection = $database->products;

$entry = ["name"=>"Super cool Product", "price"=>20];

$collection->insert($entry);

$products = $collection->find(); // Get Cursor

if ($products->count()) {

    foreach($products->sort(["price"=>1])->limit(5) as $product) {
        var_dump($product);
    }
}
```

### Query collection

In general you can use a callback or simple array as criteria

``` php
$collection->find(function($document) {   // recommended to query data
    return $document["price"] > 10;
});

//or

$collection->find(["price"=>['$gt'=>10]]); // only very simple criteria is supported (can be slow)

//or just one

$collection->findOne(function($document) { ... });
$collection->findOne([...]);
```

### Writing documents

``` php
$collection->insert($document);
$collection->save($document);
$collection->update($criteria, $data);
```

### Delete documents

``` php
$collection->remove($criteria);
```

##API

**Client**

``` php
Client::listDBs()
Client::selectDB(databasename)
Client::selectCollection(databasename, collectionname)
```

**Database**

``` php
Database::vacuum()
Database::drop()
Database::createCollection(collectionname)
Database::dropCollection(collectionname)
Database::getCollectionNames()
Database::listCollections()
Database::selectCollection(collectionname)
```

**Collection**

``` php
Collection::drop()
Collection::renameCollection(newname)
Collection::insert(document)
Collection::save(document)
Collection::update(criteria, data)
Collection::remove(criteria)
Collection::count()
Collection::find(criteria)
Collection::findOne(criteria)
```

**Cursor**

``` php
Cursor::count()
Cursor::limit(number)
Cursor::skip(number)
Cursor::sort(array)
Cursor::each($callable)
Cursor::toArray()
```

## Installation

To install and use MongoLite via the composer PHP package manager just take these steps:


If you don’t already have one, create the file composer.json in the root of your new project that is going to use MongoLite.

Add the following to the composer.json file..

    {
        "require": {
            "agentejo/mongo-lite": "dev-master"
        }
    }

Install composer (if it isn’t already installed):

    curl -s https://getcomposer.org/installer | php
    php composer.phar install
