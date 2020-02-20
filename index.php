<?php
##电力上传激光点云数据
##http://192.168.5.59:88 内网测试地址
define('CODE_FAIL', 1);
define('WEB_IMG','http://'.$_SERVER['HTTP_HOST'].'/uploads');
define('HTTP_APIKEY','FyXP76QbrBFJzwfOQIu7mg==');

if($_SERVER['HTTP_APIKEY'] == null)
	jsonOut(CODE_FAIL,'请验证通行证!');
if($_SERVER['HTTP_APIKEY'] != HTTP_APIKEY)
	jsonOut(CODE_FAIL,'通行证错误!');

require __DIR__ . '/Fratupload.php';
$upload = new \Fratupload(date('ymd').'/');
$data = $upload->doUpload();
if($data['status'] == 200){
	require __DIR__ . '/Mysqld.php';
	$conn = new \Mysqld('192.168.5.59','root','root','elec_rtk');
	$true = $conn->insertSql($data['url']);
	if($true){
		jsonOut(200,'Success',$data);
	}else{
		jsonOut(CODE_FAIL,'写入mysql失败');
	}
}else{
	jsonOut($data['status'],$data['msg'],$data);
}

function jsonOut($code=0,$msg='Success',$data=[])
{
	exit(json_encode(['code'=>$code,'msg'=>$msg,'data'=>$data],JSON_UNESCAPED_UNICODE));
}
	
