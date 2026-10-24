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
 public int $found_rows=0;
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
 public function query(string $sql):PanelSqliteResult|bool {return $this->execute_query($sql);}
 public function prepare(string $sql):PanelSqliteStatement {return new PanelSqliteStatement($this,$sql);}
 public function execute_query(string $sql,array $values=[]):PanelSqliteResult|bool {
  if(preg_match('/^SHOW COLUMNS FROM `?([a-zA-Z_][a-zA-Z0-9_]*)`?$/iD',trim($sql),$m)){return new PanelSqliteResult($this->columns($m[1]));}
  if(preg_match('/^SELECT FOUND_ROWS\(\);?$/iD',trim($sql))){return new PanelSqliteResult([['FOUND_ROWS()'=>$this->found_rows]]);}
  // Legacy helper emits INSERT ... SET; API writes already use standard INSERT syntax.
  if(preg_match('/^INSERT INTO (`[a-zA-Z_][a-zA-Z0-9_]*`) SET (.+)$/isD',$sql,$m)){
   $assign=explode(',',$m[2]);$names=[];foreach($assign as $a){if(!preg_match('/^\s*(`\w+`)\s*=\s*\?\s*$/D',$a,$n)){throw new RuntimeException('Unsupported legacy insert');}$names[]=$n[1];}
   $sql='INSERT INTO '.$m[1].' ('.implode(',',$names).') VALUES ('.implode(',',array_fill(0,count($names),'?')).')';
  }
  if(str_contains($sql,'SQL_CALC_FOUND_ROWS')){
   $sql=str_replace('SQL_CALC_FOUND_ROWS','',$sql);
   $countSql=preg_replace('/\s+LIMIT\s+\d+\s*,\s*\d+\s*$/i','',$sql);
   $count=$this->pdo->prepare('SELECT COUNT(*) FROM ('.$countSql.') counted');$count->execute($values);$this->found_rows=(int)$count->fetchColumn();
  }
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
function panelSqliteConnection():PanelSqliteConnection {static $db;return $db??=new PanelSqliteConnection(getenv('PERSONAL_DB_FILE')?:dirname(__DIR__,2).'/storage/personal.sqlite');}
