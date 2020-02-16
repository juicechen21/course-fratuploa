<?php
#http://120.24.194.98:88/ 线上测试地址
define('CODE_FAIL', 1);
define('WEB_IMG','http://'.$_SERVER['HTTP_HOST'].'/uploads');
require __DIR__ . '/Fratupload.php';
$upload = new \Fratupload(date('ymd').'/');
$data = $upload->doUpload();
if($data['status'] == 200){
	require __DIR__ . '/Mysqld.php';
	$conn = new \Mysqld('127.0.0.1','root','root','elec_dy');
	$true = $conn->insertSql($data['url']);
	if($true){
		$upload->jsonOut(200,'Success',$data);
	}else{
		$upload->jsonOut(CODE_FAIL,'写入mysql失败');
	}
}else{
	$upload->jsonOut($data['status'],$data['msg'],$data);
}
