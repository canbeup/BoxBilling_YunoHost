<?php
/*
	PHP class to interact with YunoHost API.
	YunoHost: https://yunohost.org/
	The code was inspired by CpuID's Proxmox 2.0 API Client for PHP: https://github.com/CpuID/pve2-api-php-client

	Copyright (c) 2016 scith https://github.com/scith
	Licensed under the GNU AFFERO General Public License. See LICENCE file.
*/

namespace Box\Mod\Serviceyunohostshared;

class OVH_API {
	// URLs to communicate with OVH API
	private $endpoints = array(
        'ovh-eu'        => 'https://api.ovh.com/1.0',
        'ovh-ca'        => 'https://ca.api.ovh.com/1.0',
        'kimsufi-eu'    => 'https://eu.api.kimsufi.com/1.0',
        'kimsufi-ca'    => 'https://ca.api.kimsufi.com/1.0',
        'soyoustart-eu' => 'https://eu.api.soyoustart.com/1.0',
        'soyoustart-ca' => 'https://ca.api.soyoustart.com/1.0',
        'runabove-ca'   => 'https://api.runabove.com/1.0',
    );
	private $endpoint = null;
	private $application_key = null;
	private $application_secret = null;
	private $consumer_key = null;
	private $time_delta = null;

	/**
     * Construct a new wrapper instance
     *
     * @param string $application_key    key of your application.
     *                                   For OVH APIs, you can create a application's credentials on
     *                                   https://api.ovh.com/createApp/
     * @param string $application_secret secret of your application.
     * @param string $api_endpoint       name of api selected
     * @param string $consumer_key       If you have already a consumer key, this parameter prevent to do a
     *                                   new authentication
     *
     * @throws Exceptions\InvalidParameterException if one parameter is missing or with bad value
     */
	public function __construct ($application_key,$application_secret,$api_endpoint,$consumer_key = null) {
        if (!isset($application_key)) {
            throw new Exception("Application key parameter is empty");
        }
        if (!isset($application_secret)) {
            throw new Exception("Application secret parameter is empty");
        }
        if (!isset($api_endpoint)) {
            throw new Exception("Endpoint parameter is empty");
        }
        if (!array_key_exists($api_endpoint, $this->endpoints)) {
            throw new Exception("Unknown provided endpoint");
        }

        $this->application_key    = $application_key;
        $this->endpoint           = $this->endpoints[$api_endpoint];
        $this->application_secret = $application_secret;
        $this->consumer_key       = $consumer_key;
        $this->time_delta         = null;
	}
	
	/**
     * Calculate time delta between local machine and API's server
     *
     * @throws \GuzzleHttp\Exception\ClientException if http request is an error
     * @return int
     */
    private function calculateTimeDelta()
    {
        if (!isset($this->time_delta)) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $this->endpoint . "/auth/time");
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			$output = curl_exec($ch);
			curl_close($ch);
			unset($ch);
			
			$serverTimestamp = (int)(String)$output;
			$this->time_delta = $serverTimestamp - (int)\time();
        }
        return $this->time_delta;
    }
	
    /**
     * Request a consumer key from the API and the validation link to
     * authorize user to validate this consumer key
     *
     * @param array  $accessRules list of rules your application need.
     * @param string $redirection url to redirect on your website after authentication
     *
     * @return mixed
     * @throws \GuzzleHttp\Exception\ClientException if http request is an error
     
    public function requestCredentials(
        array $accessRules,
        $redirection = null
    ) {
        $parameters              = new \StdClass();
        $parameters->accessRules = $accessRules;
        $parameters->redirection = $redirection;
        //bypass authentication for this call
        $response = $this->action(
            'POST',
            '/auth/credential',
            $parameters,
            false
        );
        $this->consumer_key = $response["consumerKey"];
        return $response;
    }
	*/
	
	/**
     * This is the main method of this wrapper. It will
     * sign a given query and return its result.
     *
     * @param string               $method           HTTP method of request (GET,POST,PUT,DELETE)
     * @param string               $path             relative url of API request
     * @param \stdClass|array|null $content          body of the request
     * @param bool                 $is_authenticated if the request use authentication
     *
     * @return array
     */
    private function action($action_path, $http_method, $put_post_parameters = null, $is_authenticated = true)
    {
		// Check if we have a prefixed / on the path, if not add one.
		if (substr($action_path, 0, 1) != "/") {
			$action_path = "/".$action_path;
		}
		
		// Check if logged in
		if ($is_authenticated) {
            if (!isset($this->time_delta)) {
                $this->calculateTimeDelta();
            }
            $now = time() + $this->time_delta;
            $headers['X-Ovh-Timestamp'] = $now;
            if (isset($this->consumer_key)) {
                $toSign                     = $this->application_secret . '+' . $this->consumer_key . '+' . $method
                    . '+' . $url . '+' . $body . '+' . $now;
                $signature                  = '$1$' . sha1($toSign);
                $headers['X-Ovh-Consumer']  = $this->consumer_key;
                $headers['X-Ovh-Signature'] = $signature;
            }
        }
        $headers = array(
            'Content-Type'      => 'application/json; charset=utf-8',
            'X-Ovh-Application' => $this->application_key,
        );
		
		// Prepare cURL resource.
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->endpoint . $action_path;);
		
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		// Lets decide what type of action we are taking...
		switch ($http_method) {
			case "GET":
				curl_setopt($ch, CURLOPT_HTTPGET, true);
				break;
			case "PUT":
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");

				// Set "POST" data.
				$action_postfields_string = http_build_query($put_post_parameters);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $action_postfields_string);
				unset($action_postfields_string);
				break;
			case "POST":
				curl_setopt($ch, CURLOPT_POST, true);

				// Set POST data.
				$action_postfields_string = http_build_query($put_post_parameters);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $put_post_parameters);
				unset($action_postfields_string);
				break;
			case "DELETE":
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
				// No "POST" data required, the delete destination is specified in the URL.
				break;
			default:
				throw new \Exception("Error - Invalid HTTP Method specified.", 5);	
				return false;
		}

		$action_response = curl_exec($ch);
		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		
		curl_close($ch);
		unset($ch);

		$header_response = substr($action_response, 0, $header_size);
		$body_response = substr($action_response, $header_size);
		$action_response_array = json_decode($body_response, true);
		
		// Full Log
		/* $action_response_export = var_export($action_response_array, true);
		error_log("------" .
			"Headers:{$header_response} -----" .
			"Data:{$body_response} " .
			"------");

		unset($action_response);
		unset($action_response_export);*/

		// Parse response, confirm HTTP response code etc.
		if (substr($header_response, 0, 9) == "HTTP/1.1 ") {
			$http_response_line = explode(" ", $header_response);
			// If successful response, return response
			if ($http_response_line[1] == "200" OR $http_response_line[1] == "204" OR $http_response_line[3] == "201") {
				if ($http_method == "PUT" OR $http_method == "DELETE") {
					return true;
				} else {
					return $action_response_array;
				}
			} else {
				// Failed response
				error_log("API Request Failed: {$body_response}" .
					" / Request - {$header_response}");
				return $body_response; // Return error message
			}
		} else {
			error_log("Error - Invalid HTTP Response" . $header_response);
			return false;
		}
		
		if (!empty($action_response_array)) {
			return $action_response_array;
		} else {
			error_log("\$action_response_array is empty. Returning false.\n" . 
				var_export($action_response_array, true));
			return false;
		}
	}

	/*
	 * object/array? get (string action_path)
	 */
	public function get ($action_path) {
		return $this->action($action_path, "GET");
	}

	/*
	 * bool put (string action_path, array parameters)
	 */
	public function put ($action_path, $parameters) {
		return $this->action($action_path, "PUT", $parameters);
	}

	/*
	 * bool post (string action_path, array parameters)
	 */
	public function post ($action_path, $parameters) {
		return $this->action($action_path, "POST", $parameters);
	}

	/*
	 * bool delete (string action_path)
	 */
	public function delete ($action_path) {
		return $this->action($action_path, "DELETE");
	}

	// Logout not required? Cookie lifetime?
}
?>
