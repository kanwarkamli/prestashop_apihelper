<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

if (!function_exists('api'))
{
	$mc = new Memcache;
	$mc->addServer("127.0.0.1");

	function api($url, $cache_ttl=0, $compress=false)
	{
		/* Performs API call to Prestashop, with optional caching.
		 * @param string $url The API URL (including query strings)
		 * @param int $cache_ttl Seconds to cache the API result for.
		 * @return API result (already json-decoded).
		 * @param bool $compress Enable memcached compression (good for large text values).
		 */
		global $mc;

		$CI =& get_instance();
		if(!$CI->config->item('cache_env')){
			$CI->config->set_item('cache_env', 'live_');
		}
		$key = $CI->config->item('cache_env') . $url;

		// Return API result from memcache if it is cached
		if ($cache_ttl && ($cached_json = $mc->get($key)) !== FALSE) {
			return $cached_json;
		}

		// Append API key to URL query string
		if (strpos($url, "?") === false) {
			$api_url = sprintf("%swebapi/%s?apikey=%s", WEBAPIBASE, $url, KEY);
		} else {
			$api_url = sprintf("%swebapi/%s&apikey=%s", WEBAPIBASE, $url, KEY);
		}

		// Perform API call
		$result = api_curl($api_url);
		$json = json_decode($result, true);

		// Write API result to cache (only if $cache_ttl > 0)
		if ($cache_ttl && $json['code'] == 200) {
			$mc->set($key, $json, ($compress ? MEMCACHE_COMPRESSED : 0), $cache_ttl);
		}
		return $json;
	}

	function api_curl($url, $method = 'GET', $data = false, $headers = false, $returnInfo = false) {
		$ch = curl_init();

		if($method == 'POST') {
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POST, true);
			if($data !== false) {
				curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
			}
		} else {
			if($data !== false) {
				if(is_array($data)) {
					$dataTokens = array();
					foreach($data as $key => $value) {
						array_push($dataTokens, urlencode($key).'='.urlencode($value));
					}
					$data = implode('&', $dataTokens);
				}
				curl_setopt($ch, CURLOPT_URL, $url.'?'.$data);
			} else {
				curl_setopt($ch, CURLOPT_URL, $url);
			}
		}
		curl_setopt($ch, CURLOPT_FAILONERROR, false);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 180);
		if($headers !== false) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		}

		$contents = curl_exec($ch);

		if($returnInfo) {
			$info = curl_getinfo($ch);
		}

		curl_close($ch);

		if($returnInfo) {
			return array('contents' => $contents, 'info' => $info);
		} else {
			return $contents;
		}
	}

}
