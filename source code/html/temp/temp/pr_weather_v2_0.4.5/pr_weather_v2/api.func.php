<?php
require_once _XE_PATH_ . 'widgets/pr_weather_v2/region.class.php';

class WeatherDataClass
{
	private static $_timeout = 3;
	private static $_cache_key = 'widget_pr_weatherV2:XY:%s';
	private static $_aqicn_token = 'API Token';
	private static $_kma_key = 'ServiceKey';

	public static function getForecastData($geo)
	{
		$cache_key_fcst = sprintf(self::$_cache_key, $geo->x.'@'.$geo->y.':FCST');
		$cache_sec = (int)$_SESSION['pr_w_weather_cache_sec'];
		$cache_sec = $cache_sec >= 60 ? $cache_sec : 60 * 30;
		$is_fcst_new = false;
		$cached_fcst = null;
		$fcst_data = new stdClass();

		// 유효한 캐시가 있을경우 캐시 리턴
		$oCacheHandler = CacheHandler::getInstance('object');
		if($oCacheHandler->isSupport())
		{
			if(($cached_fcst = $oCacheHandler->get($cache_key_fcst)) !== false)
			{
				if($cached_fcst->mk_time >= (time() - $cache_sec))
					return $cached_fcst->data;
			}
		}

		// 기상청 단기예보
		$wkr_url = self::getApiUrl(self::$_kma_key, $geo, 3);
		if($kma = json_decode(self::file_get_contents_curl($wkr_url, self::$_timeout)))
		{
			if($kma->response->header->resultMsg === 'OK')
			{
				$is_fcst_new = true;
				$fcst_data->data = array();
				$array = self::getVarFromObj($kma->response->body->items->item, 3);
				$count = 0;
				foreach($array as $key => $val)
				{
					if(date('YmdH') * 100 < $key)
					{
						$tmpObj = new stdClass();
						$tmpObj->fcstDate = substr($key, 0, 8);
						$tmpObj->humidity = substr($val['humidity'], 0, -1);
						$tmpObj->temp = round($val['temp']);
						$tmpObj->pop = $val['pop'];
						$skyClass = self::getSkyAndClass($val['pty'], $val['sky'], substr($key, -4, 4));
						$tmpObj->txt = $skyClass[0];
						$tmpObj->class = $skyClass[1];
						$tmpObj->wind_class = self::getWindDirectionAndSpeed($val['vec'], $val['wsd'], 1);
						$tmpObj->wind_speed = $val['wsd'];
						$tmpObj->time = substr($key, -4, 2);
						$fcst_data->data[] = $tmpObj;

						if(15 <= ++$count)
							break;
					}
				}
			}
		}
		if(0 == count($fcst_data->data) && $cached_fcst)
		{
			$fcst_data->data = $cached_fcst->data;
		}

		if($oCacheHandler->isSupport())
		{
			if($is_fcst_new)
			{
				if($oCacheHandler->isValid($cache_key_fcst))
				{
					$oCacheHandler->delete($cache_key_fcst);
				}
				$fcst_data->mk_time = time();
				$oCacheHandler->put($cache_key_fcst, $fcst_data, 86400);
			}
		}

		return $fcst_data->data;
	}

	public static function getWeatherData($geo, $remake = true)
	{
		if(!isset($geo->x)  || !isset($geo->y) || !isset($geo->lat) || !isset($geo->lng))
		{
			return false;
		}

		$cache_key_aqi = sprintf(self::$_cache_key, $geo->x.'@'.$geo->y.':AQI');
		$cache_key_kma = sprintf(self::$_cache_key, $geo->x.'@'.$geo->y.':KMA');
		$cache_sec = (int)$_SESSION['pr_w_weather_cache_sec'];
		$cache_sec = $cache_sec >= 60 ? $cache_sec : 60 * 30;
		$cached_kma = null;
		$cached_aqi = null;
		$is_aqi_new = false;
		$is_kma_new = 0;
		$kma_data = null;
		$aqi_data = null;

		// 날씨, 공기질 두가지 캐시가 유효할때만 캐시 리턴
		$oCacheHandler = CacheHandler::getInstance('object');
		if($oCacheHandler->isSupport())
		{
			if(($cached_kma = $oCacheHandler->get($cache_key_kma)) !== false)
			{
				if($cached_kma->mk_time >= (time() - $cache_sec))
				{
					$kma_data = $cached_kma;
				}
			}
			if(($cached_aqi = $oCacheHandler->get($cache_key_aqi)) !== false)
			{
				if($cached_aqi->mk_time >= (time() - $cache_sec))
				{
					$aqi_data = $cached_aqi;
				}
			}

			if($kma_data && $aqi_data)
			{
				$kma_data->aqi = new stdClass();
				$kma_data->aqi = $aqi_data;

				return $kma_data;
			}
		}

		// 위젯 초기 로드시 속도를 위해 유효한 캐시가 없어도 그냥 리턴, 이후 ajax로 날씨정보 갱신
		if(!$remake)
		{
			return false;
		}


		// 유효 캐시가 없을때만 데이터 갱신 시도
		if(!$aqi_data)
		{
			// 공기질 : 새 데이터 작성 실패시 혹시 유효기간 지난 캐시라도 있으면 캐시 사용
			$aqi_data = new stdClass();
			self::initData($geo, $aqi_data, 'aqi');

			$aqicn_url = 'https://api.waqi.info/feed/geo:'.$geo->lat.';'.$geo->lng.'/?token='.self::$_aqicn_token;
			if($air = json_decode(self::file_get_contents_curl($aqicn_url, self::$_timeout)))
			{
				if($air->status === 'ok')
				{
					$is_aqi_new = true;
					$aqi_data = self::getAirQualityIndex((int)$air->data->aqi);
					$aqi_data->pm10 = $air->data->iaqi->pm10->v;
					$aqi_data->pm10_color = self::getAirQualityIndex((int)$aqi_data->pm10, 'color');
					$aqi_data->pm25 = $air->data->iaqi->pm25->v;
					$aqi_data->pm25_color = self::getAirQualityIndex((int)$aqi_data->pm25, 'color');
					$aqi_data->time = date('H:i', strtotime($air->data->time->s));
					$aqi_data->time_full = $air->data->time->s;
				}
			}
			if($aqi_data->time == '' && $cached_aqi)
			{
				$aqi_data = $cached_aqi;
			}
		}

		// 유효 캐시가 없을때만 데이터 갱신 시도
		if(!$kma_data)
		{
			// 기상청 초단기실황
			$kma_data = new stdClass();
			self::initData($geo, $kma_data, 'kma');
			$wkr_url = self::getApiUrl(self::$_kma_key, $geo, 1);
			if($kma = json_decode(self::file_get_contents_curl($wkr_url, self::$_timeout)))
			{
				if($kma->response->header->resultMsg === 'OK')
				{
					$is_kma_new++;
					$array = self::getVarFromObj($kma->response->body->items->item, 1);
					$kma_data->weather->humidity = $array['humidity'];
					$kma_data->weather->temp = $array['temp'];
					$kma_data->weather->rainfall = $array['rainfall'];
					$skyClass = self::getSkyAndClass($array['pty'], $array['sky']);
					$kma_data->weather->txt = $skyClass[0];
					$kma_data->weather->class = $skyClass[1];
					$kma_data->weather->wind = self::getWindDirectionAndSpeed($array['vec'], $array['wsd']);
					$kma_data->weather->time = date('H:i');

					if(strpos($kma_data->weather->txt, '눈') !== false)
						$kma_data->weather->falling = 'S';
					elseif(strpos($kma_data->weather->txt, '비') !== false)
						$kma_data->weather->falling = 'R';
				}
			}
			if($kma_data->weather->time == '' && $cached_kma)
			{
				$kma_data->weather = $cached_kma->weather;
			}

			// 기상청 초단기예보
			$wkr_url = self::getApiUrl(self::$_kma_key, $geo, 2);
			if($kma = json_decode(self::file_get_contents_curl($wkr_url, self::$_timeout)))
			{
				if($kma->response->header->resultMsg === 'OK')
				{
					$is_kma_new++;
					$array = self::getVarFromObj($kma->response->body->items->item, 2);
					foreach($array as $key => $val)
					{
						if(date('H') < ($key / 100) || date('Ymd') < $val['fcstDate'])
						{
							$tmpObj = new stdClass();
							$tmpObj->humidity = $val['humidity'];
							$tmpObj->temp = round($val['temp']);
							$tmpObj->rainfall = $val['rainfall'];
							$skyClass = self::getSkyAndClass($val['pty'], $val['sky'], $key);
							$tmpObj->txt = $skyClass[0];
							$tmpObj->class = $skyClass[1];
							$tmpObj->wind = self::getWindDirectionAndSpeed($val['vec'], $val['wsd']);
							$tmpObj->time = ($key / 100) . '시 예보';
							$kma_data->forecast[] = $tmpObj;
						}
					}
				}
			}
			if(0 == count($kma_data->forecast) && $cached_kma)
			{
				$kma_data->forecast = $cached_kma->forecast;
			}
		}

		// 캐시 저장 : 새로 수집한 완벽한 데이터 일때만 캐시 갱신
		if($oCacheHandler->isSupport())
		{
			if(2 <= $is_kma_new)
			{
				if($oCacheHandler->isValid($cache_key_kma))
				{
					$oCacheHandler->delete($cache_key_kma);
				}
				$kma_data->mk_time = time();
				$oCacheHandler->put($cache_key_kma, $kma_data, 86400);
			}

			if($is_aqi_new)
			{
				if($oCacheHandler->isValid($cache_key_aqi))
				{
					$oCacheHandler->delete($cache_key_aqi);
				}
				$aqi_data->mk_time = time();
				$oCacheHandler->put($cache_key_aqi, $aqi_data, 86400);
			}
		}

		$kma_data->aqi = new stdClass();
		$kma_data->aqi = $aqi_data;

		return $kma_data;
	}

	// obj배열에서 필요한 값들을 정리
	public static function getVarFromObj($obj, $type)
	{
		$var = array();
		$ftime = $type;
		$value = 'obsrValue';
		$key_temp = 'T1H';
		if(2 == $type)
			$value = 'fcstValue';
		elseif(3 == $type)
		{
			$value = 'fcstValue';
			$key_temp = 'T3H';
		}

		foreach($obj as $arr)
		{
			if(2 == $type)
			{
				$ftime = $arr->fcstTime;
				$var[$ftime]['fcstDate'] = $arr->fcstDate;
			}
			elseif(3 == $type)
			{
				$ftime = $arr->fcstDate . $arr->fcstTime;
				if($arr->category === 'POP')
					$var[$ftime]['pop'] = $arr->$value;
			}

			if($arr->category === 'REH')
				$var[$ftime]['humidity'] = $arr->$value . '%';
			elseif($arr->category === $key_temp)
				$var[$ftime]['temp'] = $arr->$value;
			elseif($arr->category === 'RN1')
				$var[$ftime]['rainfall'] = $arr->$value;
			elseif($arr->category === 'PTY')
				$var[$ftime]['pty'] = $arr->$value;
			elseif($arr->category === 'SKY')
				$var[$ftime]['sky'] = $arr->$value;
			elseif($arr->category === 'VEC')
				$var[$ftime]['vec'] = $arr->$value;
			elseif($arr->category === 'WSD')
				$var[$ftime]['wsd'] = $arr->$value;
		}

		if(1 == $type)
			return $var[$type];
		else
			return $var;
	}

	// 날씨상황[0]과 아이콘 출력위한 클래스[1]를 배열로 반환
	public static function getSkyAndClass($pty, $sky, $time = 9999)
	{
		$time = 9999 === $time ? date('H') * 100 : $time;
		$str = array();
		if(0 < intval($pty))
		{
			$pty_arr = array('1' => '비', '2' => '눈비', '3' => '눈');
			$str[0] = $pty_arr[$pty];
			$sky = 4;
		}
		else
		{
			$sky_arr = array('1' => '맑음', '2' => '구름조금', '3' => '구름많음', '4' => '흐림');
			$str[0] = $sky_arr[$sky];
		}

		// 클래스(날씨 아이콘)
		$class = intval($sky) + intval($pty);
		$class = 7 == $class ? $class + 1 : $class;
		$str[1] = 'DB0' . $class;
		$str[1] = (1800 < $time || 600 > $time) ? $str[1] . '_N' : $str[1];

		return $str;
	}

	// API url 출력 ($num: 1-초단기실황, 2-초단기예보, 3-동네예보)
	public static function getApiUrl($kma_key, $geo, $num)
	{
		$basedate = '20151201';
		$basetime = '0600';
		$update_time = 40;
		$data_min = '00';
		$rows = 10;
		$wkr_url = 'http://newsky2.kma.go.kr/service/SecndSrtpdFrcstInfoService2/ForecastGrib';

		if(2 == $num)
		{
			$update_time = 45;
			$data_min = '30';
			$rows = 40;
			$wkr_url = 'http://newsky2.kma.go.kr/service/SecndSrtpdFrcstInfoService2/ForecastTimeData';
		}

		if(3 == $num)
		{
			$rows = 400;
			$wkr_url = 'http://newsky2.kma.go.kr/service/SecndSrtpdFrcstInfoService2/ForecastSpaceData';
			$ts = strtotime("Now");
			if(2 > date('H', $ts))
			{
				$basetime = '2300';
				$ts = strtotime('-1 days');
			}
			elseif(5 > date('H', $ts))
				$basetime = '0200';
			elseif(8 > date('H', $ts))
				$basetime = '0500';
			elseif(11 > date('H', $ts))
				$basetime = '0800';
			elseif(14 > date('H', $ts))
				$basetime = '1100';
			elseif(17 > date('H', $ts))
				$basetime = '1400';
			elseif(20 > date('H', $ts))
				$basetime = '1700';
			elseif(23 > date('H', $ts))
				$basetime = '2000';

			$basedate = date('Ymd', $ts);
		}
		else
		{
			if($update_time >= date('i'))
			{
				$basetime = date('H', strtotime('-1 hours')) . $data_min;
				$basedate = date('Ymd', strtotime('-1 hours'));
			}
			else
			{
				$basetime = date('H') . $data_min;
				$basedate = date('Ymd');
			}
		}

		$wkr_url .= '?' . urlencode('ServiceKey') . '=' . $kma_key;
		$wkr_url .= '&' . urlencode('ServiceKey') . '=' . urlencode($kma_key);
		$wkr_url .= '&' . urlencode('base_date') . '=' . urlencode($basedate);
		$wkr_url .= '&' . urlencode('base_time') . '=' . urlencode($basetime);
		$wkr_url .= '&' . urlencode('nx') . '=' . urlencode($geo->x);
		$wkr_url .= '&' . urlencode('ny') . '=' . urlencode($geo->y);
		$wkr_url .= '&' . urlencode('numOfRows') . '=' . urlencode($rows);
		$wkr_url .= '&' . urlencode('pageNo') . '=' . urlencode('1');
		$wkr_url .= '&' . urlencode('_type') . '=' . urlencode('json');

		return $wkr_url;
	}

	// 각도로 제공되는 풍향을 대략 분류 + 풍속
	public static function getWindDirectionAndSpeed($vec, $wsd, $type = 0)
	{
		$ivec = intval($vec);
		$str = null;
		if(30 > $ivec)
			$str = array('북', 'w00');
		elseif(60 > $ivec)
			$str = array('북동', 'w01');
		elseif(120 > $ivec)
			$str = array('동', 'w02');
		elseif(150 > $ivec)
			$str = array('남동', 'w03');
		elseif(210 > $ivec)
			$str = array('남', 'w04');
		elseif(240 > $ivec)
			$str = array('남서', 'w05');
		elseif(300 > $ivec)
			$str = array('서', 'w06');
		elseif(330 > $ivec)
			$str = array('북서', 'w07');
		else
			$str = array('북', 'w00');

		if(1 === $type)
			return $str[$type];
		else
			return $str[$type] . ' ' . $wsd . 'm/s';
	}

	public static function getAirQualityIndex($aqi, $type = 'obj')
	{
		$scale = new stdClass();
		$scale->val = $aqi;

		if(-999 == $aqi) 
		{
			$scale->bgcolor = '#666';
			$scale->color = '#fff';
			$scale->level = '-';
		}
		elseif(300 < $aqi) 
		{
			$scale->bgcolor = '#7e0023';
			$scale->color = '#fff';
			$scale->level = '위험';
		}
		elseif(200 < $aqi)
		{
			$scale->bgcolor = '#660099';
			$scale->color = '#fff';
			$scale->level = '매우나쁨';
		}
		elseif(150 < $aqi)
		{
			$scale->bgcolor = '#cc0033';
			$scale->color = '#fff';
			$scale->level = '나쁨';
		}
		elseif(100 < $aqi)
		{
			$scale->bgcolor = '#ff9933';
			$scale->color = '#fff';
			$scale->level = '약간나쁨';
		}
		elseif(50 < $aqi)
		{
			$scale->bgcolor = '#ffde33';
			$scale->color = '#000';
			$scale->level = '보통';
		}
		else
		{
			$scale->bgcolor = '#009966';
			$scale->color = '#fff';
			$scale->level = '좋음';
		}

		return $type == 'obj' ? $scale : $scale->bgcolor;
	}

	public static function file_get_contents_curl($url, $timeout = 5)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_URL, $url);
		$data = curl_exec($ch);
		$errno = curl_errno($ch);
		//$errmsg = curl_error($ch);
		curl_close($ch);

		return $errno ? false : $data;
	}

	public static function initData($geo, &$data, $type)
	{
		if('aqi' == $type)
		{
			$data = self::getAirQualityIndex(-999);
			$data->pm10 = '-';
			$data->pm25 = '-';
			$data->time = '';
			$data->time_full = '-';
		}
		else
		{
			$data->rid = Region::getRgnList($geo->x.'@'.$geo->y, 'xy2rid');
			$data->weather = new stdClass();
			$data->weather->falling = 'N';

			$data->weather->class = '';
			$data->weather->txt = '-';
			$data->weather->temp = '-';
			$data->weather->wind = '-';
			$data->weather->humidity = '-';
			$data->weather->rainfall = '-';
			$data->weather->time = '';

			$data->forecast = array();
		}
	}

	public static function Array2Str($array, $selector = '', $step1 = ':', $step2 = '|')
	{
		$str = array();
		foreach($array as $key => $val)
		{
			$str[] = $key . $step1 . ($selector ? $val[$selector] : $val);
		}
		return implode($step2, $str);
	}
}

?>
