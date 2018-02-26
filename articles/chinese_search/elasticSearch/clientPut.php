<?php

require 'vendor/autoload.php';

use Elasticsearch\ClientBuilder;

$client = ClientBuilder::create()->build();

$params = [
    'index' => 'megacorp',
    'type' => 'employee',
    'id' => 3,
    'body' => [
        'id' => 3,
        'name' => '用户3'
    ]
];

$response = $client->index($params);
print_r($response);