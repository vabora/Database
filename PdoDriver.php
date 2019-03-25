<?php
/**
 * Database 数据库操作类[PDO]版
 * @author vabora(王小平)
 * @create time 2019/3/22 14:23:22
 * @version 1.0.0
 */
namespace Database;
use PDO;
use PDOException;
class PdoDriver{
	//缓存实例
	private static $instance = null;
	//系统日志
	private static $log = [];
	//数据连接实例
	private static $link = null;
	//当前数据表
	private $table = null;
	//数据列
	private $column = "*";
	//筛选条件
	private $where = [];
	//数据信息
	private $data = [];
	//数据排序
	private $order = [];
	//私有化构造函数，防止外部实例化
	private function __construct(){}
	//私有化克隆函数，防止克隆
	private function __clone(){}
	/**
	 * execute 预处理执行函数[select|insert|delete|update]
	 * @param string $sql 执行的SQL语句
	 * @param array $params 执行的SQL参数
	 * @param boolean $modify 是否执行更改
	 */
	private static function execute(string $sql,array $params=[],bool $modify=true){
		$result = array('state'=>false,'rows'=>0,'data'=>[]);
		$stmt = self::$link->prepare($sql,array(PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL));
		if($stmt){
			$result['state'] = count($params)>0?$stmt->execute($params):$stmt->execute();
			$result['rows'] = $stmt->rowCount();
			$result['data'] = !$modify?$stmt->fetchAll(PDO::FETCH_ASSOC):[];
			$stmt->closeCursor();
		}
		return $result;
	}
	/**
	 * getCharset 获取数据库连接字符集
	 * @return array [charset,collation]
	 */
	private static function getCharset(){
		$charset['charset'] = self::$link->query("SELECT CHARSET('');")->fetchColumn();
		$charset['collation'] = self::$link->query("SELECT COLLATION('');")->fetchColumn();
		return $charset;
	}
	/**
	 * getInstance 类实例获取入口
	 * @return object 类唯一实例
	 */
	public static function getInstance(){
		if(!(self::$instance instanceof self)){
			self::$instance = new self();
		}
		return self::$instance;
	}
	/**
	 * info 获取当前数据库实例信息
	 * @return array 信息数组
	 */
	public function info(){
		return array(
			'server' => self::$link->getAttribute(PDO::ATTR_SERVER_INFO),
			'client' => self::$link->getAttribute(PDO::ATTR_CLIENT_VERSION),
			'driver' => 'PDO',
			'version' => self::$link->getAttribute(PDO::ATTR_SERVER_VERSION),
			'host' => self::$link->getAttribute(PDO::ATTR_CONNECTION_STATUS),
			'charset' => self::getCharset()['charset'],
			'collation' => self::getCharset()['collation'],
		 	'log' => self::$log
		);
	}
	/**
	 * connect 数据库连接函数
	 * @param array $config 连接配置信息
	 * @return object 数据库实例
	 */
	public function connect(array $config=array()){
		$valid = ['host','user','password','database','port'];
		if(count(array_diff($valid,array_keys($config)))>0){
			self::$log[] = ['state'=>0,'message'=>'非法的数据连接参数'];
		}
		else{
			if(preg_match('/local\S*/',$config['host'])){
				$config['host'] = '127.0.0.1';
			}
			try {
				$dsn = 'mysql:host='.$config['host'].';dbname='.$config['database'].';port='.$config['port'];
				self::$link = new PDO($dsn, $config['user'], $config['password'],array(PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING));
				self::$log[] = ['state'=>1,'message'=>self::$link];
			} catch (PDOException $e) {
				self::$log[] = ['state'=>1,'message'=>$e->getMessage()];
			}
		}
		return self::$link;
	}
	/**
	 * table 设置当前使用数据表
	 * @param string $table 数据表名称
	 * @return object $this 当前实例
	 */
    public function table(string $table){
        $this->table = '`'.$table.'`';
        return $this;
	}
	/**
	 * column 设置当前数据列
	 * @param array $columns 数据列名称数组
	 * @return object $this 当前实例
	 */
	public function column(array $columns){
		if(count($columns)>0){
			$this->column = '`'.implode('`,`',$columns).'`';
		}
		if($this->column=='`*`'){$this->column="*";}
		return $this;
	}
	/**
	 * data 设置当前操作的数据
	 * @param array $data 数据信息数组
	 * @return object $this 当前实例
	 */
	public function data(array $data){
		$this->data['key'] = array_keys($data);
		$this->data['value'] = array_values($data);
		return $this;
	}
	/**
	 * where 设置条件语句及对应参数
	 * @param string $statement 语句字符串[name=? and age >?]
	 * @param array $params 对应参数['vabora',10]
	 * @return object $this 当前实例
	 */
	public function where(string $statement,array $params=[]){
		$this->where['statement']=preg_replace('/where/i','',$statement);
		$this->where['value'] = array_values($params);
		return $this;
	}
	/**
	 * order 设置数据列表排序规则
	 * @param array $rule 排序规则
	 * @return object $this 当前实例
	 */
	public function order(array $rule){
		$keys = array_keys($rule);
		$values = array_values($rule);
		for($i=0;$i<count($rule);$i++){
			$this->order[] = $keys[$i].' '.(preg_match('/(asc|desc)/i',$values[$i])?strtoupper($values[$i]):'ASC');
		}
		return $this;
	}
	/**
	 * insert 插入新的记录到数据表
	 * @return array $result 返回操作结果[state=>状态,rows=>插入行数,data=>插入数据]
	 */
	public function insert(){
		$field = '';
		if(gettype($this->data['key'][0])=='string'){
			$field = '(`'.implode('`,`',$this->data['key']).'`)';
		}
		$value = rtrim(str_repeat('?,',count($this->data['value'])),',');
		$sql = implode(' ',['INSERT INTO',$this->table,$field,'VALUES('.$value.')']);
		$result = self::execute($sql,$this->data['value']);
		$result['data'] = array_combine($this->data['key'],$this->data['value']);
		return $result;
	}
	/**
	 * delete 删除数据表中的指定数据
	 * @return array $result 返回操作结果[state=>状态,rows=>删除行数,data=>删除数据]
	 */
	public function delete(){
		$sql = implode(' ',['DELETE FROM',$this->table]);
		if(isset($this->where['statement'])){//安全控制，默认没有where条件不执行
			$sql.=' WHERE '.$this->where['statement'];
			$data = $this->select()['data'];
			$result = self::execute($sql,$this->where['value']);
			$result['data'] = $data;
		}
		return $result;
	}
	/**
	 * clear 清空数据表并重置数据索引
	 * @return array $result 返回操作结果[state=>状态,rows=>清空行数,data=>清空数据]
	 */
	public function clear(){
		$sql = 'DELETE FROM '.$this->table;
		$data = $this->select()['data'];
		$result = self::execute($sql);
		if($result['rows']>0){
			self::$link->query('TRUNCATE TABLE '.$this->table);
		}
		$result['data'] = $data;
		return $result;
	}
	/**
	 * update 更新数据表中指定数据
	 * @return array $result 返回操作结果[state=>状态,rows=>更新行数,data=>更新数据]
	 */
	public function update(){
		$sql = 'UPDATE '.$this->table;
		if(isset($this->data['value'])&&isset($this->where['statement'])){//安全控制，默认没有where条件不执行
			if(gettype($this->data['key'][0])=='string'){
				$sql .= ' SET '.implode('=?,',$this->data['key']).'=? ';
				$sql .= ' WHERE '.$this->where['statement'];
				$params = array_merge($this->data['value'],$this->where['value']);
				$result = self::execute($sql,$params);
			}
		}
		$result['data'] = array_combine($this->data['key'],$this->data['value']);
		return $result;
	}
	/**
	 * select 查询数据表中指定数据
	 * @return array $result 返回操作结果[state=>状态,rows=>查询行数,data=>匹配数据]
	 */
	public function select(){
		$sql = implode(' ',['SELECT',$this->column,'FROM',$this->table]);
		$params = [];
		if(isset($this->where['statement'])){
			$sql.=' WHERE '.$this->where['statement'];
			$params = is_array($this->where['value'])?$this->where['value']:[];
		}
		if(count($this->order)>0){
			$sql.=' ORDER BY '.implode(',',$this->order);
		}
		$result = self::execute($sql,$params,false);
		return $result;
	}
	/**
	 * list 获取当前数据列表
	 * @return array 数据列表
	 */
	public function list(){
		return $this->select()['data'];
	}
	/**
	 * count 统计当前记录数
	 * @return int 当前记录数
	 */
	public function count(){
		return $this->select()['rows'];
	}
	/**
	 * fields 显示当前表的字段详情
	 * @param string $table 查询的表名
	 * @return array 数据表字段详情数组
	 */
	public function fields(string $table=null){
		$this->table = isset($table)?$table:$this->table;
		$obj = self::$link->query('DESCRIBE '.$this->table);
		return gettype($obj)=='object'?$obj->fetchAll(PDO::FETCH_ASSOC):[];
	}
}
