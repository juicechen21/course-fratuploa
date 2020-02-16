<?php
/**
 *2019-12-27
 * @czj
 * 分片上传
 */
class Fratupload
{
    private $abspath;//绝对路径
    private $filepath = '/'; //上传目录
    private $blobNum; //第几个文件块
    private $totalBlobNum; //文件块总数
    private $fileName; //文件名
    private $allowExtension = ['zip','png'];//允许上传的文件名称后缀
	private $allowFileType = ['application/zip'];//允许上传的文件名称后缀
    private $fileExtension ='';//文件名称后缀
	private $nowfileType ='';//文件类型
    private $nowFile = '';//当前块内容
    private $totalSize = 0;//文件大小
    private $allowFileSize = 0;//文件总大小只允许
    private $fileMd5 = '';//文件md5  前端传过来的   用于创建临时文件夹  上传完后删除
	private $fileDecdir = '';//解压目录
    public function __construct($filepath = '')
    {
        $this->abspath = __DIR__ .'/uploads';
        #文件路径
        if($filepath)$this->filepath =  $this->filepath . $filepath;
        #post上传文件
        $postData = $_POST;
        if(!isset($postData['name']) || !isset($postData['fileMd5']) || !isset($postData['size']) || !isset($postData['chunk']) || !isset($postData['chunks'])){
            $this->jsonOut(CODE_FAIL,'参数错误！');
        }
        #文件名称
        $this->fileName = $postData['name'];
        if($this->isHaveFile())$this->jsonOut(CODE_FAIL,'文件已存在');
        #临时文件夹
        $this->fileMd5 = $postData['fileMd5'];
        #文件大小（前端定义的）
        $this->totalSize = $postData['size'];
        #当前块
        $this->blobNum = $postData['chunk'];
        #总共块
        $this->totalBlobNum = $postData['chunks'];
        #获取后缀
        $fileExtension = explode(".",basename($this->fileName));
		$this->fileDecdir = $fileExtension[0];
        $this->fileExtension = $fileExtension[1];
        
        #检测文件
        if(!$_FILES || !isset($_FILES['file']))$this->jsonOut(CODE_FAIL,'请选择文件上传！');
        $this->nowFile =  $_FILES['file'];
        if($this->nowFile['error'] > 0)$this->jsonOut(CODE_FAIL,'文件错误！');
        
		$this->nowfileType = $_FILES['file']['type'];
		
		#检测后缀是否在允许范围&&文件类型
        $this->checkFileExtension();
		
		#允许文件的大小 50M
        $this->allowFileSize = (50*1024*1024);
        if((int)$this->nowFile['size']>$this->allowFileSize)$this->jsonOut(CODE_FAIL,'文件大小超50M限制！');
    }

    function doUpload()
    {
        #临时文件移动到指定目录下
        $res = $this->moveFile();
        if($res['status']==999){
            return $this->fileMerge();
        }else{
            return $res;
        }
    }

    /**
    *文件合并
     */
    function fileMerge()
    {
        if ($this->blobNum == $this->totalBlobNum) {
            $fileName = $this->abspath.$this->createFileName();
            @unlink($fileName);#删除旧文件
            #文件合并  文件名以
            $handle=fopen($fileName,"a+");
            for($i=1; $i<= $this->totalBlobNum; $i++){
                #当前分片数
                $this->blobNum = $i;
                #吧每个块的文件追加到 上传的文件中
                fwrite($handle,file_get_contents($this->createBlockFileName()));
            }
            fclose($handle);
            #删除分片
            for($i=1; $i<= $this->totalBlobNum; $i++){
                $this->blobNum = $i;
                @unlink($this->createBlockFileName());
            }
            #删除临时目录
            @rmdir($this->abspath.$this->filepath.$this->fileMd5);
			
			$fileUrl = $this->abspath.$this->createFileName();
			$path = $this->abspath.$this->filepath;
			$true = $this->unzipFile($fileUrl, $path);
			if($true){
				$msg['status'] = 200;
				$msg['msg'] = "上传成功！";
				$msg['url'] = WEB_IMG.$this->filepath.$this->fileDecdir."/tileset.json";
				return $msg;
			}
			
			/**
            $msg['status'] = 200;
            $msg['msg'] = '上传成功！';
            $msg['size'] = $this->totalSize;
            $msg['fileAddress'] = $this->createFileName();
            $msg['url'] = WEB_IMG . $this->createFileName();
            return $msg;
			*/
        }
    }

    /**
     * 创建md5  文件名
     */
    function createFileName()
    {
        return $this->filepath.$this->fileName;
    }

    /**
     *将临时文件移动到指定目录
     */
    function moveFile()
    {
        try{
            #每个块的文件名 以文件名的MD5作为命名
            $filename=$this->createBlockFileName();
            #分片文件写入
            $handle=fopen($filename,"w+");
            fwrite($handle,file_get_contents($this->nowFile['tmp_name']));
            fclose($handle);
            #不是最后一块就返回当前信息   是最后一块往下执行合并操作
            if($this->blobNum != $this->totalBlobNum) {
                $msg['status'] = 201;
                $msg['msg'] = "上传成功！";
                $msg['blobNum'] = $this->blobNum;
                return $msg;
            }else{
                $msg['status'] = 999;
                $msg['msg'] = "上传成功！";
                $msg['blobNum'] = $this->blobNum;
                return $msg;
            }
        }catch (Exception $e){
            $msg['status'] = 501;
            $msg['msg'] = $e->getMessage();
            return $msg;
        }
    }

    /**
     *检测文件是否重复
     */
    function isHaveFile()
    {
        if(file_exists($this->abspath.$this->filepath.$this->fileName))return true;
        return false;
    }

    /**
     *创建分片文件名
     */
    function createBlockFileName()
    {
        $dirName = $this->abspath.$this->filepath.$this->fileMd5."/";
        if(!is_dir($dirName))@mkdir($dirName, 0777,true);
        return $dirName.$this->blobNum.".part";
    }

    /**
     *检测上传类型
     */
    function checkFileExtension()
    {
        if(!in_array(strtolower($this->fileExtension),$this->allowExtension))$this->jsonOut(CODE_FAIL,'文件名称格式错误');
		if(!in_array(strtolower($this->nowfileType),$this->allowFileType))$this->jsonOut(CODE_FAIL,'文件类型错误');
    }

    /**
     *返回数据
     */
    function jsonOut($code=0,$msg='Success',$data=[])
    {
        exit(json_encode(['code' => $code, 'msg' => $msg,'data' => $data],JSON_UNESCAPED_UNICODE));
    }
	
	/**
	*解压zip
	*/
	function unzipFile($filename, $path)
	{
		//先判断待解压的文件是否存在
		if(!file_exists($filename))$this->jsonOut(CODE_FAIL,'文件不存在！');
		//$starttime = explode(' ',microtime()); //解压开始的时间
		//将文件名和路径转成windows系统默认的gb2312编码，否则将会读取不到
		//$filename = iconv("utf-8","gb2312",$filename);
		//$path = iconv("utf-8","gb2312",$path);
		//打开压缩包
		$resource = zip_open($filename);
		$i = 1;
		//遍历读取压缩包里面的一个个文件
		while ($dir_resource = zip_read($resource)) {
			//如果能打开则继续
			if (zip_entry_open($resource,$dir_resource)) {
				//获取当前项目的名称,即压缩包里面当前对应的文件名
				$file_name = $path.zip_entry_name($dir_resource);
				//以最后一个“/”分割,再用字符串截取出路径部分
				$file_path = substr($file_name,0,strrpos($file_name, "/"));
				//如果路径不存在，则创建一个目录，true表示可以创建多级目录
				if(!is_dir($file_path)){
					mkdir($file_path,0777,true);
				}
				//如果不是目录，则写入文件
				if(!is_dir($file_name)){
					//读取这个文件
					$file_size = zip_entry_filesize($dir_resource);
					$file_content = zip_entry_read($dir_resource,$file_size);
					file_put_contents($file_name,$file_content);
					/**
					//最大读取6M，如果文件过大，跳过解压，继续下一个
					if($file_size<(1024*1024*30)){
						$file_content = zip_entry_read($dir_resource,$file_size);
						file_put_contents($file_name,$file_content);
					}else{
						$this->jsonOut(CODE_FAIL,'解压文件过大');
						//echo "<p> ".$i++." 此文件已被跳过原因：文件过大 ".$file_name." </p>";
					}
					*/
				}
				//关闭当前
				zip_entry_close($dir_resource);
			}
		}
		//关闭压缩包
		zip_close($resource);
			//$endtime = explode(' ',microtime()); //解压结束的时间
			//$thistime = $endtime[0]+$endtime[1]-($starttime[0]+$starttime[1]);
			//$thistime = round($thistime,3); //保留3为小数
			//echo "<p>解压完毕！，本次解压花费：$thistime 秒。</p>";
		return true;
	}

}

