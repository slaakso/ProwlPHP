<?php

class Prowl
{
	private $_version = '0.2.1';
	private $_obj_curl = null;
	private $_verified = false;
	private $_return_code;
	private $_remaining;
	private $_resetdate;

	private $_api_key = null;
	private $_api_domain = 'https://prowl.weks.net/publicapi';
	private $_url_verify = '/verify?apikey=%s';
	private $_url_push = '/add';
	
	private $_params = array(		// Accessible params [key => maxsize]
		'apikey' => 40,				// User API Key.
		//'providerkey' => 40,		// Provider key.
		'priority' => 2,			// Range from -2 to 2.
		'application' => 254,		// Name of the app.
		'event' => 1024,			// Name of the event.
		'description' => 10000,		// Description of the event.
	);
	
	public function __construct($apikey, $providerkey=null)
	{
		$this->_api_key = $apikey;
		$return = $this->_execute(sprintf($this->_url_verify, $apikey));
		
		if($return===false)
		{
			$this->_error_code = 500;
			return false;
		}
		
		$this->_verified = $this->_response($return);
	}
	
	public function push($params, $is_post=false)
	{
		if(!$this->_verified)
			return false;
		
		if($is_post)
			$post_params = '';
			
		$url = $is_post ? $this->_url_push : $this->_url_push . '?';
		$params = func_get_args();
		$params[0]['apikey'] = $this->_api_key;

		foreach($params[0] as $k => $v)
		{
			if(!isset($this->_params[$k]))
			{
				$this->_error_code = 400;
				return false;
			}
			if(strlen($v) > $this->_params[$k])
			{
				$this->_error_code = 10001;
				return false;
			}
			
			if($is_post)
				$post_params .= $k . '=' . urlencode($v) . '&';
			else
				$url .= $k . '=' . urlencode($v) . '&';
		}
		
		if($is_post)
			$params = substr($post_params, 0, strlen($post_params)-1);
		else
			$url = substr($url, 0, strlen($url)-1);
		
		$return = $this->_execute($url, $is_post ? true : false, $params);
		
		if($return===false)
		{
			$this->_error_code=500;
			return false;
		}
		
		return $this->_response($return);	
	}
	
	public function getError()
	{
		switch($this->_return_code)
		{
			case 200: 	return 'Request Successfull.';	break;
			case 400:	return 'Bad request, the parameters you provided did not validate.';	break;
			case 401: 	return 'The API key given is not valid, and does not correspond to a user.';	break;
			case 405:	return 'Method not allowed, you attempted to use a non-SSL connection to Prowl.';	break;
			case 406:	return 'Your IP address has exceeded the API limit.';	break;
			case 500:	return 'Internal server error, something failed to execute properly on the Prowl side.';	break;
			case 10001:	return 'Parameter value exceeds the maximum byte size.';	break;
			default:	return false;	break;
		}
	}
	
	public function getRemaining()
	{
		if(!$this->_verified)
			return false;
		
		return $this->_remaining;
	}
	
	public function getResetDate()
	{
		if(!$this->_verified)
			return false;
			
		return $this->_resetdate;
	}
	
	private function _execute($url, $is_post=false, $params=null)
	{
		$this->_obj_curl = curl_init($this->_api_domain . $url);
		curl_setopt($this->_obj_curl, CURLOPT_HEADER, 0);
		curl_setopt($this->_obj_curl, CURLOPT_USERAGENT, "ProwlPHP/" . $this->_version);
		curl_setopt($this->_obj_curl, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
		curl_setopt($this->_obj_curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($this->_obj_curl, CURLOPT_RETURNTRANSFER, 1);
		
		if($is_post)
		{
			curl_setopt($this->_obj_curl, CURLOPT_POST, 1);
			curl_setopt($this->_obj_curl, CURLOPT_POSTFIELDS, $params);
		}
		
		$return = curl_exec($this->_obj_curl);
		curl_close($this->_obj_curl);
		return $return;
	}
	
	private function _response($return)
	{
		$response = new SimpleXMLElement($return);
		
		if(isset($response->success))
		{
			$this->_return_code = (int)$response->success['code'];
			$this->_remaining = (int)$response->success['remaining'];
			$this->_resetdate = (int)$response->success['resetdate'];
		}
			else
		{
			$this->_return_code = $response->error['code'];
		}
		
		switch($this->_return_code)
		{
			case 200: 	return true;	break;
			default:	return false;	break;
		}
		
		unset($response);
	}
}

?>