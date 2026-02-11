<?php

use Iperamuna\SelfDeploy\Support\ConfigFormatter;

it('formats arrays with custom spacing and double newlines', function () {
    $array = [
        'key1' => 'value1',
        'key2' => [
            'nested1' => true,
            'nested2' => null,
        ],
        'key3' => 123,
    ];

    $expected = "[\n\n".
        "    'key1' => 'value1',\n\n".
        "    'key2' => [\n\n".
        "        'nested1' => true,\n\n".
        "        'nested2' => null,\n\n".
        "    ],\n\n".
        "    'key3' => 123,\n\n".
        ']';

    expect(ConfigFormatter::format($array))->toBe($expected);
});

it('handles numeric keys correctly', function () {
    $array = [
        'first',
        'second',
    ];

    $expected = "[\n\n".
        "    0 => 'first',\n\n".
        "    1 => 'second',\n\n".
        ']';

    expect(ConfigFormatter::format($array))->toBe($expected);
});

it('escapes strings and keys', function () {
    $array = [
        "it's" => "a value with 'quotes'",
    ];

    $expected = "[\n\n".
        "    'it\\'s' => 'a value with \\'quotes\\'',\n\n".
        ']';

    expect(ConfigFormatter::format($array))->toBe($expected);
});

it('formats special config keys with helpers', function () {
    // Mock environment for deterministic test
    putenv('SELF_DEPLOY_USER=test-user');

    $array = [
        'log_dir' => storage_path('self-deployments/logs'),
        'deployment_scripts_path' => base_path('.deployments'),
        'user' => 'test-user', // Should match env
    ];

    $expected = "[\n\n".
        "    'log_dir' => storage_path('self-deployments/logs'),\n\n".
        "    'deployment_scripts_path' => base_path('.deployments'),\n\n".
        "    'user' => env('SELF_DEPLOY_USER'),\n\n".
        ']';

    expect(ConfigFormatter::format($array))->toBe($expected);
});
