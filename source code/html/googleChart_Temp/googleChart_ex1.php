<?php
	$mysql_hostname = "localhost";
	$mysql_username = "20175120";
	$mysql_password = "20175120";
	$mysql_database = "smart_farm";
	
	$conn = new mysqli($mysql_hostname, $mysql_username, $mysql_password, $mysql_database);
/*	
	if($conn){
		echo "MySQL접속성공<br>";
	}else{
		echo "MySQL접속실패<br>";
	}
 */	
	$query = "select * from sensor";
	$res = $conn->query($query);
	
	echo "id co2 cds soil humi temp date time<br>";
	
	if($res->num_rows > 0){
		while($row = $res->fetch_assoc()){
			echo $row['id'].'  '.$row['co2'].' '.$row['cds'].' '.$row['soil'].' '.$row['humi'].''.$row['temp'].' '.$row['date'].' '.$row['time'].'<br>';

		        $mysql_id = $row['id'];
		        $mysql_co2 = $row['co2'];
		        $mysql_cds = $row['cds'];
		        $mysql_soil = $row['soil'];
		        $mysql_humi = $row['humi'];
		        $mysql_temp = $row['temp'];
		        $mysql_date = $row['date'];
	 	        $mysql_time = $row['time'];
		}
	}else{
		echo "0 results";
	}
?>

<html>
	<head> 
		<script type="text/javascript" src="https://www.google.com/jsapi"></script>
		<script type="text/javascript">
			
			google.load("visualization", "1", {packages:["corechart"]});
			google.setOnLoadCallback(drawChart);

			function drawChart() {
	              		var data = google.visualization.arrayToDataTable([
			                ['Time', 'Temperature', 'Humi'],
			                ['2004',  25,	40], 
			                ['2005',  26,   15], 
			                ['2006',  23,   50], 
			                ['2007',  25,   25]
				]);

	       			var options = {
			                title: 'Have a good farm',
					vAxis: {title:'Value'}
					hAxis: {title:'Time'}
					curveType: 'function',
					legend: { position: 'bottom'}
				};

				var chart = new google.visualization.LineChart(document.getElementById('chart_div'));
				chart.draw(data, options);
	            }
		</script>
	</head>
	
	<body>
		<div id="chart_div" style="width: 900px; height: 500px;"></div>
	</body>
</html>
