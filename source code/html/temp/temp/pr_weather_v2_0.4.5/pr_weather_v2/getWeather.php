<?php
if(!preg_match('/'.$_SERVER['HTTP_HOST'].'/', $_SERVER['HTTP_REFERER']))
{
	print json_encode(array('success' => false, 'data' => 'ERROR_REFERER'));
	die();
}

define('__XE__', TRUE);
define('_XE_PATH_', str_replace('widgets/pr_weather_v2', '', str_replace('\\', '/', __DIR__)));
require _XE_PATH_ . 'config/config.inc.php';
require _XE_PATH_ . 'widgets/pr_weather_v2/api.func.php';
$oContext = Context::getInstance();
$oContext->init();

$success = false;
$data = '';
$region = '';
$output_type = '';
$isgeo = false;
$debug = '...';
$emsg = '';
$loc = $_POST['loc'];
$cookie_life = time() + (86400 * 365 * 10);

if($loc == 'Load')
{
	if(isset($_COOKIE['_pr_w_weather_geo']))
	{
		$geo = json_decode($_COOKIE['_pr_w_weather_geo']);
		$data = WeatherDataClass::getWeatherData($geo);
		$success = true;
		$isgeo = ($geo->isgeo == 'on') ? true : false;
		$region = Region::getRgnList($geo->rid, 'sAddr');
		$output_type = 'html';

		$geo->falling = $data->weather->falling;
		$cookie_life = ($geo->isgeo == 'default') ? 0 : $cookie_life;
		setcookie('_pr_w_weather_geo', json_encode($geo), (int)$cookie_life, '/');
	}
	else
	{
		$emsg = 'COOKIE_NOT_ALOAD';
	}
}
elseif(is_numeric($loc))
{
	$length = strlen($loc);
	if($length == 10)
	{
		$rid_info = Region::getRgnList($loc);
		$geo = new stdClass();
		$geo->x = $rid_info['x'];
		$geo->y = $rid_info['y'];
		$geo->lat = $rid_info['lat'];
		$geo->lng = $rid_info['lon'];
		$geo->rid = $loc;
		$geo->isgeo = 'off';
		$geo->mktime = time();

		if($data = WeatherDataClass::getWeatherData($geo))
		{
			$geo->falling = $data->weather->falling;
			$success = true;
			$region = Region::getRgnList($loc, 'sAddr');
			$output_type = 'html';

			setcookie('_pr_w_weather_geo', json_encode($geo), (int)$cookie_life, '/');
		}
		else
		{
			$emsg = 'GET_W_DATA_FAIL_LOC';
		}
	}
	elseif($length == 1 || $length == 2 || $length == 5)
	{
		$selector = ($length == 5 || $loc == '36') ? 'name' : '';
		$data = WeatherDataClass::Array2Str(Region::getRgnList($loc), $selector);
		$success = true;
		$output_type = 'list';
	}
}
elseif($geo = json_decode($loc))
{
	if($data = WeatherDataClass::getWeatherData($geo))
	{
		$success = true;
		$isgeo = true;
		$region = Region::getRgnList($data->rid, 'sAddr');
		$output_type = 'html';
		$geo->falling = $data->weather->falling;
		$geo->rid = $data->rid;
		$geo->isgeo = 'on';
		$geo->mktime = time();

		setcookie('_pr_w_weather_geo', json_encode($geo), (int)$cookie_life, '/');
	}
	else
	{
		$emsg = 'GET_W_DATA_FAIL_GEO';
	}
}

print json_encode(array('success' => $success, 'data' => $data, 'region' => $region, 'type' => $output_type, 'isgeo' => $isgeo, 'debug' => $debug, 'emsg' => $emsg));
$oContext->close();

?>
