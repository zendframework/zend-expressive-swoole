# Using Swoole Tables in your application
Sometimes, you need to share structured data between your message workers and have data outlive your request cycle.
Swoole Tables are designed to do this exactly for you. They require no additional work and are automatically synchronized.

For reasons that will become clear later on, I find it best to create up my tables by extending \Swoole\Table
and then defining the appropriate columns inside of the `__construct()`, along with the table size.

*IMPORTANT* You must call your table's `create()` method. Otherwise your table will not work. I find it easiest to do it
inside of the constructor.


## Creating a table
```
<?php

declare(strict_types=1);

namespace App\Table;

use Swoole\Table;

final class Vec3Table extends Table
{
    public function __construct()
    {
        parent::__construct(1024); //Table size
        $this->column('x', self::TYPE_FLOAT);
        $this->column('y', self::TYPE_FLOAT);
        $this->column('z', self::TYPE_FLOAT);
        $this->create();
    }
}
```

## Creating your table
Creating a swoole table is very straightforward, but it HAS to be created inside of your main process.
By defining the columns inside of the constructor, nothing needs to be done here besides instantiating a new table

```
private function getDependencies() : array
{
    return [
        'services'  => [
            ...
            Vec3Table::class               => new Vec3Table(),
        ],
    ];
}
```
## Using the Table
You are able to retrieve it inside of any worker process by calling `$container->get(Vec3Table::class)`

## Troubleshooting
`PHP Fatal error:  Swoole\Table::offsetSet(): the table object does not exist` then chances are you are not calling $table->create();
