# Laravel Tuya Client
A Simple Tuya API client for Laravel

## Install
```
composer require zhaowe1/laravel-tuya-client
```

## Example

```
<?php

require __DIR__ . '/vendor/autoload.php';

$config = [
    'endpoint' => 'https://openapi.tuyacn.com',
    'access_id' => '',
    'access_secret' => '',
];
$client = LaravelTuyaClient\TuyaClient::getClient($config);


// 查询用户家庭列表
$uid = 'ay00000000';
$res = $client->send('GET', '/v1.0/users/{uid}/homes', [
        'params' => [
            'uid' => $uid
        ]
    ]
);
print_r($res);

// 获取设备日志上报
$deviceId = '';
$startTime = '';
$endTime = '';
$res = $client->send('GET', '/v1.0/devices/{device_id}/logs', [
    'params' => [
        'device_id' => $deviceId
    ],
    'query' => [
        'type' => 7,
        'start_time' => $startTime,
        'end_time' => $endTime
    ],
]);
print_r($res);

// 下发设备指令
$deviceId = '';
$commandArr = [];
$res = $client->send('POST', '/v1.0/devices/{device_id}/commands', [
    'params' => ['device_id' => $deviceId],
    'body' => ['commands' => $commandArr],
]);
print_r($res);

```
