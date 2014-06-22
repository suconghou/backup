<?php

/**
 * MYSQL&FILES备份 
 * @author suconghou
 * @blog http://blog.suconghou.cn
 * @version v1.0
 */

//要备份的文件目录.默认当前目录
define('PASS',1111);  //备份密匙
define("BACKNAME", 'yunpage'); //定义表示网站,请使用英文或者数字
define('BACKUP_DIR','.');///需要备份的文件夹
define('BACKUP_TYPE',2); //1 仅保存到云端,2 仅保存到本地 3. 云端和本地同时保存
define('BACKUP_DB', 0); //是否开启mysql备份,需要填写正确的mysql信息.

// mysql数据库配置
define('DB_HOST','rds3a326jjabjay.mysql.rds.aliyuncs.com');
define('DB_PORT',3306);
define('DB_NAME','dbd9sn0cunmk5x7u');
define('DB_USER','db_user');
define('DB_PASS','123456');


/**
* mysql_backup start
*/
class mysql_backup
{
  private static $link;
  
  function __construct()
  {
      @set_time_limit(300);
      try
      {
         self::$link=mysql_connect(DB_HOST.':'.DB_PORT,DB_USER,DB_PASS);
         mysql_select_db(DB_NAME);
         mysql_query("set names utf8");
      } 
      catch (Exception $e) 
      {
        exit($e->getMessage());
      }
  }
  function runSql($sql)
  {
    try
    {
      $result=mysql_query($sql);
      return $result;  
    }
    catch (Exception $e)
    {
        exit($e->getMessage());
    }
  }
  function get_tables()
  {
    $sql="SHOW TABLES";
    $result=$this->runSql($sql);
    while($row=mysql_fetch_row($result))
    {
      $tables[]=$row[0];
    }
    return $tables;

  }
  //获得所有表结构
  function table2sql()
  {
    $tables=$this->get_tables();
    $return="-- ".date('Y-m-d H:i:s')."\r\n";
    foreach ($tables as $table)
    {
      $result=$this->runSql("select * from ".$table);
      $num_fields = mysql_num_fields($result);   
      $return.= 'DROP TABLE IF EXISTS `'.$table.'` ;';
      
      $create = mysql_fetch_row($this->runSql('SHOW CREATE TABLE '.$table));
      $return.= "\n\n".$create[1].";\n\n";
      
    }
    return  $return;

  }
  function data2sql()
  {
    $tables=$this->get_tables();
    $return="-- ".date('Y-m-d H:i:s')."\r\n";
    foreach ($tables as $table)
    {
      $result=$this->runSql("select * from ".$table);
      $num_fields = mysql_num_fields($result);   
      $return.= 'DROP TABLE IF EXISTS `'.$table.'` ;';
      $create = mysql_fetch_row($this->runSql('SHOW CREATE TABLE '.$table));
      $return.= "\n\n".$create[1].";\n\n";
      
      for ($i=0; $i < $num_fields ; $i++)
      { 
          while($row = mysql_fetch_row($result))
          {
               $return.= 'INSERT INTO `'.$table.'` VALUES(';
               for($j=0; $j<$num_fields; $j++) 
               {
                  $row[$j] = addslashes($row[$j]);
                  if (isset($row[$j])) { $return.= '"'.$row[$j].'"' ; }
                  else { $return.= '""'; }
                  if ($j<($num_fields-1)) { $return.= ','; }
               }
              $return.= ");\n";
          }
      }  
      $return.="\n\n\n";
    
    }
    
    return $return;
  }
  function backup()
  {
    $data=$this->data2sql();
    $GLOBALS['sql_name']=BACKNAME.'-'.date('Y-m-d-H-i-s').'-backup.sql';
    file_put_contents(BACKUP_DIR.'/'.$GLOBALS['sql_name'],$data);
  }

}

// end class mysql_backup


/**
* 
*/
class file_backup
{
 

  function __construct()
  {
      @set_time_limit(300);
  
  }
  function backup()
  {
    $zip= new ZipArchive(); 
    $file=BACKNAME.'-'.date("Y-m-d-H-i-s-")."backup.zip";
    $filename = "./".$file; 
    if ($zip->open($filename, ZIPARCHIVE::CREATE)!==TRUE)
    {
      die("当前目录不可写。创建文件失败，请检查目录权限。"); 
    }
    $files = self::listdir();
    if(BACKUP_DB)
    {
      array_push($files, $GLOBALS['sql_name']);      
    }
    foreach($files as $path)
    {
      $zip->addFile($path,str_replace("./","",str_replace("\\","/",$path))); 
    }
    $ret['filenum']=$zip->numFiles;
    $zip->close();
    BACKUP_DB&&unlink($GLOBALS['sql_name']);
    $ret['filename']=$file;
    return $ret;

  }
  public static function  listdir($start_dir=BACKUP_DIR)
  {
    $files = array();
    if (is_dir($start_dir)) {
      $fh = opendir($start_dir);
      while (($file = readdir($fh)) !== false) {
        if(strcmp($file, '.')==0 || strcmp($file, '..')==0){
          continue;
        }
        $filepath = $start_dir . '/' . $file;
        if(is_dir($filepath)){
          $files = array_merge($files, self::listdir($filepath));
        }else{
          array_push($files, $filepath);
        }
      }
      closedir($fh);
    }else{
      $files = false;
    }
    return $files;
  }


  public static function post_data($url,$post_string)
  {
      $ch=curl_init();
      curl_setopt_array($ch, array(CURLOPT_URL=>$url,CURLOPT_SSL_VERIFYPEER=>0,CURLOPT_RETURNTRANSFER=>1,CURLOPT_POST=>1,CURLOPT_POSTFIELDS=>$post_string));
      $result=curl_exec($ch);
      curl_close($ch);
      return $result;
  }
  /**
   * 发送到我的快盘
   */
  public static function sendfile($name)
  {
     $file=file_get_contents($name);
     $path='/backup/'.$name;
     $token=file_get_contents('http://api.suconghou.cn/kupan/key');
     $url="https://api-upload.kanbox.com/0/upload{$path}?bearer_token=".$token;
     $res=self::post_data($url,$file);
     return ($res==1)?true:false;

  }



}

// end class file_backup


function byte_format($size,$dec=2)
{
    $unit=array("B","KB","MB","GB","TB","PB","EB","ZB","YB");
    return round($size/pow(1024,($i=floor(log($size,1024)))),$dec).' '.$unit[$i];
}

/**
 * All controls
 */
function backup()
{

  if(BACKUP_DB)
  {
    $db=new mysql_backup();
    $db->backup();
    $log=date('Y-m-d H:i:s')."已成功导出MYSQL数据".$GLOBALS['sql_name']."\r\n";
  }
  $file=new file_backup();
  $ret=$file->backup();
  $log.=date('Y-m-d H:i:s')."已成功备份".$ret['filenum']."个文件( ".byte_format(filesize($ret['filename']))." )\r\n";
  switch (BACKUP_TYPE)
  {
    case '1': //仅云端
        $sendok=$file::sendfile($ret['filename']);
        if($sendok)
        {
          $log.=date('Y-m-d H:i:s')."已成功存储到云端".$ret['filename']."\r\n";
        }
        else
        {
          $log.=date('Y-m-d H:i:s')."存储到云端失败".$ret['filename']."\r\n";
        }
        unlink('./'.$ret['filename']);   
      break;
    case '2': //仅本地
        $log.=date('Y-m-d H:i:s')."已存储到本地".$ret['filename']."\r\n";
      break;
    case '3': ///云端和本地
        $log.=date('Y-m-d H:i:s')."已存储到本地".$ret['filename']."\r\n";
        $sendok=$file::sendfile($ret['filename']);
        if($sendok)
        {
          $log.=date('Y-m-d H:i:s')."已成功存储到云端".$ret['filename']."\r\n";
        }
        else
        {
          $log.=date('Y-m-d H:i:s')."存储到云端失败".$ret['filename']."\r\n";
        }
      break;
    default:
      $log.="备份参数不正确\r\n";
      break;
  }
  return $log;

}
if($_GET['key']==PASS)
{
  $log=backup();
  echo $log;
  file_put_contents('log.log',$log,FILE_APPEND);
}

