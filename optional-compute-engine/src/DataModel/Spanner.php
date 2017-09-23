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

namespace Google\Cloud\Samples\Bookshelf\DataModel;

use Google\Cloud\Spanner\SpannerClient;
use Google\Cloud\Spanner\Transaction;
use Google\Cloud\Spanner\Instance;

/**
 * Class Spanner implements the DataModel with a Google Cloud Spanner.
 */
class Spanner implements DataModelInterface
{
    private $spannerClient;
    private $instance;
    private $database;
    private $transaction;
    protected $columns = [
        'id'            => 'INT64 NOT NULL',
        'title'         => 'STRING(255)',
        'author'        => 'STRING(255)',
        'published_date' => 'STRING(255)',
        'image_url'     => 'STRING(255)',
        'description'   => 'STRING(255)',
        'created_by'    => 'STRING(255)',
        'created_by_id' => 'STRING(255)',
    ];
    private $columnNames;

    public function __construct($projectId, $instaceId, $databaseId)
    {
        $this->spannerClient = new SpannerClient([
            'projectId' => $projectId,
        ]);

        $this->columnNames = array_Keys($this->columns);

        $this->instance = $this->spannerClient->instance($instaceId);
        if (!$this->instance->exists()) {
            $configurationId = "projects/$projectId/instanceConfigs/regional-us-central1";
            $configuration = $this->spannerClient->instanceConfiguration($configurationId);
            $operation = $this->instance->create($configuration);
            $operation->pollUntilComplete();
        }

        $this->database = $this->instance->database($databaseId);
        if (!$this->database->exists()) {
            $operation = $this->instance->createDatabase($databaseId, ['statements' => [
                "CREATE TABLE Books (
                    id             INT64 NOT NULL,
                    title          STRING(255),
                    author         STRING(255),
                    published_date STRING(255),
                    image_url      STRING(255),
                    description    STRING(255),
                    created_by     STRING(255),
                    created_by_id  STRING(255)
                ) PRIMARY KEY (id)"
            ]]);
            $operation->pollUntilComplete();
        }
    }

    public function listBooks($limit = 10, $cursor = null)
    {
        $results = null;
        if ($cursor) {
            $results = $this->database->execute(
                'SELECT * FROM Books WHERE id > @cursor ORDER BY id LIMIT ' . ($limit + 1),
                [
                    'parameters' => [
                        'cursor' => (int)$cursor
                    ]
                ]
            );
        } else {
            $results = $this->database->execute(
                'SELECT * FROM Books  ORDER BY id LIMIT ' . ($limit + 1)
            );
        }

        $rows = array();
        $last_row = null;
        $new_cursor = null;
        foreach ($results->rows() as $row) {
            if (count($rows) == $limit) {
                $new_cursor = $last_row['id'];
                break;
            }
            array_push($rows, $row);
            $last_row = $row;
        }
        return [
            'books' => $rows,
            'cursor' => $new_cursor,
        ];
    }

    public function create($book, $id = null)
    {
        $this->verifyBook($book);

        $lastInsertId = 0;
        if ($id) {
            $book['id'] = $id;
        }
        
        $this->database->runTransaction(function (Transaction $t) use ($book, $id, &$lastInsertId) {
            $maxId = null;
            if (!$id) {
                //Get Max id
                $result = $t->execute('SELECT (MAX(id) + 1) AS max_id FROM Books');
                $maxId = $result->rows()->current()['max_id'];
                if (!$maxId) {
                    $maxId = 1;
                }
                $book['id'] = $maxId;
            }

            //Insert
            $t->insert('Books', $book);

            $lastInsertId = $maxId;
            
            $t->commit();

            // $result = $this->database->execute('SELECT MAX(id) AS max_id FROM Books');
            // $maxId = $result->rows()->current()['max_id'];
        });
        
        // return max id
        return $lastInsertId;
    }

    public function read($id)
    {
        $result = $this->database->execute(
            'SELECT * FROM Books WHERE id = @id',
            [
                'parameters' => [
                    'id'  => (int)$id
                ]
            ]
        );

        if ($result) {
            return $result->rows()->current();
        }

        return false;
    }

    public function update($book)
    {
        $this->verifyBook($book);

        if (!isset($book['id'])) {
            throw new \InvalidArgumentException('Book must have an "id" attribute');
        }

        $this->database->update('Books', $book);

        // return the number of updated rows
        return 1;
    }

    public function delete($id)
    {
        $bookKey = [$id];
        $bookKeySet = $this->spannerClient->keySet(['keys'=> [$bookKey]]);
        $this->database->delete('Books', $bookKeySet);
        return 1;
    }

    private function verifyBook($book)
    {
        if ($invalid = array_diff_key($book, $this->columns)) {
            throw new \InvalidArgumentException(sprintf(
                'unsupported book properties: "%s"',
                implode(', ', $invalid)
            ));
        }
    }
}
