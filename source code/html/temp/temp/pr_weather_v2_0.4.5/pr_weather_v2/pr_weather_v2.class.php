<?php
require _XE_PATH_ . 'widgets/pr_weather_v2/api.func.php';

class pr_weather_v2 extends WidgetHandler
{
	function proc($args)
	{
		$widget_info = new stdClass();
		$widget_info->colorset = $args->colorset;
		$widget_info->is_act = false;
		$widget_info->trace = false;
		$widget_info->req_load = false;
		$widget_info->show_fcst = $args->show_fcst;

		if($args->act_level == '1' || Context::get('is_logged'))
		{
			$widget_info->is_act = true;
			$widget_info->kmaurl = Mobile::isMobileCheckByAgent() ? 'http://m.kma.go.kr/m/forecast/forecast_01.jsp' : 'http://www.weather.go.kr/weather/forecast/timeseries.jsp';
			$args->link_addon = (int)$args->link_addon;
			$geo_valid_sec = (int)$args->trace_min;
			$geo_valid_sec = $geo_valid_sec > 0 ? $geo_valid_sec * 60 : 0;
			$cache_sec = (int)$args->cache_min;
			$cache_sec = $cache_sec > 0 ? $cache_sec * 60 : 60 * 30;
			$_SESSION['pr_w_weather_cache_sec'] = $cache_sec;
			$cookie_life = time() + (86400 * 365 * 10);

			//기본 지역코드 : 1114057000 (서울 중구 필동)
			$default_rid = (strlen($args->rid) == 10 && is_numeric($args->rid)) ? $args->rid : '1114057000';			
			
			/* 이전버전 쿠키 처리 */
			self::renewCookie($cookie_life);
			
			$geo = null;
			if(isset($_COOKIE['_pr_w_weather_geo']))
			{
				$geo = json_decode($_COOKIE['_pr_w_weather_geo']);
			}
			else
			{
				$rid_info = Region::getRgnList($default_rid);
				$geo = new stdClass();
				$geo->x = $rid_info['x'];
				$geo->y = $rid_info['y'];
				$geo->lat = $rid_info['lat'];
				$geo->lng = $rid_info['lon'];

				$geo->rid = $default_rid;
				$geo->isgeo = 'default';
				$geo->falling = 'N';
				$geo->mktime = time();
				setcookie('_pr_w_weather_geo', json_encode($geo), 0, '/');
			}
			$widget_info->isgeo = $geo->isgeo;

			if($result = WeatherDataClass::getWeatherData($geo, false))
			{
				$widget_info->data = new stdClass();
				$widget_info->data = $result;
				$widget_info->address = Region::getRgnList($geo->rid, 'sAddr');
				if('1' == $args->show_fcst)
				{
					$widget_info->data->short_fcst = array();
					$widget_info->data->short_fcst = WeatherDataClass::getForecastData($geo);
					$arr_tmp = array();
					foreach($widget_info->data->short_fcst as $val)
						$arr_tmp[] = $val->temp;
					$widget_info->data->short_fcst_temp = '[' . implode(',', $arr_tmp) . ']';
				}
				
				$falling = $result->weather->falling;
			}
			else
			{
				$widget_info->req_load = true;
				$widget_info->data = new stdClass();
				$widget_info->data->weather = new stdClass();
				$widget_info->data->aqi = new stdClass();
				$widget_info->data->weather->class = 'DB01';
				$widget_info->data->weather->txt = '맑음';
				$widget_info->data->weather->temp = 0;
				$widget_info->data->weather->wind = '-';
				$widget_info->data->weather->humidity = '-';
				$widget_info->data->weather->time = null;
				$widget_info->data->aqi->level = '-';
				$widget_info->data->aqi->bgcolor = '';
				$widget_info->data->aqi->color = '';
				$widget_info->data->aqi->pm10 = '-';
				$widget_info->data->aqi->pm25 = '-';
				$widget_info->data->aqi->time = '00:00';
				$widget_info->data->forecast = array();
				$widget_info->data->short_fcst = array();
				if('1' == $args->show_fcst)
				{
					$widget_info->data->short_fcst = WeatherDataClass::getForecastData($geo);
					$arr_tmp = array();
					foreach($widget_info->data->short_fcst as $val)
						$arr_tmp[] = $val->temp;
					$widget_info->data->short_fcst_temp = '[' . implode(',', $arr_tmp) . ']';
				}
				
				$falling = $geo->falling;
			}


			//위치 정보 유효기간 확인
			if(60 <= $geo_valid_sec && $geo->isgeo == 'on' && (int)$geo->mktime < time() - $geo_valid_sec)
			{
				$widget_info->trace = true;
			}

			/* Snow Falling 애드온과 연동을위한 변수 설정 */
			self::setSnowfallValue($args->link_addon, $falling);

		}

		Context::set('widget_info', $widget_info);

		// Compile a template
		$tpl_path = sprintf('%sskins/%s', $this->widget_path, $args->skin);		
		$oTemplate = TemplateHandler::getInstance();
		return $oTemplate->compile($tpl_path, 'weather');
	}

	function renewCookie($cookie_life)
	{
		if(isset($_COOKIE['_pr_w_weather_geo']))
		{
			$geo = json_decode($_COOKIE['_pr_w_weather_geo']);

			if($geo->isgeo)
			{
				return;
			}

			$geo->isgeo = 'on';
			$geo->mktime = time();
			$geo->falling = 'N';
			setcookie('_pr_w_weather_geo', json_encode($geo), (int)$cookie_life, '/');
		}
		elseif(isset($_COOKIE['_pr_w_weather_rid']))
		{
			$geo = json_decode($_COOKIE['_pr_w_weather_rid']);
			$geo->isgeo = 'off';
			$geo->mktime = time();
			$geo->falling = 'N';
			setcookie('_pr_w_weather_geo', json_encode($geo), (int)$cookie_life, '/');
			unset($_COOKIE['_pr_w_weather_rid']);
			setcookie('_pr_w_weather_rid', '', time() - 3600, '/');
		}
	}

	function setSnowfallValue($link_addon, $status)
	{
		if(0 < $link_addon && $status == 'S')
		{
			if($link_addon == 1)
			{
				Context::set('pr_is_snowing', 'Y');
				if(isset($_SESSION['pr_is_snowing']))
				{
					unset($_SESSION['pr_is_snowing']);
				}
			}
			else
			{
				$_SESSION['pr_is_snowing'] = 'Y';
			}
		}
		else
		{
			if(isset($_SESSION['pr_is_snowing']))
			{
				unset($_SESSION['pr_is_snowing']);
			}
		}
	}

}
