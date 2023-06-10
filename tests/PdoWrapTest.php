<?php

use Vertilia\PdoWrap\PdoWrap;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass PdoWrap
 */
class PdoWrapTest extends TestCase
{
    protected static PdoWrap $pdo_wrap;

    public static function setUpBeforeClass(): void
    {
        self::$pdo_wrap = new PdoWrap('sqlite::memory:');
        self::$pdo_wrap->exec('CREATE TABLE IF NOT EXISTS test_tbl (id INT UNSIGNED, name VARCHAR(255))');
        self::$pdo_wrap->exec("INSERT INTO test_tbl (id, name) VALUES (1, 'Jon'), (2, 'Mary')");
    }

    public static function tearDownAfterClass(): void
    {
        self::$pdo_wrap->exec('DROP TABLE IF EXISTS test_tbl');
    }

    /**
     * @covers PdoWrap::prepareBind
     */
    public function testPrepareBind()
    {
        $this->assertInstanceOf(PDO::class, self::$pdo_wrap);
        $stmt = self::$pdo_wrap->prepareBind(
            'SELECT id, name FROM test_tbl WHERE id <= :id_max ORDER BY id',
            [':id_max' => 2]
        );
        while (($name = $stmt->fetchColumn(1)) !== false) {
            $this->assertContains($name, ['Jon', 'Mary']);
        }
    }

    /**
     * @covers PdoWrap::parseParams
     * @dataProvider providerParseParams
     */
    public function testParseParams(string $query, $params, string $expected_query, array $expected_params)
    {
        [$actual_query, $actual_params] = self::$pdo_wrap->parseParams($query, $params);
        $this->assertSame($expected_query, $actual_query);
        $this->assertSame($expected_params, $actual_params);
    }

    public static function providerParseParams(): array
    {
        return [
            'no placeholders' => [
                'SELECT * FROM test_tbl',
                null,
                'SELECT * FROM test_tbl',
                []
            ],

            // question mark placeholders
            'question mark param unset' => [
                'SELECT * FROM test_tbl WHERE id = ?',
                null,
                'SELECT * FROM test_tbl WHERE id = ?',
                [],
            ],
            'question mark param set' => [
                'SELECT * FROM test_tbl WHERE id = ? AND name > ?',
                [5, 'Jon'],
                'SELECT * FROM test_tbl WHERE id = ? AND name > ?',
                [
                    [1, 5, PDO::PARAM_STR],
                    [2, 'Jon', PDO::PARAM_STR],
                ],
            ],

            // named placeholders
            'named params unset' => [
                'SELECT * FROM test_tbl WHERE id = :id',
                null,
                'SELECT * FROM test_tbl WHERE id = :id',
                [],
            ],
            'named params simple' => [
                'SELECT * FROM test_tbl WHERE id = :id AND name > :name AND active = :active',
                [':id' => 1, 'name' => 'Jon', 'active' => 1],
                'SELECT * FROM test_tbl WHERE id = :id AND name > :name AND active = :active',
                [
                    [':id', 1, PDO::PARAM_STR],
                    [':name', 'Jon', PDO::PARAM_STR],
                    [':active', 1, PDO::PARAM_STR],
                ],
            ],
            'named params with types' => [
                'SELECT * FROM test_tbl WHERE id = :id AND name > :name AND active = :active',
                ['id<i>' => 1, ':name<s>' => 'Jon', ':active<b>' => 0],
                'SELECT * FROM test_tbl WHERE id = :id AND name > :name AND active = :active',
                [
                    [':id', 1, PDO::PARAM_INT],
                    [':name', 'Jon', PDO::PARAM_STR],
                    [':active', 0, PDO::PARAM_BOOL],
                ],
            ],
            'named params with types and arrays' => [
                'SELECT * FROM test_tbl WHERE id IN(:id) AND name > :name AND active = :active',
                [':id[i]' => [1, 5, 15], ':name<>' => 'Jon', ':active<b>' => null],
                'SELECT * FROM test_tbl WHERE id IN(:id0,:id1,:id2) AND name > :name AND active = :active',
                [
                    [':id0', 1, PDO::PARAM_INT],
                    [':id1', 5, PDO::PARAM_INT],
                    [':id2', 15, PDO::PARAM_INT],
                    [':name', 'Jon', PDO::PARAM_STR],
                    [':active', null, PDO::PARAM_BOOL],
                ],
            ],
            'named params with same prefix' => [
                'SELECT * FROM test_tbl WHERE id IN(:id) AND id2 IN(:id_2)',
                [':id[i]' => [1,2], ':id_2[i]' => [2,3]],
                'SELECT * FROM test_tbl WHERE id IN(:id0,:id1) AND id2 IN(:id_20,:id_21)',
                [
                    [':id0', 1, PDO::PARAM_INT],
                    [':id1', 2, PDO::PARAM_INT],
                    [':id_20', 2, PDO::PARAM_INT],
                    [':id_21', 3, PDO::PARAM_INT],
                ],
            ],
        ];
    }

    /**
     * @covers PdoWrap::queryExecute
     * @dataProvider providerQueryExecute
     */
    public function testQueryExecute($expected, string $query, $params)
    {
        $this->assertSame($expected, self::$pdo_wrap->queryExecute($query, $params));
    }

    public static function providerQueryExecute(): array
    {
        return [
            'question mark params' =>
                [2, 'INSERT INTO test_tbl (id, name) VALUES (?,?), (?,?)', [3, 'Romeo', 4, 'Juliette']],
            'named params' =>
                [1, 'INSERT INTO test_tbl (id, name) VALUES (:id1, :name1)', ['id1<i>' => 5, 'name1' => 'Sam']],
            'named params with array' =>
                [3, 'DELETE FROM test_tbl WHERE id IN(:ids)', [':ids[i]' => [3,4,5]]],
        ];
    }

    /**
     * @covers PdoWrap::queryFetchOne
     * @dataProvider providerQueryFetchOne
     */
    public function testQueryFetchOne($expected, string $query, $params, ...$args)
    {
        $this->assertSame($expected, self::$pdo_wrap->queryFetchOne($query, $params, ...$args));
    }

    public static function providerQueryFetchOne(): array
    {
        return [
            'question mark params' => [
                ['id' => 1, 1, 'name' => 'Jon', 'Jon'],
                'SELECT id, name FROM test_tbl WHERE id = ?', [1]
            ],
            'named params' => [
                [1, 'Jon'],
                'SELECT id, name FROM test_tbl WHERE id = :id', ['id' => 1], PDO::FETCH_NUM
            ],
            'named params with type' => [
                ['id' => 1, 'name' => 'Jon'],
                'SELECT id, name FROM test_tbl WHERE id = :id', [':id<i>' => 1], PDO::FETCH_ASSOC
            ],
            'without limit' => [
                ['id' => 1, 'name' => 'Jon'],
                'SELECT id, name FROM test_tbl ORDER BY id LIMIT 1', null, PDO::FETCH_ASSOC
            ],
            'named params with limit' => [
                ['id' => 2, 'name' => 'Mary'],
                'SELECT id, name FROM test_tbl WHERE id > :id ORDER BY id LIMIT 1', [':id<i>' => 1], PDO::FETCH_ASSOC
            ],
        ];
    }

    /**
     * @covers PdoWrap::queryFetchAll
     * @dataProvider providerQueryFetchAll
     */
    public function testQueryFetchAll($expected, string $query, $params, ...$args)
    {
        $this->assertSame($expected, self::$pdo_wrap->queryFetchAll($query, $params, ...$args));
    }

    public static function providerQueryFetchAll(): array
    {
        return [
            'question mark params' => [
                [[1, 'Jon'], [2, 'Mary']],
                'SELECT id, name FROM test_tbl WHERE id <= ? ORDER BY id', [2], PDO::FETCH_NUM
            ],
            'named params' => [
                [[1, 'Jon'], [2, 'Mary']],
                'SELECT id, name FROM test_tbl WHERE id <= :id_max ORDER BY id', ['id_max' => 2], PDO::FETCH_NUM
            ],
            'named params with type and column' => [
                ['Jon', 'Mary'],
                'SELECT id, name FROM test_tbl WHERE id <= :id_max ORDER BY id', [':id_max<i>' => 2], PDO::FETCH_COLUMN, 1
            ],
        ];
    }
}
