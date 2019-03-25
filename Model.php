<?php
namespace Database;
class Model{
    //数据库连接配置
    private static $config = [
        'driver'=>'pdo',
        'connection'=>[
            'host'=>'127.0.0.1',
            'user'=>'root',
            'password'=>'vabora',
            'database'=>'demo',
            'port'=>3306
        ]
    ];
    //数据库连接信息
    private static $info = [];
    //数据模型实例
    private static $model = null;
    //数据库连接实例
    private static $connection = null;
    /**
     * 构造函数
     * @param array $config 配置信息
     * @return object 数据库实例
     */
    private function __construct(){
        $driver = self::$config['driver'].'Driver';
        require_once($driver.'.php');
        $driver = __NAMESPACE__.'\\'.$driver;
        $db = $driver::getInstance();
        self::$connection = $db->connect(self::$config['connection']);
        self::$model = $db;
        self::$info = $db->info();
    }
    //私有化克隆函数，防止克隆
    private function __clone(){}
    /**
     * source 数据表源信息
     * @param string $table 数据表名
     * @param boolean $fill 是否填充数据
     * @return string 返回元数据信息字符串
     */
    private static function source(string $table,bool $fill=true){
        $sql = ["-- ----------------------------\n-- Table structure for {$table}\n-- ----------------------------"];
        $sql[] = "DROP TABLE IF EXISTS `{$table}`;";
        $createInfo = self::query('SHOW CREATE TABLE '.$table);
        $sql[] = str_replace('"','`',$createInfo[0]['Create Table'].';');
        if($fill){
            $sql[] = "-- ----------------------------\n-- Records of {$table}\n-- ----------------------------";
            $sql[] = "BEGIN;";
            self::$model->table($table);
            $fields = self::$model->fields();
            foreach(self::$model->list() as $data){
                $field = implode(', ',array_map(function($key,$value){
                    if(preg_match('/^int\S*/',$key)){return $value;}
                    elseif(is_null($value)){return "NULL";}
                    else{return "'{$value}'";}
                },array_column($fields,'Type'),array_values($data)));
                $sql[] = "INSERT INTO `{$table}` VALUES ({$field});";
            }
            $sql[] = "COMMIT;";
        } 
        return implode("\n",$sql);
    }
    /**
     * table 数据表实例获取函数
     * @param string $table 数据表名
     * @return object 数据表模型实例
     */
    public static function table(string $table){
        if(!(self::$model instanceof self)){
			new self();
        }
		return self::$model->table($table);
    }
    /**
	 * query 对数据库执行查询操作
	 * @param string $sql 执行的SQL语句[支持{0}变量替换，C# string.format]
	 * @param mixed $params 参数
	 * @return [bool|array] 失败时返回 FALSE，成功执行SELECT, SHOW, DESCRIBE或 EXPLAIN查询会返回数据数组Array，其他查询则返回TRUE。
	 */
	public static function query(string $sql,...$params){
		$sql = preg_replace_callback('/\\{(0|[1-9]\\d*)\\}/',function($match)use($params){
            return isset($params[$match[1]])?$params[$match[1]]:$match[0];
        },$sql);
        $obj = self::$connection->query($sql);
        if(gettype($obj)=='boolean'){
            return $obj;
        }
        else{
            return preg_match('/mysqli/i',self::$config['driver'])?$obj->fetch_all(MYSQLI_ASSOC):$obj->fetchAll(\PDO::FETCH_ASSOC);
        }
    }
    /**
     * backup 备份数据库
     * @param string $file 完整的备份文件路径及名称
     * @param boolean $fill 是否填充数据[默认：true]
     * @param boolean $cover 是否覆盖已有备份文件[默认：false]
     * @return array 备份操作结果集[state,file,message]
     */
    public static function backup(string $file,bool $fill=true,bool $cover=false){
        if(is_file($file)&&!$cover){return ['state'=>false,'file'=>$file,'message'=>'文件已存在'];}
        else{
            $database = self::$config['connection']['database'];
            $time = date('Y-m-d H:i:s');
            $sqls = [
                "/*\nDatabase Transfer Engine",
                "Source Server  : MySQL V".self::$info['version'],
                "Source Host    : ".self::$info['host'],
                "Source Port    : ".self::$config['connection']['port'],
                "Source Charset : ".self::$info['charset'],
                "Source Schema  : ".$database,
                "Backup Date    : {$time}\n*/",
                "USE {$database};\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;"
            ];
            $sqls = [implode("\n",$sqls)];
            $tables = self::query('SHOW TABLES');
            $tables = array_column($tables,array_keys(is_array($tables[0])?$tables[0]:[])[0]);
            foreach($tables as $table){
                $sqls[] = self::source($table,$fill);
            }
            $sqls[] = 'SET FOREIGN_KEY_CHECKS = 1;';
            $byte = file_put_contents($file,implode("\n\n",$sqls));
            return ['state'=>isset($byte)?true:false,'file'=>$file,'message'=>isset($byte)?'备份成功':'备份失败'];
        }
    }
    /**
     * recover 还原数据库
     * @param string $file 完整文件路径及名称
     * @return array 还原操作结果集
     */
    public static function recover(string $file){
        if(!is_file($file)){
            return ['state'=>false,'file'=>$file,'message'=>'文件不存在'];
        }
        else{
            $sql = file_get_contents($file);
            $result = preg_match('/mysqli/i',self::$config['driver'])?self::$connection->multi_query($sql):self::$connection->query($sql);
            return ['state'=>isset($result)?true:false,'file'=>$file,'message'=>isset($result)?'还原成功':'还原失败'];
        }
    }
    
}
