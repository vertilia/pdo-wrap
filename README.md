# pdo-wrap - PDO wrapper library to simplify basic DB operations

PDO ([PHP Data Objects](https://php.net/pdo)) is a standard interface to work with databases from PHP applications.
Although it provides a full set of DB-related features for large list of providers, some basic operations seem to lack
simplicity.

This package adds some form of simplicity and elegance to your work with PDO-based DB connections from PHP code.

It features:

- typed placeholders without sacrificing standard placeholders form, recognized by most IDEs,
- transparent typed parameter binding allowing simple chaining operations.

## Example

Quick example for fetching a record set of several rows with inbound named parameter with PDO:

```php
<?php

use PDO;

$pdo = new PDO('sqlite::memory:');
$sth = $pdo->prepare("SELECT name, join_date, acl FROM users WHERE acl IN(:a1,:a2)");
$sth->bindValue(':a1', 9, PDO::PARAM_INT);
$sth->bindValue(':a2', 10, PDO::PARAM_INT);
$sth->execute();
$result = $sth->fetchAll();
```

> Yes, there is no standard PDO way of having automatic array unfolding when specifying lists...

Compare the same operation with `PdoWrap`:

```php
<?php

use PDO;
use Vertilia\PdoWrap\PdoWrap;

$pdo_wrap = new PdoWrap('sqlite::memory:');
$result = $pdo_wrap->queryFetchAll(
    "SELECT name, join_date, acl FROM users WHERE acl IN(:acls)",
    [':acls[i]' => [9, 10]]
);
```

> Two main `PdoWrap` concepts implemented in one example. First, we provided type information by appending `[i]` suffix
> to the name in the list of parameters. Second, `:acls` named parameter was unfurled to include several elements from
> array, since the `[i]` suffix indicates that corresponding value must be represented as a list of integers.
> 
> **Also note:** `:acls` placeholder is in the standard PDO form of named parameters, it will be automatically
> recognized by IDEs as such.

See below for the full list of available types.

# Types reference

`PdoWrap` extends PDO class and adds 3 helper methods that you will use on daily basis:

- `queryFetchAll()` \
  to execute SELECT statements with typed placeholders and return the whole recordset (same as normal
  sequence `PDO::prepare` / `PDOStatement::bindValue` &times; n / `PDOStatement::execute` / `PDOStatement::fetchAll`),
- `queryFetchOne()` \
  to execute SELECT statements with typed placeholders and return the first record (same as normal
  sequence `PDO::prepare` / `PDOStatement::bindValue` &times;
  n / `PDOStatement::execute` / `PDOStatement::fetch` / `PDOStatement::closeCursor`),
- `queryExecute()` \
  to execute DML statements with typed placeholders (same as normal sequence `PDO::prepare` / `PDOStatement::bindValue`
  &times; n / `PDOStatement::execute` / `PDOStatement::rowCount`).

All these methods receive as their first argument an SQL statement with either question-mark placeholders (as in `?`) or
named placeholders (as in `:name`). Second argument is an array with values to use for corresponding placeholders.
Additional arguments representing PDO fetch modes may follow, they will be transmitted to corresponding
`PDOStatement::fetchAll()` or `PDOStatement::fetch()` methods.

For question-mark placeholders typing can not be provided, so the values are stored in a numeric array, exactly like in
standard PDO form `PDO::prepare()->execute()`. This form is kept for compatibility.

For named placeholders, values are passed in associative array, where keys correspond to the name of placeholder in SQL
statement, but have type indicator suffix, like in `:acls[i]`.

Type indicator is a suffix appended to parameter name in the list of parameters passed to corresponding `query*()`
method, and it may be in one of two forms, either in angle brackets (like `<TYPE>`), to specify a _single_ value of
specified type, or in square brackets (like `[TYPE]`), to specify an _array_ of values of specified type.

The following type indicators are available:

| sufix | param type        | example                                                                        |
|-------|-------------------|--------------------------------------------------------------------------------|
| `<i>` | `PDO::PARAM_INT`  | `"... WHERE id = :id", [":id<i>" => 42]`                                       |
| `<s>` | `PDO::PARAM_STR`  | `"... WHERE name = :name", [":name<s>" => 'Mary']`                             |
| `<b>` | `PDO::PARAM_BOOL` | `"... WHERE active = :active", [":active<b>" => true]`                         |
| `[i]` | `PDO::PARAM_INT`  | `"... WHERE id IN(:ids)", [":ids[i]" => [42, 43]]`                             |
| `[s]` | `PDO::PARAM_STR`  | `"... WHERE name IN(:names)", [":names[s]" => ['Mary', 'Juliette']]`           |
| `[b]` | `PDO::PARAM_BOOL` | `"... WHERE (active, connected) IN((:flags))", [":flags[b]" => [true, false]]` |

### Some examples

```php
<?php

$db = new PdoWrap($db_dsn, $db_user, $db_pass, $db_options);

$admins_rows = $db->queryFetchAll('SELECT * FROM users WHERE acl >= ?', [90]);

$admins_list = $db->queryFetchAll(
    'SELECT id, name FROM users WHERE acl >= :acl',
    [':acl<i>' => 90],
    PDO::FETCH_KEY_PAIR
);

$admins_count = $db->queryFetchOne(
    'SELECT COUNT(*) FROM users WHERE acl >= :acl',
    [':acl<i>' => 90],
    PDO::FETCH_COLUMN
);

$admins_updated = $db->queryExecute(
    'UPDATE users SET connected = :conn WHERE acl >= :acl',
    [
        ':acl<i>' => 90,
        ':conn<b>' => false,
    ]
);
```

Please look into `/tests/` for more examples.

More functionality to come, please stay tuned ;-)
