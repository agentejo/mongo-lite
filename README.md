MongoLite
=========

Schemaless database on top of SqLite

###Sample Usage

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



###Query collection

In general you can use a callback or simple array as criteria

    $collection->find(function($document) {
        return $document["price"] > 10;
    });

    or

    $collection->find(["name"=>"Super cool Product"]); // just for very simple criteria array

    or just one

    $collection->findOne(function($document) { ... });
    $collection->findOne([...]);

###Writing documents

    $collection->insert($document);
    $collection->save($document);
    $collection->update($criteria, $data);

###Delete documents
    
    $collection->remove($criteria);

##API

**Client**

    Client::listDBs()
    Client::selectDB(databasename)
    Client::selectCollection(databasename, collectionname)

**Database**

    Database::vacuum()
    Database::drop()
    Database::createCollection(collectionname)
    Database::dropCollection(collectionname)
    Database::getCollectionNames()
    Database::listCollections()
    Database::selectCollection(collectionname)

**Collection**

    Collection::drop()
    Collection::insert(document)
    Collection::save(document)
    Collection::update(criteria, data)
    Collection::remove(criteria)
    Collection::count()
    Collection::find(criteria)
    Collection::findOne(criteria)

**Cursor**

    Cursor::count()
    Cursor::limit(number)
    Cursor::skip(number)
    Cursor::sort(array)
    Cursor::toArray()


