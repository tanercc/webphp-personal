<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');

//=======================================================
// DB connections
//=======================================================
require_once __DIR__.'/sqlite.php';
$conn=panelSqliteConnection();

// mysqli query
//=======================================================
function sqlQuery($sqlstring, ?array $params = null)
{
  global $conn;
  try {
    if (!$conn) { throw new RuntimeException('Database unavailable'); }
    $result = $conn->execute_query($sqlstring, $params ?? []);
    if (!$result) { throw new RuntimeException('Query failed'); }
    return $result;
  } catch (Throwable $error) {
    // Never put SQL, bound values or connection details in an exception/log.
    error_log('CMS database operation failed; code=' . (int) $error->getCode());
    throw new RuntimeException('Database operation failed', (int) $error->getCode());
  }
}

function dbSqlLimit($pageIndex, $pageSize): string
{
  $pageIndex = dbSqlInteger($pageIndex); $pageSize = dbSqlInteger($pageSize);
  if ($pageIndex === 0 || $pageSize === 0) { return ''; }
  if (($pageIndex - 1) > intdiv(PHP_INT_MAX, $pageSize)) { throw new InvalidArgumentException('Pagination overflow'); }
  return ' LIMIT ' . (($pageIndex - 1) * $pageSize) . ', ' . $pageSize;
}

function dbSqlIdentifier($name): string
{
  if (!is_string($name) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $name)) { throw new InvalidArgumentException('Invalid SQL identifier'); }
  return $name;
}

function dbSqlInteger($value, int $minimum = 0): int
{
  if ((!is_int($value) && !is_string($value)) || filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimum]]) === false) {
    throw new InvalidArgumentException('Invalid integer');
  }
  return (int) $value;
}

function dbLegacyReadRow(array $row): array
{
  return array_map(static fn ($value) => is_int($value) || is_float($value) ? (string) $value : $value, $row);
}

//=======================================================
// get languages table
//=======================================================
function dbGetLanguages()
{
  $sqlstring = "SELECT * FROM `langs` WHERE `is_active`=1 AND `deleted`=0 ORDER BY `sort_order`, `id` ASC";
  $sqlresult = sqlQuery($sqlstring);
  $resultarr = [];
  while ($row = $sqlresult->fetch_assoc()) {
    $id = $row['id'];
    $resultarr[$id] = $row;
  }
  return $resultarr;
}

//=======================================================
// get variables table selected lang
//=======================================================
function dbGetSiteVariables($langId = 1)
{
  $langId = dbSqlInteger($langId);
  $sqlstring = "SELECT `variables`.`name` as `key`,
  COALESCE(`variable_lang`.`name`, `variables`.`name`) as `value`
  FROM `variables`
  LEFT JOIN `variable_lang`
  ON `variable_lang`.`row_id`=`variables`.`id`
  AND `variable_lang`.`lang_id`=?
  WHERE `variables`.`deleted`=0";
  $sqlresult = sqlQuery($sqlstring, [$langId]);
  $resultarr = [];
  while ($row = $sqlresult->fetch_assoc()) {
    $key = $row['key'];
    $resultarr[$key] = $row['value'];
  }
  return $resultarr;
}

//=======================================================
// get variables table jsGrid format compatible
//=======================================================
function dbGetVariablesData($options = [])
{
  if (!is_array($options)) { throw new InvalidArgumentException('Invalid list options'); }
  $fields = ['id', 'name', 'is_active', 'sort_order'];
  $sortField = $options['sortField'] ?? 'sort_order';
  $sortOrder = $options['sortOrder'] ?? 'ASC';
  if (!in_array($sortField, $fields, true) || !is_string($sortOrder) || !in_array(strtoupper($sortOrder), ['ASC', 'DESC'], true)) { throw new InvalidArgumentException('Invalid list sort'); }
  $sortOrder = strtoupper($sortOrder);
  $deleted = dbSqlInteger($options['deleted'] ?? 0);
  $pageIndex = dbSqlInteger($options['pageIndex'] ?? 0);
  $pageSize = dbSqlInteger($options['pageSize'] ?? 0);
  unset($options['sortField'], $options['sortOrder'], $options['deleted'], $options['pageIndex'], $options['pageSize']);
  $languages = dbGetLanguages(); $params = []; $aliases = [];
  $sql = "SELECT `variables`.`id`,`variables`.`name`,`variables`.`is_active`,`variables`.`sort_order`";
  foreach ($languages as $language) {
    $alias = $language['slug'];
    if (!is_string($alias) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/D', $alias)) { throw new InvalidArgumentException('Invalid language alias'); }
    $aliases[] = $alias;
    $sql .= ", (SELECT `name` FROM `variable_lang` WHERE `lang_id`=? AND `row_id`=`variables`.`id`) AS `$alias`";
    $params[] = dbSqlInteger($language['id']);
  }
  $sql .= " FROM `variables` WHERE `deleted`=?"; $params[] = $deleted;
  foreach ($options as $key => $value) {
    if (!is_scalar($value) && $value !== null) { throw new InvalidArgumentException('Invalid filter value'); }
    if ($key === 'q') { continue; }
    if (!in_array($key, $fields, true)) { throw new InvalidArgumentException('Unknown filter field'); }
    $sql .= " AND `variables`.`$key`=?"; $params[] = $value;
  }
  if (array_key_exists('q', $options)) {
    $clauses = ['`name` LIKE ?']; $params[] = (string) $options['q'] . '%';
    foreach ($aliases as $alias) { $clauses[] = "`$alias` LIKE ?"; $params[] = (string) $options['q'] . '%'; }
    $sql .= ' HAVING (' . implode(' OR ', $clauses) . ')';
  }
  $sql .= " ORDER BY `variables`.`$sortField` $sortOrder, `variables`.`id`";
  $sql .= dbSqlLimit($pageIndex, $pageSize);
  $result = sqlQuery($sql, $params); $rows = [];
  while ($row = $result->fetch_assoc()) { $rows[$row['id']] = dbLegacyReadRow($row); }
  return $rows;
}

//=======================================================
// get *_lang single row for main table
//=======================================================
function dbGetTableLangRow($langTable, $dataId, $langId)
{
  if (empty($dataId) && empty($langId)) return [];
  $langTable = dbSqlIdentifier($langTable);
  $dataId = dbSqlInteger($dataId); $langId = dbSqlInteger($langId);
  $sqlstring = "SELECT * FROM `$langTable` WHERE `row_id`=? AND `lang_id`=?";
  $sqlresult = sqlQuery($sqlstring, [$dataId, $langId]);
  if ($row = $sqlresult->fetch_assoc()) {
    return dbLegacyReadRow($row);
  } else return [];
}

//=======================================================
// get single row data
//=======================================================
function dbGetTableRow($table, $id)
{
  if (empty($id)) return [];

  $table = dbSqlIdentifier($table); $id = dbSqlInteger($id);
  $sqlstring = "SELECT * FROM `$table` WHERE `id`=?";
  $sqlresult = sqlQuery($sqlstring, [$id]);
  if ($row = $sqlresult->fetch_assoc()) {
    return dbLegacyReadRow($row);
  } else return [];
}

//=======================================================
// get data table array jsGrid format compatible
//=======================================================
function dbGetTableData($table, $fields = null, $options = [])
{
  if (!is_array($options) || ($fields !== null && !is_array($fields))) { throw new InvalidArgumentException('Invalid list options'); }
  $table = dbSqlIdentifier($table);
  $deleted = 0;
  $sortField = 'sort_order';
  $sortOrder = 'ASC';
  $pageIndex = 1;
  $pageSize = 0;
  $typeTable = null;
  $catTable = null;
  if ($options && is_array($options)) {
    foreach ($options as $key => $value) {
      switch ($key) {
        case 'deleted':
          $deleted = $value;
          unset($options[$key]);
          break;
        case 'sortField':
          $sortField = $value;
          unset($options[$key]);
          break;
        case 'sortOrder':
          $sortOrder = $value;
          unset($options[$key]);
          break;
        case 'pageIndex':
          $pageIndex = $value;
          unset($options[$key]);
          break;
        case 'pageSize':
          $pageSize = $value;
          unset($options[$key]);
          break;
        case 'typeTable':
          $typeTable = $value;
          unset($options[$key]);
          break;
        case 'catTable':
          $catTable = $value;
          unset($options[$key]);
          break;
      }
    }
  }

  $tableFields = dbGetTableFieldNames($table);

  $deleted = dbSqlInteger($deleted);
  $pageIndex = dbSqlInteger($pageIndex); $pageSize = dbSqlInteger($pageSize);
  if (!in_array($sortField, array_merge(['id'], $tableFields), true) || !is_string($sortOrder) || !in_array(strtoupper($sortOrder), ['ASC', 'DESC'], true)) { throw new InvalidArgumentException('Invalid list sort'); }
  $sortOrder = strtoupper($sortOrder);
  if ($typeTable) { $typeTable = dbSqlIdentifier($typeTable); }
  if ($catTable) { $catTable = dbSqlIdentifier($catTable); }
  $params = [$deleted];
  $sqlstring = "SELECT SQL_CALC_FOUND_ROWS ";
  if ($fields && is_array($fields)) {
    $fields = array_values(array_intersect($fields, $tableFields));
    array_walk($fields, function (&$f) {
      $f = "`$f`";
    });
    $sqlstring .= "`id`" . ($fields ? "," . implode(',', $fields) : "");
  } else {
    $sqlstring .= "*";
  }

  if ($typeTable) {
    $sqlstring .= ",(SELECT `$typeTable`.`name` FROM `$typeTable` WHERE `$typeTable`.`id`=`$table`.`type_id`) AS `type`";
  }

  if ($catTable) {
    $sqlstring .= ",(SELECT `$catTable`.`name` FROM `$catTable` WHERE `$catTable`.`id`=`$table`.`cat_id`) AS `type`";
  }

  $sqlstring .= " FROM `$table`
    WHERE `$table`.`deleted`=? ";

  if ($options && is_array($options)) {
    foreach ($options as $key => $value) {
      if (!is_scalar($value) && $value !== null) { throw new InvalidArgumentException("Invalid filter value"); }
      if ($key == 'q') {
        $sqlstring .= " AND `$table`.`name` LIKE ?";
        $params[] = (string) $value . "%";
      } else if (in_array($key, array_merge(["id"], $tableFields), true)) {
        $sqlstring .= " AND `$table`.`$key`=?";
        $params[] = $value;
      } else { throw new InvalidArgumentException("Unknown filter field"); }
    }
  }

  $sqlstring .= " ORDER BY `$table`.`$sortField` $sortOrder, `$table`.`id`";

  $sqlstring .= dbSqlLimit($pageIndex, $pageSize);

  $sqlresult = sqlQuery($sqlstring, $params);
  $resultarr = [];
  while ($row = $sqlresult->fetch_assoc()) {
    $idx = $row['id'];
    $resultarr[$idx] = dbLegacyReadRow($row);
  }
  return $resultarr;
}

//=======================================================
// get data table found rows
//=======================================================
function dbGetFoundRows()
{
  $sqlresult = sqlQuery("SELECT FOUND_ROWS();");
  $resultArray=$sqlresult->fetch_array();
  return $resultArray["FOUND_ROWS()"];
}

//=======================================================
// get data table count
//=======================================================
function dbGetRowCount($table)
{
  $table = dbSqlIdentifier($table);
  $sqlresult = sqlQuery("SELECT COUNT(*) FROM `$table`;");
  $result=$sqlresult->fetch_row();
  return $result[0];
}

//=======================================================
// get data table array
//=======================================================
function dbGetRawTableData($table, $is_active = 1, $deleted = 0)
{
  $table = dbSqlIdentifier($table);
  $sqlresult = sqlQuery("SELECT * FROM `$table` WHERE `is_active`=? AND `deleted`=?", [dbSqlInteger($is_active), dbSqlInteger($deleted)]);
  $resultarr = [];
  while ($row = $sqlresult->fetch_assoc()) {
    $idx = $row['id'];
    $resultarr[$idx] = dbLegacyReadRow($row);
  }
  return $resultarr;
}

//=======================================================
// get table field names array with types
//=======================================================
function dbGetTableFieldNames($table)
{
  $table = dbSqlIdentifier($table);
  $exclude_fields = ['id', 'row_id', 'lang_id', 'deleted', 'created_at', 'updated_at'];
  $sqlresult = sqlQuery("SHOW COLUMNS FROM `$table`");
  $resultarr = [];
  while ($row = $sqlresult->fetch_assoc()) {
    if (!in_array($row['Field'], $exclude_fields)) $resultarr[] = dbSqlIdentifier($row['Field']);
  }
  return $resultarr;
}

//=======================================================
// get table field names list
//=======================================================
function dbGetTableFields($table)
{
  $table = dbSqlIdentifier($table);
  $exclude_fields = ['id', 'row_id', 'lang_id', 'deleted', 'created_at', 'updated_at'];
  $sqlresult = sqlQuery("SHOW COLUMNS FROM `$table`");
  $resultarr = [];
  while ($row = $sqlresult->fetch_assoc()) {
    dbSqlIdentifier($row['Field']);
    if (!in_array($row['Field'], $exclude_fields)) $resultarr[] = $row;
  }
  return $resultarr;
}

//=======================================================
// insert row from key=>value list
//=======================================================
function dbLegacyWriteValues($table, array $fields): array
{
  $table = dbSqlIdentifier($table);
  $columns = [];
  $result = sqlQuery("SHOW COLUMNS FROM `$table`");
  while ($row = $result->fetch_assoc()) { $columns[] = dbSqlIdentifier($row['Field']); }
  $values = [];
  foreach ($fields as $key => $value) {
    if ($key === 'id') { continue; }
    dbSqlIdentifier($key);
    if (!in_array($key, $columns, true) || (!is_scalar($value) && $value !== null)) { throw new InvalidArgumentException('Invalid write field'); }
    if (str_ends_with($key, '_id') && ($value === '' || $value === 0 || $value === '0' || $value === 'NULL')) { $value = null; }
    if (str_ends_with($key, '_at') && $value !== null) {
      if ($value === '') { $value = null; }
      else {
        $timestamp = strtotime((string) $value);
        $parsed = date_parse((string) $value);
        if ($timestamp === false || $parsed['error_count'] || $parsed['warning_count']) { throw new InvalidArgumentException('Invalid date'); }
        $value = date('Y-m-d H:i:s', $timestamp);
      }
    }
    $values[$key] = $value;
  }
  return $values;
}

function dbInsertTableRow($table, $fields)
{
  global $conn;
  if (!$fields || !is_array($fields)) { return []; }
  $table = dbSqlIdentifier($table); $values = dbLegacyWriteValues($table, $fields);
  if (!$values) { return []; }
  $assignments = array_map(static fn ($key) => "`$key`=?", array_keys($values));
  $result = sqlQuery("INSERT INTO `$table` SET " . implode(',', $assignments), array_values($values));
  $id = $conn->insert_id;
  dbWriteLog($table, json_encode(['action' => 'insert', 'id' => $id, 'fields' => array_keys($values)], JSON_THROW_ON_ERROR));
  return $result ? $id : false;
}

//=======================================================
// update row from key=>value list
//=======================================================
function dbUpdateTableRow($table, $id, $fields)
{
  if (empty($id) || !$fields || !is_array($fields)) { return []; }
  $table = dbSqlIdentifier($table); $id = dbSqlInteger($id, 1);
  $values = dbLegacyWriteValues($table, $fields);
  if (!$values) { return []; }
  $assignments = array_map(static fn ($key) => "`$key`=?", array_keys($values));
  $params = array_values($values); $params[] = $id;
  $result = sqlQuery("UPDATE `$table` SET " . implode(',', $assignments) . " WHERE `id`=?", $params);
  dbWriteLog($table, json_encode(['action' => 'update', 'id' => $id, 'fields' => array_keys($values)], JSON_THROW_ON_ERROR));
  return $result;
}

//=======================================================
// delete row softdelete
//=======================================================
function dbDeleteTableRow($table, $id)
{
  if (empty($id)) { return []; }
  $table = dbSqlIdentifier($table); $id = dbSqlInteger($id, 1);
  $result = sqlQuery("UPDATE `$table` SET `deleted`=1 WHERE `id`=?", [$id]);
  dbWriteLog($table, json_encode(['action' => 'delete', 'id' => $id], JSON_THROW_ON_ERROR));
  return $result;
}

//=======================================================
// if table exist true else false
//=======================================================
function dbIsTableExist($table)
{
  $table = dbSqlIdentifier($table);
  $sqlresult = sqlQuery("SELECT name FROM sqlite_master WHERE type='table' AND name=?", [$table]);

  if ($row = $sqlresult->fetch_row()) {
    return ($row[0] == $table);
  } else {
    return false;
  }
}

//=======================================================
// Write Logs
//=======================================================
function dbWriteLog($task, $comment)
{
  global $conn;
  $userId = filter_var($_SESSION['userId'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  if (sqlQuery("INSERT INTO `logs` (`task`,`comment`,`user_id`,`ip_no`) VALUES (?,?,?,?)", [$task, $comment, $userId === false ? 0 : $userId, $ip])) {
    return $conn->insert_id;
  }
}

//=======================================================
// Write Exceptions and Errors
//=======================================================
function dbWriteError($type, $code, $message, $file, $script, $line, $referer, $session, $get, $post)
{
  global $conn;
  $values = [$type, $code, $message, $file, $script, $line, $referer, $session, $get, $post, $_SERVER['REMOTE_ADDR'] ?? ''];
  foreach ($values as $value) { if (!is_scalar($value) && $value !== null) { throw new InvalidArgumentException('Invalid error field'); } }
  if (sqlQuery("INSERT INTO `errors` (`type`,`code`,`message`,`file`,`script`,`line`,`referer`,`session`,`get`,`post`,`ip_no`) VALUES (?,?,?,?,?,?,?,?,?,?,?)", $values)) {
    return $conn->insert_id;
  }
}

//=======================================================
// Return lang slug array
//=======================================================
function findLangSlugs($table, $lang_table, $slug)
{
  global $siteLanguages;
  $table = dbSqlIdentifier($table); $lang_table = dbSqlIdentifier($lang_table);
  if (!is_string($slug)) { throw new InvalidArgumentException("Invalid slug"); }

  $data_id = null;
  $resultArray = [];

  // find id from slug
  if ($langresult = sqlQuery("SELECT `row_id` FROM `$lang_table` WHERE `slug`=?", [$slug])) {
    if ($row = $langresult->fetch_assoc()) {
      $data_id = $row['row_id'];
    } else if ($sqlresult = sqlQuery("SELECT `id` FROM `$table` WHERE `slug`=?", [$slug])) {
      if ($row = $sqlresult->fetch_assoc()) {
        $data_id = $row['id'];
      }
    }
  }

  // create lang,slug array
  if ($data_id) {

    $sqlresult = sqlQuery("SELECT
      `langs`.`id`,
      COALESCE(`$lang_table`.`slug`, (SELECT `slug` FROM `$table` WHERE `id`=?)) AS `slug`
      FROM `langs`
      LEFT JOIN `$lang_table`
      ON `$lang_table`.`lang_id`=`langs`.`id`
      AND `$lang_table`.`row_id`=?", [$data_id, $data_id]
    );

    while ($row = $sqlresult->fetch_assoc()) {
      $id = $row['id'];
      $resultArray[$id] = dbLegacyReadRow($row);
    }
  }

  if (!$resultArray) {
    foreach ($siteLanguages as $l => $lang) {
      $resultArray[$l] = ['id' => $l, 'slug' => ''];
    }
  }

  return $resultArray;
}
