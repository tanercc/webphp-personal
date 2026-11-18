<?php
defined('BASEPATH') or exit('No direct script access allowed');
require_once __DIR__.'/database.php';
/** Creates every table of a schema definition (database/schema.php shape) in an empty database. Used by tools/install.php only. */
function panelCreateSchema(PanelDbConnection $db, array $schema): void {
    $dialect = $db->dialect();
    $pdo = $db->pdo();
    $statements = [];
    foreach ($schema['tables'] ?? [] as $table => $definition) {
        array_push($statements, ...$dialect->createTableSql((string)$table, $definition));
    }
    if ($dialect->name() === 'mysql') {
        // MySQL checks foreign-key targets at CREATE time and some tables reference `templates`, which is defined later.
        // The new tables are empty, so creating them with checks off cannot admit orphan rows. DDL commits implicitly.
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        try { foreach ($statements as $sql) { $pdo->exec($sql); } }
        finally { $pdo->exec('SET FOREIGN_KEY_CHECKS=1'); }
        return;
    }
    $pdo->beginTransaction();
    try {
        foreach ($statements as $sql) { $pdo->exec($sql); }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}
