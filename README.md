# pdo-wrap - PDO wrapper library to simplify basic DB operations

PDO ([PHP Data Objects](https://php.net/pdo)) is a standard interface to work with databases from PHP applications.
Although it provides a full set of DB-related features for large list of providers, some basic operations seem to lack
simplicity.

This package adds some form of simplicity and elegance to your work with PDO-based DB connections from PHP code.

## Example

Quick example for fetching a record set of several rows with inbound named parameter with `PDO`:

```php
<?php

use PDO;

$pdo = new PDO('sqlite::memory:');
$sth = $pdo->prepare("SELECT name, colour FROM fruit WHERE colour IN(:c1,:c2)");
$sth->bindValue(':c1', 'red', PDO::PARAM_STR);
$sth->bindValue(':c2', 'yellow', PDO::PARAM_STR);
$sth->execute();
$result = $sth->fetchAll();
```

> Yes, there is no way of having automatic array unfolding...

Same with `PdoWrap`:

```php
<?php

use PDO;
use Vertilia\PdoWrap\PdoWrap;

$pdowrap = new PdoWrap(new PDO('sqlite::memory:'));
$result = $pdowrap->queryFetchAll(
    "SELECT name, colour FROM fruit WHERE colour IN(:colours)",
    [':colours[s]' => ['red', 'yellow']]
);
```

> Two main `PdoWrap` concepts implemented in one example. First, we provided type information by appending `[s]` suffix
> to the name in the list of parameters. Second, `:colours` named parameter was unfurled to include several elements
> from array, since the `[s]` suffix indicates that corresponding value must be represented as a list of strings.

See below for the full list of available types.

# Types reference

Type indicator is a suffix appended to parameter name in the list of parameters passed to corresponding `query*()`
method, and it may be in one of two forms, either in angle brackets (like `<TYPE>`), to specify a single value of
specified type, or in square brackets (like `[TYPE]`), to specify an array of values of specified type.

The following type indicators are available:

| sufix | param type        | example                                                                        |
|-------|-------------------|--------------------------------------------------------------------------------|
| `<i>` | `PDO::PARAM_INT`  | `"... WHERE id = :id", [":id<i>" => 42]`                                       |
| `<s>` | `PDO::PARAM_STR`  | `"... WHERE name = :name", [":name<s>" => 'Mary']`                             |
| `<b>` | `PDO::PARAM_BOOL` | `"... WHERE active = :active", [":active<b>" => true]`                         |
| `[i]` | `PDO::PARAM_INT`  | `"... WHERE id IN(:ids)", [":ids[i]" => [42, 43]]`                             |
| `[s]` | `PDO::PARAM_STR`  | `"... WHERE name IN(:names)", [":names<s>" => ['Mary', 'Juliette']]`           |
| `[b]` | `PDO::PARAM_BOOL` | `"... WHERE (active, connected) IN((:flags))", [":flags<b>" => [true, false]]` |

Please look into `/tests/` for more examples.

More functionality to come, please stay tuned ;-)
