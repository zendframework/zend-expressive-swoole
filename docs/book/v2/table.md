# Using Swoole Tables in Your Application

Sometimes, you need to share structured data between your message workers and
have data outlive your request cycle. [Swoole Tables](https://www.swoole.co.uk/docs/modules/swoole-table)
are designed to do this for you. They require no additional work and are
automatically synchronized.

For reasons that will become clear presently, we recommend creating memory
tables by extending the `Swoole\Table` class, defining the appropriate columns
and table size inside of the constructor.

> ### Initialize the Table Within the Constructor
>
> You **must** call your table's `create()` method, and this **must** be done
> prior to initializing any worker processes; if you fail to do so, your table
> will not work. We recommend doing this in your table class's constructor.


## Creating a Table

As an example of a custom table class, consider the following example, which
defines a table that can contain up to 1024 rows, each with three columns
accepting `float` values to define a 3-dimensional vector, e.g.
`src/App/Table/Vector3dTable.php`:

```php
namespace App\Table;

use Swoole\Table;

final class Vector3dTable extends Table
{
    public function __construct()
    {
        parent::__construct(1024); // Table size
        $this->column('x', self::TYPE_FLOAT);
        $this->column('y', self::TYPE_FLOAT);
        $this->column('z', self::TYPE_FLOAT);
        $this->create();
    }
}
```

## Creating Your Table

Now that we have defined a table class, we need to wire the application to use
it.

Tables **must** be created inside of your main process, in order to ensure each
worker process has access to them. Since we define the columns and table size in
the constructor, we can accomplish this by mapping the service name to a
concrete instance, using the `services` dependency configuration key in a 
config provider class, e.g. `src/App/ConfigProvider.php`:

```php
private function getDependencies() : array
{
    return [
        'services'  => [
            // ...
            Vector3dTable::class => new Vector3dTable(),
        ],
    ];
}
```

## Using the Table

Classes that will push values to or pull values from the table can compose an
instance of your custom class just as they normally would. Factories will then
fetch the instance using `$container->get(Vector3dTable::class)` (to use our
previous example).

## Troubleshooting

If you receive the message `PHP Fatal error:  Swoole\Table::offsetSet(): the
table object does not exist`, then chances are you are not calling
`$table->create()` in your custom table's constructor.
