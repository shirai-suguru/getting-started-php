<?php
/*
 * Copyright 2015 Google Inc. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Google\Cloud\Samples\Bookshelf;

use Google\Cloud\Samples\Bookshelf\DataModel\CloudSql;
use Google\Cloud\Samples\Bookshelf\DataModel\Datastore;
use Google\Cloud\Samples\Bookshelf\DataModel\MongoDb;
use Google\Cloud\Samples\Bookshelf\FileSystem\CloudStorage;

require_once __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../src/app.php';
require __DIR__ . '/../src/controllers.php';

// Cloud Storage
$bucket = getenv('GOOGLE_STORAGE_BUCKET');
$app['bookshelf.storage'] = new CloudStorage($bucket);

// Data Model
$db = getenv('BOOKSHELF_DATA_BACKEND');
if ($db === false) {
    $db = 'cloudsql'; // default
}
if ($db == 'mongodb') {
    $app['bookshelf.model'] = new MongoDb();
} elseif ($db == 'datastore') {
    $datasetId = getenv('GOOGLE_DATASET_ID');
    $app['bookshelf.model'] = new Datastore($datasetId);
} elseif ($db == 'cloudsql') {
    $app['bookshelf.model'] = new CloudSql();
} else {
    throw Exception("Invalid BOOKSHELF_DATA_BACKEND given: $db. "
                    . "Possible values are cloudsql, mongodb, or datastore.");
}

$app->run();
