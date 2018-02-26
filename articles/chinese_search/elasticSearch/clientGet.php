<?php

require 'vendor/autoload.php';

use Elasticsearch\ClientBuilder;

$client = ClientBuilder::create()->build();

$params = [
    'index' => 'megacorp',
    'type' => 'employee',
    'body' => [
        'query' => [
            'match' => [
                'name' => '用户'
            ]
        ]
    ]
];

$response = $client->search($params);
print_r($response);