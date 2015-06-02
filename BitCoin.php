<?php

/**
 * Class to facilitate the communication to and from a Bitcoin JSON-RPC server.
 *
 * @author  Liam W ( https://github.com/xfliam )
 * @license http://creativecommons.org/licenses/by-sa/4.0/
 */
class BitCoin
{
	/**
	 * Connect using SSL.
	 */
	const PROTOCOL_SSL = "https";

	/**
	 * Connect using plain http.
	 */
	const PROTOCOL_HTTP = "http";

	protected $_user;
	protected $_password;
	protected $_host;
	protected $_port;

	protected $_protocol = 'http';
	protected $_cert = null;

	protected $_status;
	protected $_error;
	protected $_response;
	protected $_rawResponse;

	protected $_id = 0;

	/**
	 * @param string $user     Bitcoin-RPC username
	 * @param string $password Bitcoin-RPC password
	 * @param string $host     Bitcoin host (default 127.0.0.1)
	 * @param string $port     Bitcoin port (8332)
	 *
	 * @throws Exception
	 */
	public function __construct($user, $password, $host = '127.0.0.1', $port = '8332')
	{
		if (!extension_loaded('curl'))
		{
			throw new \Exception("Curl is not active on this PHP installation. Please install the curl PHP library.");
		}

		$this->_user = $user;
		$this->_password = $password;
		$this->_host = $host;
		$this->_port = $port;

		set_error_handler(array(
			$this,
			'_onError'
		));
	}

	/**
	 * Set the protocol used to connect to the JSON-RPC server.
	 *
	 * @param string $protocol The protocol to use. The PROTOCOL_ constants should be used.
	 *
	 * @throws BitCoinException Thrown if invalid protocol value passed.
	 */
	public function setProtocol($protocol)
	{
		switch ($protocol)
		{
			case self::PROTOCOL_SSL:
				$this->_protocol = "https";
				break;
			case self::PROTOCOL_HTTP:
				$this->_protocol = "http";
				break;
			default:
				throw new BitCoinException("Invalid Protocol");
		}
	}

	/**
	 * Set the certificate to be used to confirm a HTTPS connection.
	 *
	 * This will set the protocol to {@see PROTOCOL_SSL}.
	 *
	 * @param $filePath
	 *
	 * @throws BitCoinException
	 */
	public function setCertificate($filePath)
	{
		if (!file_exists(realpath($filePath)))
		{
			throw new BitCoinException("Invalid Certificate Path");
		}

		$this->_cert = realpath($filePath);
		$this->_protocol = "https";
	}

	/**
	 * Call a method and return the response as an associative array.
	 *
	 * @param string $methodName The method to call.
	 * @param array  $parameters Parameters for the method.
	 *
	 * @return array|false
	 * @throws BitCoinException
	 */
	public function callMethod($methodName, array $parameters = array())
	{
		$this->_status = null;
		$this->_response = null;
		$this->_rawResponse = null;
		$this->_error = null;

		$parameters = array_values($parameters);

		$this->_id++;

		$input = json_encode(array(
			'method' => $methodName,
			'params' => $parameters,
			'id' => $this->_id
		));

		$curlResource = curl_init("{$this->_protocol}://{$this->_user}:{$this->_password}@{$this->_host}:{$this->_port}");
		$curlOptions = array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTPHEADER => array("Content-type: application/json"),
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $input,
		);

		if ($this->_protocol == 'https')
		{
			if ($this->_cert != null)
			{
				$curlOptions[CURLOPT_CAINFO] = $this->_cert;
				$curlOptions[CURLOPT_CAPATH] = dirname($this->_cert);
				$curlOptions[CURLOPT_SSL_VERIFYPEER] = true;
				$curlOptions[CURLOPT_SSL_VERIFYHOST] = true;
			}
			else
			{
				$curlOptions[CURLOPT_SSL_VERIFYPEER] = false;
				$curlOptions[CURLOPT_SSL_VERIFYHOST] = false;
			}
		}

		curl_setopt_array($curlResource, $curlOptions);

		$this->_rawResponse = curl_exec($curlResource);
		$this->_response = json_decode($this->_rawResponse, true);

		$this->_status = curl_getinfo($curlResource, CURLINFO_HTTP_CODE);

		$curlError = curl_error($curlResource);

		curl_close($curlResource);

		if ($curlError)
		{
			$this->_error = $curlError;

			return false;
		}

		if ($this->_response['error'])
		{
			$this->_error = $this->_response['error']['message'];
			$this->_errorCode = $this->_response['error']['code'];
		}
		else if ($this->_status != 200)
		{
			switch ($this->_status)
			{
				case 400:
					$this->_error = "HTTP_BAD_REQUEST";
					break;
				case 401:
					$this->_error = "HTTP_UNAUTHORIZED";
					break;
				case 403:
					$this->_error = "HTTP_FORBIDDEN";
					break;
				case 404:
					$this->_error = "HTTP_NOT_FOUND";
					break;
				default:
					throw new BitCoinException("An unexpected http response code was encountered!");
			}
		}

		if ($this->_error)
		{
			return false;
		}

		return $this->_response['result'];
	}

	/**
	 * @return string
	 */
	public function getError()
	{
		return $this->_error;
	}

	/**
	 * @return string
	 */
	public function getRawResponse()
	{
		return $this->_rawResponse;
	}

	/**
	 * @return array
	 */
	public function getResponse()
	{
		return $this->_response;
	}

	/**
	 * @return string
	 */
	public function getStatus()
	{
		return $this->_status;
	}

	/**
	 * @see callMethod
	 */
	function __call($name, $arguments)
	{
		return $this->callMethod($name, $arguments);
	}
}

/**
 * General exception for bitcoin-related issues. (Requires message).
 */
class BitCoinException extends Exception
{
	public function __construct($message, $code = 0, Exception $previous = null)
	{
		parent::__construct($message, $code, $previous);
	}
}
