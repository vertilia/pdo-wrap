<?php

declare(strict_types=1);

namespace Vertilia\PdoWrap;

use InvalidArgumentException;
use PDO;
use PDOException;
use PDOStatement;

class PdoWrap extends PDO
{
    /**
     * Prepare a statement for $query and bind parameters by value using provided type indicators.
     *
     * Statements using question-mark placeholders always bind parameters as strings and do not allow array flattening.
     * Statements using named placeholders may be augmented with type, added to param name in the list of parameters.
     * Ex: $query = "SELECT col FROM tbl WHERE id IN(:id)", $params = [":id[i]" => [5, 15]]. Here, param name :id has
     * type indicator [i] that specifies that the param should be bound as array of integers.
     *
     * Available types:
     * - &lt;i&gt; - int
     * - &lt;s&gt; - string
     * - &lt;b&gt; - bool
     * - [i] - array of ints
     * - [s] - array of strings
     * - [b] - array of bools
     * @param string $query in form of either ?-based or :name-based params. Ex: "SELECT col FROM tbl WHERE id IN(:id)"
     * @param array|null $params hash of params for 1st or 2nd form. Ex: [":id[i]" => [5, 15]]
     * @return array array with 2 items: new query and list of params to bind.
     * Ex: [
     *  "SELECT col FROM tbl WHERE id IN(:id0,:id1)",
     *  [[":id0", 5, PDO::PARAM_INT], [":id1", 15, PDO::PARAM_INT]]
     * ]
     * @throws InvalidArgumentException
     */
    public function parseParams(string $query, ?array $params = null): array
    {
        $params_bind = [];

        if ($params) {
            reset($params);
            $k = key($params);
            if (is_int($k)) {
                // ?-based params, no typing
                foreach ($params as $i => $value) {
                    $params_bind[] = [$i + 1, $value, PDO::PARAM_STR];
                }
            } else {
                // named params, typing + array flattening
                foreach ($params as $param => $value) {
                    if (preg_match('/^:?(\w+)([\[<]\w?[>\]])?$/', $param, $m)) {
                        $name = $m[1];
                        $type = match ($m[2] ?? '') {
                            '<i>', '[i]' => PDO::PARAM_INT,
                            '<b>', '[b]' => PDO::PARAM_BOOL,
                            default => PDO::PARAM_STR,
                        };
                        if (is_array($value) and $value and strlen($m[2]) and $m[2][0] === '[') {
                            $param_parts = [];
                            foreach ($value as $k => $v) {
                                $param_parts[] = ":$name$k";
                                $params_bind[] = [":$name$k", $v, $type];
                            }
                            $query = preg_replace("/:$name\\b/", implode(',', $param_parts), $query);
                        } else {
                            $params_bind[] = [":$name", $value, $type];
                        }
                    } else {
                        throw new InvalidArgumentException("Invalid param name: $param");
                    }
                }
            }
        }

        return [$query, $params_bind];
    }

    /**
     * Prepare query, bind input parameters and return the statement ready for execution.
     *
     * @param string $query SQL query to prepare and bind
     * @param array|null $params parameters for placeholders
     * @return PDOStatement statement with bound parameters
     * @throws PDOException
     */
    public function prepareBind(string $query, ?array $params = null): PDOStatement
    {
        [$query_parsed, $params_bound] = $this->parseParams($query, $params);

        $stmt = $this->prepare($query_parsed);
        foreach ($params_bound as $bind) {
            $stmt->bindValue(...$bind);
        }

        return $stmt;
    }

    /**
     * Prepare DML query, bind input parameters, execute query and return the number of affected rows.
     *
     * @param string $query SQL DML query to prepare and execute
     * @param array|null $params parameters for placeholders
     * @return int|false number of affected rows or false on error
     * @throws PDOException
     */
    public function queryExecute(string $query, ?array $params = null): int|false
    {
        $stmt = $this->prepareBind($query, $params);
        return $stmt->execute() ? $stmt->rowCount() : false;
    }

    /**
     * Prepare query, bind input parameters, execute query and fetch all records with fetchAll().
     *
     * @param string $query SQL query to prepare and execute
     * @param array|null $params parameters for placeholders
     * @param mixed ...$args arguments to pass to fetchAll() method
     * @return array|false list or records from the query or false on error
     * @throws PDOException
     */
    public function queryFetchAll(string $query, ?array $params = null, ...$args): array|false
    {
        $stmt = $this->prepareBind($query, $params);
        return $stmt->execute() ? $stmt->fetchAll(...$args) : false;
    }

    /**
     * Prepare query, bind input parameters, execute query, fetch first record with fetch() and close cursor.
     *
     * @param string $query SQL query to prepare and execute
     * @param array|null $params parameters for placeholders
     * @param mixed ...$args arguments to pass to fetch() method
     * @return mixed first record from the query or false on error
     * @throws PDOException
     */
    public function queryFetchOne(string $query, ?array $params = null, ...$args): mixed
    {
        $stmt = $this->prepareBind($query, $params);
        if ($stmt->execute()) {
            $res = $stmt->fetch(...$args);
            $stmt->closeCursor();
            return $res;
        } else {
            return false;
        }
    }
}
