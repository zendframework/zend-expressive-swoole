# zend-expressive-swoole

[![Build Status](https://secure.travis-ci.org/zendframework/zend-expressive-swoole.svg?branch=master)](https://secure.travis-ci.org/zendframework/zend-expressive-swoole)
[![Coverage Status](https://coveralls.io/repos/github/zendframework/zend-expressive-swoole/badge.svg?branch=master)](https://coveralls.io/github/zendframework/zend-expressive-swoole?branch=master)

This library provides the support of [Swoole](https://www.swoole.co.uk/) into
an [Expressive](https://getexpressive.org/) application. This means you can
execute your Expressive application using Swoole directly from the command line.


## Installation

Run the following to install this library:

```bash
$ composer require zendframework/zend-expressive-swoole
```

## Configuration

By default, Swoole executes the HTTP server with host `127.0.0.1` on port
`8080`. You can change these values using a `/config/autoload/swoole.local.php`
file with the following data:

```php
return [
    'swoole' => [
        'host' => 'insert the hostname to use',
        'port' => // insert a integer number
    ]
];
```

Or you can change the `config` key of your service manager.

## Execute

You can run an Expressive application with Swoole using the following command:

```bash
php public/index.php
```

## Documentation

Browse the documentation online at https://docs.zendframework.com/zend-expressive-swoole/

## Support

* [Issues](https://github.com/zendframework/zend-expressive-swoole/issues/)
* [Chat](https://zendframework-slack.herokuapp.com/)
* [Forum](https://discourse.zendframework.com/)
