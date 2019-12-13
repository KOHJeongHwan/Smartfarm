<?
$temp = $_GET['input_temp'];
$humi = $_GET['input_humi'];
$cds = $_GET['input_cds'];
$co2 = $_GET['input_co2'];
$soil = $_GET['input_soil'];
$result = "";

$mysql_hostname = "localhost";
$mysql_username = "20155329";
$mysql_password = "20155329";
$mysql_database = "smart_farm";

$conn = @mysqli_connect($mysql_hostname, $mysql_username, $mysql_password, $mysql_database);

if (!$conn)
{       
	$error = mysqli_connect_error();
	$errno = mysqli_connect_errno();
	echo "$errno: $error\n";
	exit();
}

if(!empty($temp)){
	$query = "update device_log set input_temp = ".(int)$temp;
	$result = mysqli_query($conn, $query);
} 
if(!empty($humi)){
	$query = "update device_log set input_humi = ".(int)$humi;
        $result = mysqli_query($conn, $query);
} 
if(!empty($cds)){
	$query = "update device_log set input_cds = ".(int)$cds;
        $result = mysqli_query($conn, $query);
}
if(!empty($co2)){
        $query = "update device_log set input_co2 = ".(int)$co2;
        $result = mysqli_query($conn, $query);
}
if(!empty($soil)){
        $query = "update device_log set input_soil = ".(int)$soil;
        $result = mysqli_query($conn, $query);
}


/*
$query = "insert into device_log(id, input_co2, input_cds, input_soil, input_humi, input_temp) value('testID001',".(int)$co2.",".(int)$cds.",".(int)$soil.",".(int)$humi.",".(int)$temp.")";
echo "쿼리 작성";
$result = mysqli_query($conn, $query);
echo "result 출력\n";
 */
if($result)
{
	//create a new cURL resource
	$ch = curl_init();
		
	// set URL and other appropriate options
	curl_setopt($ch, CURLOPT_URL, "http://13.209.3.92/index_Manual.html");
	curl_setopt($ch, CURLOPT_HEADER, 0);

	// grab URL and pass it to the browser
	curl_exec($ch);
		
	// close cURL resource, and free up system resources
	curl_close($ch);

}

echo "<script>alert(\"업데이트 되었습니다\");</script>";

?>
