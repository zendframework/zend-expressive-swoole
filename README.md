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

After installing zend-expressive-swoole, you will need to first enable the
component, and then optionally configure it.

We recommend adding a new configuration file to your autoload directory,
`config/autoload/swoole.local.php`. To begin with, use the following contents:

```php
<?php

use Zend\Expressive\Swoole\ConfigProvider;

return array_merge((new ConfigProvider())(), []);
```

The above will setup the Swoole integration for your application.

By default, Swoole executes the HTTP server with host `127.0.0.1` on port
`8080`. You can change these values via configuration. Assuming you have the
above, modify it to read as follows:

```php
<?php

use Zend\Expressive\Swoole\ConfigProvider;

return array_merge((new ConfigProvider())(), [
    'swoole' => [
        'host' => 'insert hostname to use here',
        'port' => 80, // use an integer value here
    ],
]);
```

## Execute

Once you have performed the configuration steps as outlined above, you can run
an Expressive application with Swoole using the following command:

```bash
php public/index.php
```

## Documentation

Browse the documentation online at https://docs.zendframework.com/zend-expressive-swoole/

## Support

* [Issues](https://github.com/zendframework/zend-expressive-swoole/issues/)
* [Chat](https://zendframework-slack.herokuapp.com/)
* [Forum](https://discourse.zendframework.com/)
