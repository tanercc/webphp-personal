<?php
defined('BASEPATH') or exit('No direct script access allowed');
/** SQLite implementation of the small result/statement interface consumed by this CMS.
 * This is application code, not a general MySQL emulator or a vendor modification.
 */
final class PanelSqliteResult {
 private int $offset=0;
 public int $num_rows;
 public function __construct(private array $rows) {$this->num_rows=count($rows);}
 public function fetch_assoc(): array|false {return $this->rows[$this->offset++]??false;}
 public function fetch_row(): array|false {$row=$this->fetch_assoc();return $row===false?false:array_values($row);}
 public function fetch_array(): array|false {$row=$this->fetch_assoc();return $row===false?false:array_merge(array_values($row),$row);}
 public function fetch_all(int $mode=PDO::FETCH_ASSOC): array {return $this->rows;}
}
final class PanelSqliteStatement {
 public int $field_count=0;
 private array $bound=[];
 private PanelSqliteResult|bool $result=false;
 public function __construct(private PanelSqliteConnection $db,private string $sql) {}
 public function bind_param(string $types,mixed &...$values):void {$this->bound=&$values;}
 public function execute(?array $values=null):bool {$this->result=$this->db->execute_query($this->sql,$values??$this->bound);$this->field_count=$this->result instanceof PanelSqliteResult?1:0;return true;}
 public function get_result():PanelSqliteResult {if(!$this->result instanceof PanelSqliteResult){throw new RuntimeException('No result');}return $this->result;}
 public function close():void {}
}
final class PanelSqliteConnection {
 public PDO $pdo;
 public int $insert_id=0;
 public int $affected_rows=0;
 public function __construct(string $path) {
  if(!is_file($path)){throw new RuntimeException('Run php tools/install.php first');}
  $this->pdo=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
  $this->pdo->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;');
 }
 public function columns(string $table):array {
  if(!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D',$table)){throw new InvalidArgumentException('Invalid table');}
  $rows=$this->pdo->query('PRAGMA table_info("'.$table.'")')->fetchAll();$out=[];
  foreach($rows as $c){$default=$c['dflt_value'];if(is_string($default)&&strlen($default)>1&&$default[0]==="'"){$default=str_replace("''","'",substr($default,1,-1));}
   $out[]=['Field'=>$c['name'],'Type'=>strtolower($c['type']),'Null'=>$c['notnull']?'NO':'YES','Key'=>$c['pk']?'PRI':'','Default'=>$default,'Extra'=>$c['pk']?'auto_increment':''];}
  return $out;
 }
 public function tableExists(string $table):bool {
  if(!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D',$table)){throw new InvalidArgumentException('Invalid table');}
  $s=$this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?");$s->execute([$table]);return $s->fetchColumn()!==false;
 }
 public function upsert(string $table,array $row,array $keyColumns):void {
  $id=static function(string $n):string{if(!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D',$n)){throw new InvalidArgumentException('Invalid identifier');}return '`'.$n.'`';};
  if(!$row||!$keyColumns||array_diff($keyColumns,array_keys($row))){throw new InvalidArgumentException('Invalid upsert');}
  $cols=array_map($id,array_map('strval',array_keys($row)));$keys=array_map($id,$keyColumns);
  $set=[];foreach(array_keys($row) as $c){if(!in_array($c,$keyColumns,true)){$set[]=$id($c).'=excluded.'.$id($c);}}
  $sql='INSERT INTO '.$id($table).' ('.implode(',',$cols).') VALUES ('.implode(',',array_fill(0,count($row),'?')).') ON CONFLICT ('.implode(',',$keys).') '.($set?'DO UPDATE SET '.implode(',',$set):'DO NOTHING');
  $this->execute_query($sql,array_values($row));
 }
 public function query(string $sql):PanelSqliteResult|bool {return $this->execute_query($sql);}
 public function prepare(string $sql):PanelSqliteStatement {return new PanelSqliteStatement($this,$sql);}
 public function execute_query(string $sql,array $values=[]):PanelSqliteResult|bool {
  $statement=$this->pdo->prepare($sql);
  foreach(array_values($values) as $i=>$value){$statement->bindValue($i+1,$value,$value===null?PDO::PARAM_NULL:(is_int($value)||is_bool($value)?PDO::PARAM_INT:PDO::PARAM_STR));}
  $statement->execute();$this->affected_rows=$statement->rowCount();
  if($statement->columnCount()){return new PanelSqliteResult($statement->fetchAll());}
  $this->insert_id=(int)$this->pdo->lastInsertId();return true;
 }
 public function begin_transaction():void {$this->pdo->beginTransaction();}
 public function commit():void {$this->pdo->commit();}
 public function rollback():void {if($this->pdo->inTransaction()){$this->pdo->rollBack();}}
}
function panelSqliteConnection(): PanelSqliteConnection {
    global $databaseFile;
    static $db;
    return $db ??= new PanelSqliteConnection(getenv('PANEL_DB_FILE') ?: getenv('PERSONAL_DB_FILE') ?: $databaseFile);
}
