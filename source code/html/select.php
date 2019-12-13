<?
$select = $_GET['category'];
$query = "";
$result = "";
$fetch = "";

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


if(isset($select)){
	switch ($select) {
	case 'strawberry':
		$query = "select * from growth_environment where plant = 'strawberry'";
		$result = mysqli_query($conn, $query);
		while($fetch = mysqli_fetch_array($result)){
			//echo "co2: ".$fetch['co2'].", cds: ".$fetch['cds'].", soil: ".$fetch['soil'].", humi:".$fetch['humi'].", temp: ".$fetch['temp'];
		}

		break;
	case 'pepper':
		$query = "select * from growth_environment where plant = 'pepper'";
		$result = mysqli_query($conn, $query);
		while($fetch = mysqli_fetch_array($result)){
			//echo "co2: ".$fetch['co2'].", cds: ".$fetch['cds'].", soil: ".$fetch['soil'].", humi:".$fetch['humi'].", temp: ".$fetch['temp'];
		}

		break;
	case 'pumpkin':
		$query = "select * from growth_environment where plant = 'pumpkin'";
		$result = mysqli_query($conn, $query);
		while($fetch = mysqli_fetch_array($result)){
			//echo "co2: ".$fetch['co2'].", cds: ".$fetch['cds'].", soil: ".$fetch['soil'].", humi:".$fetch['humi'].", temp: ".$fetch['temp'];
		}

		break;
	case 'sweet potato':
		$query = "select * from growth_environment where plant = 'sweet potato'";
		$result = mysqli_query($conn, $query);
		while($fetch = mysqli_fetch_array($result)){
			//echo "co2: ".$fetch['co2'].", cds: ".$fetch['cds'].", soil: ".$fetch['soil'].", humi:".$fetch['humi'].", temp: ".$fetch['temp'];
		}

		break;
	default:
		echo "default\n";
		break;
	}
}

if($result)
{

	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, "http://13.209.3.92/");
	curl_setopt($ch, CURLOPT_HEADER, 0);

	curl_exec($ch);

	curl_close($ch);


}

?>

