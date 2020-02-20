<?php
class Mysqld
{
	public $conn;
	public function __construct($servername,$username,$password,$db)
    {
		$this->conn = new mysqli($servername,$username,$password,$db);
		if($this->conn->connect_errno){
			printf("Connect failed: %s\n", $this->conn->connect_error);
			exit();
		}
	}
	
	//查询sql
	public function selectSql()
	{
		$sql = "SELECT * FROM elec_cloud";
		$result = $this->conn->query($sql);
		var_dump($result);
		if ($result->num_rows > 0) {
			// 输出数据
			while($row = $result->fetch_assoc()) {
				var_dump($row);
			}
		} else {
			echo "0 结果";
		}
		$this->conn->close();
	}
	
	//插入sql
	public function insertSql($val)
	{
		$sql = "INSERT INTO elec_cloud(urladdress,status,time) VALUES ('".$val."','1',".time().")";
		$true = true;
		if($this->conn->query($sql) !== TRUE){
			$true = false;
		}
		$this->conn->close();
		return $true;
	}

	
	
}