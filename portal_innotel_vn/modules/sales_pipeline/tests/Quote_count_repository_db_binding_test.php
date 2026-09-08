<?php

defined('BASEPATH') or define('BASEPATH', 'dummy');

function assert_true($cond, $msg) {
    if (!$cond) throw new RuntimeException("Assertion failed: {$msg}");
}
function assert_equals($expected, $actual, $msg) {
    if ($expected !== $actual) {
        $exp = var_export($expected, true);
        $act = var_export($actual, true);
        throw new RuntimeException("Assertion failed: {$msg} (Expected {$exp}, got {$act})");
    }
}

// Mock database query result
class MockResult {
    private $rows;
    public function __construct(array $rows) { $this->rows = $rows; }
    public function result_array() { return $this->rows; }
}

// Mock database connection
class MockDb {
    public $lastQuery = null;
    public $rowsToReturn = [];

    public function escape($val) {
        return "'" . addslashes((string) $val) . "'";
    }

    public function query($sql) {
        $this->lastQuery = $sql;
        return new MockResult($this->rowsToReturn);
    }
}

// Mock CI Super Object
class MockCiSuper {
    public $db;
    public function __construct(MockDb $db) {
        $this->db = $db;
    }
}

// Mock CI_Model with magic __get (simulating CodeIgniter's CI_Model which has NO __isset)
class MockCiModel {
    protected $ci;
    public function __construct(MockCiSuper $ci) {
        $this->ci = $ci;
    }
    public function __get($name) {
        return $this->ci->$name;
    }
}

$mockDb = new MockDb();
$mockDb->rowsToReturn = [
    ['staff_id' => 22, 'quote_count' => 1],
];
$mockCi = new MockCiSuper($mockDb);

function &get_instance() {
    global $mockCi;
    return $mockCi;
}

function db_prefix() {
    return 'tbl';
}

require_once __DIR__ . '/../libraries/Quote_count_repository.php';

// 1. Verify that empty($model->db) is indeed TRUE in PHP due to missing __isset on CI_Model
$model = new MockCiModel($mockCi);
assert_true(empty($model->db), 'Precondition check: PHP empty($model->db) must be true due to missing __isset on CI_Model');

// 2. Instantiate Quote_count_repository with MockCiModel passed in
$repoWithModel = new Quote_count_repository($model);
$countsWithModel = $repoWithModel->get_counts_by_staff([22], '2026-09-01 00:00:00', '2026-10-01 00:00:00');
assert_equals(['22' => 1], $countsWithModel, 'Quote_count_repository must successfully execute query even when passed CI_Model instance');

// 3. Instantiate Quote_count_repository with no arguments (uses get_instance())
$repoNoArg = new Quote_count_repository();
$countsNoArg = $repoNoArg->get_counts_by_staff([22], '2026-09-01 00:00:00', '2026-10-01 00:00:00');
assert_equals(['22' => 1], $countsNoArg, 'Quote_count_repository must successfully execute query when instantiated without arguments');

echo "Quote_count_repository_db_binding_test: PASSED\n";
