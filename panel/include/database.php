<?php
defined('BASEPATH') or exit('No direct script access allowed');
require_once __DIR__.'/db_dialect.php';
/** Driver-neutral PDO connection exposing the small result/statement interface consumed by this CMS.
 * SQL text is passed through unchanged; engine differences live in the injected PanelDbDialect.
 */
final class PanelDbResult {
    private int $offset = 0;
    public int $num_rows;
    public function __construct(private array $rows) { $this->num_rows = count($rows); }
    public function fetch_assoc(): array|false { return $this->rows[$this->offset++] ?? false; }
    public function fetch_row(): array|false { $row = $this->fetch_assoc(); return $row === false ? false : array_values($row); }
    public function fetch_array(): array|false { $row = $this->fetch_assoc(); return $row === false ? false : array_merge(array_values($row), $row); }
    public function fetch_all(int $mode = PDO::FETCH_ASSOC): array { return $this->rows; }
}

final class PanelDbStatement {
    public int $field_count = 0;
    private array $bound = [];
    private PanelDbResult|bool $result = false;
    public function __construct(private PanelDbConnection $db, private string $sql) {}
    public function bind_param(string $types, mixed &...$values): void { $this->bound = &$values; }
    public function execute(?array $values = null): bool {
        $this->result = $this->db->execute_query($this->sql, $values ?? $this->bound);
        $this->field_count = $this->result instanceof PanelDbResult ? 1 : 0;
        return true;
    }
    public function get_result(): PanelDbResult {
        if (!$this->result instanceof PanelDbResult) { throw new RuntimeException('No result'); }
        return $this->result;
    }
    public function close(): void {}
}

final class PanelDbConnection {
    public int $insert_id = 0;
    public int $affected_rows = 0;
    private PDO $pdo;

    public function __construct(private PanelDbDialect $dialect, #[\SensitiveParameter] array $config) {
        $this->pdo = $dialect->connect($config);
    }
    public function dialect(): PanelDbDialect { return $this->dialect; }
    public function pdo(): PDO { return $this->pdo; }
    public function columns(string $table): array { return $this->dialect->columns($this->pdo, $table); }
    public function tableExists(string $table): bool { return $this->dialect->tableExists($this->pdo, $table); }
    public function upsert(string $table, array $row, array $keyColumns): void {
        $this->execute_query($this->dialect->upsertSql($table, array_keys($row), $keyColumns), array_values($row));
    }
    public function query(string $sql): PanelDbResult|bool { return $this->execute_query($sql); }
    public function prepare(string $sql): PanelDbStatement { return new PanelDbStatement($this, $sql); }
    public function execute_query(string $sql, array $values = []): PanelDbResult|bool {
        $statement = $this->pdo->prepare($sql);
        foreach (array_values($values) as $i => $value) {
            $statement->bindValue($i + 1, $value, $value === null ? PDO::PARAM_NULL : (is_int($value) || is_bool($value) ? PDO::PARAM_INT : PDO::PARAM_STR));
        }
        $statement->execute();
        $this->affected_rows = $statement->rowCount();
        if ($statement->columnCount()) { return new PanelDbResult($statement->fetchAll(PDO::FETCH_ASSOC)); }
        $this->insert_id = (int)$this->pdo->lastInsertId();
        return true;
    }
    public function begin_transaction(): void { $this->pdo->beginTransaction(); }
    public function commit(): void { $this->pdo->commit(); }
    public function rollback(): void { if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); } }
}

/** Connection settings from config/site.php `database`, overridden by PANEL_DB_* environment variables.
 * The MySQL password comes only from PANEL_DB_PASSWORD or `$databasePassword` in config/config.runtime.php. */
function panelDatabaseConfig(): array {
    $installation = $GLOBALS['installation'] ?? require dirname(__DIR__, 2).'/config/site.php';
    $config = (array)($installation['database'] ?? []);
    $env = static fn (string $name): ?string => ($value = getenv($name)) === false || $value === '' ? null : $value;
    $driver = $env('PANEL_DB_DRIVER') ?? (string)($config['driver'] ?? 'sqlite');
    if ($driver === 'sqlite') {
        return ['driver' => 'sqlite', 'file' => $env('PANEL_DB_FILE') ?? $env('PERSONAL_DB_FILE') ?? (string)($config['file'] ?? '')];
    }
    $password = getenv('PANEL_DB_PASSWORD');
    if ($password === false) { $password = $GLOBALS['databasePassword'] ?? panelRuntimeDatabasePassword(); }
    return [
        'driver' => $driver,
        'host' => $env('PANEL_DB_HOST') ?? (string)($config['host'] ?? '127.0.0.1'),
        'port' => (int)($env('PANEL_DB_PORT') ?? ($config['port'] ?? 3306)),
        'name' => $env('PANEL_DB_NAME') ?? (string)($config['name'] ?? ''),
        'user' => $env('PANEL_DB_USER') ?? (string)($config['user'] ?? ''),
        'password' => (string)$password,
        'charset' => 'utf8mb4',
    ];
}

/** Reads `$databasePassword` from config/config.runtime.php in an isolated scope (CLI tools that skip config.php). */
function panelRuntimeDatabasePassword(): string {
    $file = dirname(__DIR__, 2).'/config/config.runtime.php';
    if (!is_file($file)) { return ''; }
    return (string)(static function (string $file) { require $file; return $databasePassword ?? ''; })($file);
}

function panelDatabaseConnection(): PanelDbConnection {
    static $db;
    if ($db === null) {
        $config = panelDatabaseConfig();
        $db = new PanelDbConnection(panelDbDialect($config['driver']), $config);
    }
    return $db;
}
