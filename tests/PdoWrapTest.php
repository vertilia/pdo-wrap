<?php

use Vertilia\PdoWrap\PdoWrap;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass PdoWrap
 */
class PdoWrapTest extends TestCase
{
    protected static PDO $pdo;

    public static function setUpBeforeClass(): void
    {
        self::$pdo = new PDO('sqlite::memory:');
        self::$pdo->exec('CREATE TABLE IF NOT EXISTS tbl (id INT UNSIGNED, name VARCHAR(255))');
        self::$pdo->exec("INSERT INTO tbl (id, name) VALUES (1, 'Jon'), (2, 'Mary')");
    }

    public static function tearDownAfterClass(): void
    {
        self::$pdo->exec('DROP TABLE IF EXISTS tbl');
    }

    /**
     * @covers ::getPdo
     * @covers ::prepareBind
     */
    public function testGetPdoPrepareBind()
    {
        $db = new PdoWrap(self::$pdo);
        $this->assertInstanceOf(PDO::class, $db->getPdo());
        $stmt = $db->prepareBind('SELECT id, name FROM tbl WHERE id <= :id_max ORDER BY id', [':id_max' => 2]);
        while (($name = $stmt->fetchColumn(1)) !== false) {
            $this->assertTrue(strlen($name) > 1);
        }
    }

    /**
     * @covers ::parseParams
     * @dataProvider providerParseParams
     */
    public function testParseParams(string $query, $params, string $expected_query, array $expected_params)
    {
        $db = new PdoWrap(self::$pdo);
        [$actual_query, $actual_params] = $db->parseParams($query, $params);
        $this->assertEquals($expected_query, $actual_query);
        $this->assertEquals($expected_params, $actual_params);
    }

    public static function providerParseParams(): array
    {
        return [
            'no placeholders' => [
                'SELECT * FROM tbl',
                null,
                'SELECT * FROM tbl',
                []
            ],

            // question mark placeholders
            'question mark param unset' => [
                'SELECT * FROM tbl WHERE id = ?',
                null,
                'SELECT * FROM tbl WHERE id = ?',
                [],
            ],
            'question mark param set' => [
                'SELECT * FROM tbl WHERE id = ? AND name > ?',
                [5, 'Jon'],
                'SELECT * FROM tbl WHERE id = ? AND name > ?',
                [
                    [1, 5, PDO::PARAM_STR],
                    [2, 'Jon', PDO::PARAM_STR],
                ],
            ],

            // named placeholders
            'named params unset' => [
                'SELECT * FROM tbl WHERE id = :id',
                null,
                'SELECT * FROM tbl WHERE id = :id',
                [],
            ],
            'named params simple' => [
                'SELECT * FROM tbl WHERE id = :id AND name > :name AND active = :active',
                [':id' => 1, 'name' => 'Jon', 'active' => 1],
                'SELECT * FROM tbl WHERE id = :id AND name > :name AND active = :active',
                [
                    [':id', 1, PDO::PARAM_STR],
                    [':name', 'Jon', PDO::PARAM_STR],
                    [':active', 1, PDO::PARAM_STR],
                ],
            ],
            'named params with types' => [
                'SELECT * FROM tbl WHERE id = :id AND name > :name AND active = :active',
                ['id<i>' => 1, ':name<s>' => 'Jon', ':active<b>' => 0],
                'SELECT * FROM tbl WHERE id = :id AND name > :name AND active = :active',
                [
                    [':id', 1, PDO::PARAM_INT],
                    [':name', 'Jon', PDO::PARAM_STR],
                    [':active', 0, PDO::PARAM_BOOL],
                ],
            ],
            'named params with types and arrays' => [
                'SELECT * FROM tbl WHERE id IN(:id) AND name > :name AND active = :active',
                [':id[i]' => [1, 5, 15], ':name<>' => 'Jon', ':active<b>' => null],
                'SELECT * FROM tbl WHERE id IN(:id0,:id1,:id2) AND name > :name AND active = :active',
                [
                    [':id0', 1, PDO::PARAM_INT],
                    [':id1', 5, PDO::PARAM_INT],
                    [':id2', 15, PDO::PARAM_INT],
                    [':name', 'Jon', PDO::PARAM_STR],
                    [':active', null, PDO::PARAM_BOOL],
                ],
            ],
            'named params with same prefix' => [
                'SELECT * FROM tbl WHERE id IN(:id) AND id2 IN(:id_2)',
                [':id[i]' => [1,2], ':id_2[i]' => [2,3]],
                'SELECT * FROM tbl WHERE id IN(:id0,:id1) AND id2 IN(:id_20,:id_21)',
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
     * @covers ::exec
     * @dataProvider providerExec
     */
    public function testExec($expected, string $query, $params)
    {
        $db = new PdoWrap(self::$pdo);
        $this->assertEquals($expected, $db->exec($query, $params));
    }

    public static function providerExec(): array
    {
        return [
            'question mark params' =>
                [2, 'INSERT INTO tbl (id, name) VALUES (?,?), (?,?)', [3, 'Romeo', 4, 'Juliette']],
            'named params' =>
                [1, 'INSERT INTO tbl (id, name) VALUES (:id1, :name1)', ['id1<i>' => 5, 'name1' => 'Sam']],
            'named params with array' =>
                [3, 'DELETE FROM tbl WHERE id IN(:ids)', [':ids[i]' => [3,4,5]]],
        ];
    }

    /**
     * @covers ::queryFetchOne
     * @dataProvider providerQueryFetchOne
     */
    public function testQueryFetchOne($expected, string $query, $params, ...$args)
    {
        $db = new PdoWrap(self::$pdo);
        $this->assertEquals($expected, $db->queryFetchOne($query, $params, ...$args));
    }

    public static function providerQueryFetchOne(): array
    {
        return [
            'question mark params' => [
                [1, 'Jon', 'id' => 1, 'name' => 'Jon'],
                'SELECT id, name FROM tbl WHERE id = ?', [1]
            ],
            'named params' => [
                [1, 'Jon'],
                'SELECT id, name FROM tbl WHERE id = :id', ['id' => 1], PDO::FETCH_NUM
            ],
            'named params with type' => [
                ['id' => 1, 'name' => 'Jon'],
                'SELECT id, name FROM tbl WHERE id = :id', [':id<i>' => 1], PDO::FETCH_ASSOC
            ],
            'without limit' => [
                ['id' => 1, 'name' => 'Jon'],
                'SELECT id, name FROM tbl ORDER BY id LIMIT 1', null, PDO::FETCH_ASSOC
            ],
            'named params with limit' => [
                ['id' => 2, 'name' => 'Mary'],
                'SELECT id, name FROM tbl WHERE id > :id ORDER BY id LIMIT 1', [':id<i>' => 1], PDO::FETCH_ASSOC
            ],
        ];
    }

    /**
     * @covers ::queryFetchAll
     * @dataProvider providerQueryFetchAll
     */
    public function testQueryFetchAll($expected, string $query, $params, ...$args)
    {
        $db = new PdoWrap(self::$pdo);
        $this->assertEquals($expected, $db->queryFetchAll($query, $params, ...$args));
    }

    public static function providerQueryFetchAll(): array
    {
        return [
            'question mark params' => [
                [[1, 'Jon'], [2, 'Mary']],
                'SELECT id, name FROM tbl WHERE id <= ? ORDER BY id', [2], PDO::FETCH_NUM
            ],
            'named params' => [
                [[1, 'Jon'], [2, 'Mary']],
                'SELECT id, name FROM tbl WHERE id <= :id_max ORDER BY id', ['id_max' => 2], PDO::FETCH_NUM
            ],
            'named params with type and column' => [
                ['Jon', 'Mary'],
                'SELECT id, name FROM tbl WHERE id <= :id_max ORDER BY id', [':id_max<i>' => 2], PDO::FETCH_COLUMN, 1
            ],
        ];
    }
}
