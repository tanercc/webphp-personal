<?php
defined('BASEPATH') or exit('No direct script access allowed');
require_once __DIR__.'/database.php';
/** Creates every table of a schema definition (database/schema.php shape) in an empty database. Used by tools/install.php only.
 * $created receives the tables this call actually created, also when it throws part-way (MySQL DDL commits implicitly;
 * on SQLite the transaction is rolled back, so nothing remains and the list is empty). */
function panelCreateSchema(PanelDbConnection $db, array $schema, ?array &$created = null): void {
    $dialect = $db->dialect();
    $pdo = $db->pdo();
    $created = [];
    $plan = [];
    foreach ($schema['tables'] ?? [] as $table => $definition) { $plan[(string)$table] = $dialect->createTableSql((string)$table, $definition); }
    $run = static function () use ($pdo, $plan, &$created): void {
        foreach ($plan as $table => $statements) {
            $pdo->exec(array_shift($statements)); // CREATE TABLE
            $created[] = $table;
            foreach ($statements as $sql) { $pdo->exec($sql); } // its indexes
        }
    };
    if ($dialect->name() === 'mysql') {
        // MySQL checks foreign-key targets at CREATE time and some tables reference `templates`, which is defined later.
        // The new tables are empty, so creating them with checks off cannot admit orphan rows.
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        try { $run(); }
        finally { $pdo->exec('SET FOREIGN_KEY_CHECKS=1'); }
        return;
    }
    $pdo->beginTransaction();
    try {
        $run();
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $created = [];
        throw $e;
    }
}
