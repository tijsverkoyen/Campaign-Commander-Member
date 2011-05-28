<?php

/**
 * Campaign Commander Member class
 *
 * This source file can be used to communicate with Campaign Commander (http://campaigncommander.com)
 *
 * The class is documented in the file itself. If you find any bugs help me out and report them. Reporting can be done by sending an email to php-campaign-commander-member-bugs[at]verkoyen[dot]eu.
 * If you report a bug, make sure you give me enough information (include your code).
 *
 * Changelog since 1.0.2
 * - Added method to set the server.
 * - Renamed methods to reflect to current API
 *
 * Changelog since 1.0.1
 * - Typemapping for really long longs.
 * - No more casting to integers (because of the really long longs).
 *
 * Changelog since 1.0.0
 * - debug is off by default.
 * - wrapped the close-call in a try-catch block in the destructor.
 *
 * License
 * Copyright (c), Tijs Verkoyen. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products derived from this software without specific prior written permission.
 *
 * This software is provided by the author "as is" and any express or implied warranties, including, but not limited to, the implied warranties of merchantability and fitness for a particular purpose are disclaimed. In no event shall the author be liable for any direct, indirect, incidental, special, exemplary, or consequential damages (including, but not limited to, procurement of substitute goods or services; loss of use, data, or profits; or business interruption) however caused and on any theory of liability, whether in contract, strict liability, or tort (including negligence or otherwise) arising in any way out of the use of this software, even if advised of the possibility of such damage.
 *
 * @author			Tijs Verkoyen <php-campaign-commander-member@verkoyen.eu>
 * @version			1.1.0
 *
 * @copyright		Copyright (c), Tijs Verkoyen. All rights reserved.
 * @license			BSD License
 */
class CampaignCommanderMember
{
	// internal constant to enable/disable debugging
	const DEBUG = false;

	// url for the api
	const WSDL_URL = 'apimember/services/MemberService?wsdl';

	// current version
	const VERSION = '1.1.0';


	/**
	 * The API-key that will be used for authenticating
	 *
	 * @var	string
	 */
	private $key;


	/**
	 * The login that will be used for authenticating
	 *
	 * @var	string
	 */
	private $login;


	/**
	 * The password that will be used for authenticating
	 *
	 * @var	string
	 */
	private $password;


	/**
	 * The server to use
	 *
	 * @var	string
	 */
	private $server = 'http://emvapi.emv3.com';


	/**
	 * The SOAP-client
	 *
	 * @var	SoapClient
	 */
	private $soapClient;


	/**
	 * The token
	 *
	 * @var	string
	 */
	private $token = null;


	/**
	 * The timeout
	 *
	 * @var	int
	 */
	private $timeOut = 60;


	/**
	 * The user agent
	 *
	 * @var	string
	 */
	private $userAgent;


// class methods
	/**
	 * Default constructor
	 *
	 * @return	void
	 * @param	string $login				Login provided for API access.
	 * @param	string $password			The password.
	 * @param	string $key					Manager Key copied from the CCMD web application.
	 * @param	string[optional] $server	The server to use. Ask your account-manager.
	 */
	public function __construct($login, $password, $key, $server = null)
	{
		$this->setLogin($login);
		$this->setPassword($password);
		$this->setKey($key);
		if($server !== null) $this->setServer($server);
	}


	/**
	 * Destructor
	 *
	 * @return	void
	 */
	public function __destruct()
	{
		// is the connection open?
		if($this->soapClient !== null)
		{
			try
			{
				// close
				$this->closeApiConnection();
			}

			// catch exceptions
			catch(Exception $e)
			{
				// do nothing
			}

			// reset vars
			$this->soapClient = null;
			$this->token = null;
		}
	}


	/**
	 * Make the call
	 *
	 * @return	mixed
	 * @param	string $method					The method to be called.
	 * @param	array[optional] $parameters		The parameters.
	 */
	private function doCall($method, array $parameters = array())
	{
		// open connection if needed
		if($this->soapClient === null || $this->token === null)
		{
			// build options
			$options = array('soap_version' => SOAP_1_1,
							 'trace' => self::DEBUG,
							 'exceptions' => true,
							 'connection_timeout' => $this->getTimeOut(),
							 'user_agent' => $this->getUserAgent(),
							 'typemap' => array(
												array('type_ns' => 'http://www.w3.org/2001/XMLSchema', 'type_name' => 'long', 'to_xml' => array(__CLASS__, 'toLongXML'), 'from_xml' => array(__CLASS__, 'fromLongXML'))	// map long to string, because a long can cause an integer overflow
											)
						);

			// create client
			$this->soapClient = new SoapClient($this->getServer() . '/' . self::WSDL_URL, $options);

			// build login parameters
			$loginParameters['login'] = $this->getLogin();
			$loginParameters['pwd'] = $this->getPassword();
			$loginParameters['key'] = $this->getKey();

			// make the call
			$response = $this->soapClient->__soapCall('openApiConnection', array($loginParameters));

			// validate
			if(is_soap_fault($response))
			{
				// init var
				$message = 'Internal Error';

				// more detailed message available
				if(isset($response->detail->ConnectionServiceException->description)) $message = (string) $response->detail->ConnectionServiceException->description;

				// invalid token?
				if($message == 'Please enter a valid token to validate your connection.')
				{
					// reset token
					$this->token = null;

					// try again
					return self::doCall($method, $parameters);
				}

				// internal debugging enabled
				if(self::DEBUG)
				{
					echo '<pre>';
					echo 'last request<br />';
					var_dump($this->soapClient->__getLastRequest());
					echo 'response<br />';
					var_dump($response);
					echo '</pre>';
				}

				// throw exception
				throw new CampaignCommanderMemberException($message);
			}

			// validate response
			if(!isset($response->return)) throw new CampaignCommanderMemberException('Invalid response');

			// set token
			$this->token = (string) $response->return;
		}

		// redefine
		$method = (string) $method;
		$parameters = (array) $parameters;

		// loop parameters
		foreach($parameters as $key => $value)
		{
			// strings should be UTF8
			if(gettype($value) == 'string') $parameters[$key] = utf8_encode($value);
		}

		// add token
		$parameters['token'] = $this->token;

		try
		{
			// make the call
			$response = $this->soapClient->__soapCall($method, array($parameters));
		}

		catch(Exception $e)
		{
			// internal debugging enabled
			if(self::DEBUG)
			{
				echo '<pre>';
				echo 'last request<br />';
				var_dump($this->soapClient->__getLastRequest());
				echo 'response<br />';
				var_dump($response);
				echo '</pre>';
			}

			// throw exception
			throw new CampaignCommanderMemberException($e->getMessage());
		}

		// validate response
		if(is_soap_fault($response))
		{
			// init var
			$message = 'Internal Error';

			// more detailed message available
			if(isset($response->detail->ConnectionServiceException->description)) $message = (string) $response->detail->ConnectionServiceException->description;
			if(isset($response->detail->MemberServiceException->description)) $message = (string) $response->detail->MemberServiceException->description;
			if(isset($response->detail->CcmdServiceException->description))
			{
				$message = (string) $response->detail->CcmdServiceException->description;
				if(isset($response->detail->CcmdServiceException->fields)) $message .= ' fields: ' . $response->detail->CcmdServiceException->fields;
				if(isset($response->detail->CcmdServiceException->status)) $message .= ' status: ' . $response->detail->CcmdServiceException->status;
			}

			// internal debugging enabled
			if(self::DEBUG)
			{
				echo '<pre>';
				var_dump(htmlentities($this->soapClient->__getLastRequest()));
				var_dump($this);
				echo '</pre>';
			}

			// throw exception
			throw new CampaignCommanderMemberException($message);
		}

		// empty reply
		if(!isset($response->return)) return null;

		// return the response
		return $response->return;
	}


	/**
	 * Convert a long into a string
	 *
	 * @return	string
	 * @param	string $value	The value to convert.
	 */
	public static function fromLongXML($value)
	{
		return (string) strip_tags($value);
	}


	/**
	 * Convert a x into a long
	 *
	 * @return	string
	 * @param	string $value	The value to convert.
	 */
	public static function toLongXML($value)
	{
		return '<long>' . $value . '</long>';
	}


	/**
	 * Get the key
	 *
	 * @return	string
	 */
	private function getKey()
	{
		return (string) $this->key;
	}


	/**
	 * Get the login
	 *
	 * @return	string
	 */
	private function getLogin()
	{
		return (string) $this->login;
	}


	/**
	 * Get the password
	 *
	 * @return	string
	 */
	private function getPassword()
	{
		return $this->password;
	}


	/**
	 * Get the server
	 *
	 * @return	string
	 */
	private function getServer()
	{
		return $this->server;
	}


	/**
	 * Get the timeout that will be used
	 *
	 * @return	int
	 */
	public function getTimeOut()
	{
		return (int) $this->timeOut;
	}


	/**
	 * Get the useragent that will be used. Our version will be prepended to yours.
	 * It will look like: "PHP Campaign Commander Member/<version> <your-user-agent>"
	 *
	 * @return	string
	 */
	public function getUserAgent()
	{
		return (string) 'PHP Campaign Commander Member/' . self::VERSION . ' ' . $this->userAgent;
	}


	/**
	 * Set the Key that has to be used
	 *
	 * @return	void
	 * @param	string $key		The key to set.
	 */
	private function setKey($key)
	{
		$this->key = (string) $key;
	}


	/**
	 * Set the login that has to be used
	 *
	 * @return	void
	 * @param	string $login	The login to use.
	 */
	private function setLogin($login)
	{
		$this->login = (string) $login;
	}


	/**
	 * Set the password that has to be used
	 *
	 * @return	void
	 * @param	string $password	The password to use.
	 */
	private function setPassword($password)
	{
		$this->password = (string) $password;
	}


	/**
	 * Set the server that has to be used.
	 *
	 * @return	void
	 * @param	string $server	The server to use.
	 */
	private function setServer($server)
	{
		$this->server = (string) $server;
	}


	/**
	 * Set the timeout
	 * After this time the request will stop. You should handle any errors triggered by this.
	 *
	 * @return	void
	 * @param	int $seconds	The timeout in seconds.
	 */
	public function setTimeOut($seconds)
	{
		$this->timeOut = (int) $seconds;
	}


	/**
	 * Set the user-agent for you application
	 * It will be appended to ours, the result will look like: "PHP Campaign Commander Member/<version> <your-user-agent>"
	 *
	 * @return	void
	 * @param	string $userAgent	Your user-agent, it should look like <app-name>/<app-version>.
	 */
	public function setUserAgent($userAgent)
	{
		$this->userAgent = (string) $userAgent;
	}


// connection methods
	/**
	 * Close the connection
	 *
	 * @return	bool	true if the connection was closes, otherwise false.
	 */
	public function closeApiConnection()
	{
		// make the call
		$response = $this->doCall('closeApiConnection');

		// validate response
		if($response == 'connection closed')
		{
			// reset vars
			$this->soapClient = null;
			$this->token = null;

			return true;
		}

		// fallback
		return false;
	}


// member methods
	/**
	 * Retrieves the list of fields (i.e. database column names) available in the Member table.
	 *
	 * @return	array	An array containing all database column names.
	 */
	public function descMemberTable()
	{
		// make the call
		$response = $this->doCall('descMemberTable');

		// validate response
		if(!isset($response->fields)) throw new CampaignCommanderMemberException('Invalid response');

		// init var
		$fields = array();

		// loop fields
		foreach($response->fields as $row) $fields[] = array('name' => $row->name, 'type' => strtolower($row->type));

		// return
		return $fields;
	}


	/**
	 * Get a member by email-address
	 *
	 * @return	array			An array with all fields as a key-value-pair for the member.
	 * @param	string $email	The email address of the member to retrieve.
	 */
	public function getMemberByEmail($email)
	{
		// build parameters
		$parameters = array();
		$parameters['email'] = (string) $email;

		// make the call
		$response = $this->doCall('getMemberByEmail', $parameters);

		// sometimes this will return a hash, so grab the first one
		if(is_array($response)) $response = $response[0];

		// validate response
		if(!isset($response->attributes->entry)) throw new CampaignCommanderMemberException('Invalid response');

		// init var
		$return = array();

		// loop fields
		foreach($response->attributes->entry as $row)
		{
			// create vars
			$key = (string) $row->key;
			$value = (isset($row->value)) ? $row->value : null;

			// convert some stuff
			if($key == 'DATEJOIN' && $value !== null) $value = (int) strtotime($value);

			// add
			$return[$key] = $value;
		}

		// return
		return $return;
	}


	/**
	 * Uses the member ID to retrieve the details of a member.
	 *
	 * @return	array		An array with all fields as a key-value-pair for the member.
	 * @param	string $id	The ID of the member whose details you want to retrieve..
	 */
	public function getMemberById($id)
	{
		// build parameters
		$parameters = array();
		$parameters['id'] = (string) $id;

		// make the call
		$response = $this->doCall('getMemberById', $parameters);

		// validate response
		if(!isset($response->attributes->entry)) throw new CampaignCommanderMemberException('Invalid response');

		// init var
		$return = array();

		// loop fields
		foreach($response->attributes->entry as $row)
		{
			// create vars
			$key = (string) $row->key;
			$value = (isset($row->value)) ? $row->value : null;

			// convert some stuff
			if($key == 'DATEJOIN' && $value !== null) $value = (int) strtotime($value);

			// add
			$return[$key] = $value;
		}

		// return
		return $return;
	}


	/**
	 * Retrieves a list of a maximum of 50 members who match the given criteria.
	 *
	 * @return	array			An array containing the list of members who match the criteria.
	 * @param	array $member	The member object containing the criteria.
	 */
	public function getListMembersByObj(array $member)
	{
		// build parameters
		$parameters = array();
		$parameters['member'] = $member;

		// make the call
		$response = $this->doCall('getListMembersByObj', $parameters);

		// no results
		if($response === null) return array();

		// validate response
		if(!is_array($response)) throw new CampaignCommanderMemberException('Invalid response');

		// init var
		$return = array();

		// loop fields
		foreach($response as $row)
		{
			// validate
			if(!isset($row->attributes->entry)) continue;

			// init var
			$member = array();

			// loop fields
			foreach($row->attributes->entry as $item)
			{
				// create vars
				$key = (string) $item->key;
				$value = (isset($item->value)) ? $item->value : null;

				// convert some stuff
				if($key == 'DATEJOIN' && $value !== null) $value = (int) strtotime($value);

				// add
				$member[$key] = $value;
			}

			// add
			$return[] = $member;
		}

		// return
		return $return;
	}


	/**
	 * Retrieves all members page by page. Each page contains 10 members.
	 *
	 * @return	array		An array containing the list of members.
	 * @param	int $page	The page number to retrieve.
	 */
	public function getListMembersByPage($page)
	{
		// build parameters
		$parameters = array();
		$parameters['page'] = (int) $page;

		// make the call
		$response = $this->doCall('getListMembersByPage', $parameters);

		// no results
		if($response === null) return array();

		// validate response
		if(!isset($response->list)) throw new CampaignCommanderMemberException('Invalid response');

		// init var
		$return = array();

		// loop fields
		foreach($response->list as $row)
		{
			// validate
			if(!isset($row->attributes->entry)) continue;

			// init var
			$member = array();

			// loop fields
			foreach($row->attributes->entry as $item)
			{
				// create vars
				$key = (string) $item->key;
				$value = (isset($item->value)) ? $item->value : null;

				// convert some stuff
				if($key == 'DATEJOIN' && $value !== null) $value = (int) strtotime($value);

				// add
				$member[$key] = $value;
			}

			// add
			$return[] = $member;
		}

		// return
		return $return;
	}


	/**
	 * Insert a new member, all member-fields will be empty
	 *
	 * @return	string			The job ID of the insertion, see getJobStatus().
	 * @param	string $email	The email addres of the new member.
	 */
	public function insertMember($email)
	{
		// build parameters
		$parameters = array();
		$parameters['email'] = (string) $email;

		// make the call
		$response = (int) $this->doCall('insertMember', $parameters);

		// validate
		if($response == 0) throw new CampaignCommanderMemberException('Invalid response');

		// return the job ID
		return (string) $response;
	}


	/**
	 * Updates a given field for a certain user
	 *
	 * @return	string			The job ID of the update, see getJobStatus().
	 * @param	string $email	The email address of the member.
	 * @param	string $field	The field to update.
	 * @param	mixed $value	The value with which to update the field.
	 */
	public function updateMember($email, $field, $value)
	{
		// build parameters
		$parameters = array();
		$parameters['email'] = (string) $email;
		$parameters['field'] = (string) $field;
		$parameters['value'] = $value;

		// make the call
		$response = $this->doCall('updateMember', $parameters);

		// validate
		if($response == 0) throw new CampaignCommanderMemberException('Invalid response');

		// return the job ID
		return (string) $response;
	}


	/**
	 * Insert a new member of updates an existing member
	 *
	 * @return	string						The job ID of the update/insertion, see getJobStatus().
	 * @param	array $fields				The fields, as a key-value-pair, that will be updates/inserted.
	 * @param	string[optional] $email		The email of the member to update/insert.
	 * @param	string[optional] $id		The id of the member to update/insert.
	 */
	public function insertOrUpdateMemberByObj($fields, $email = null, $id = null)
	{
		// validate
		if($email === null && $id == null) throw new CampaignCommanderMemberException('Email or id has to be specified');

		// build parameters
		$parameters = array();
		$parameters['member'] = array();

		// add fields
		foreach($fields as $key => $value)
		{
			$parameters['member']['dynContent']['entry'][] = array('key' => $key, 'value' => $value);
		}

		// memberUID
		if($email !== null) $parameters['member']['email'] = (string) $email;
		if($id !== null) $parameters['member']['memberUID'] = (string) $id;

		// make the call
		$response = $this->doCall('insertOrUpdateMemberByObj', $parameters);

		// validate
		if($response == 0) throw new CampaignCommanderMemberException('Invalid response');

		// return the job ID
		return (string) $response;
	}


	/**
	 * Update a member by building a member-object
	 *
	 * @return	string						The job ID of the update, see getJobStatus().
	 * @param	array $fields				The fields, as a key-value-pair, that will be set.
	 * @param	string[optional] $email		The email of the member to update.
	 * @param	string[optional] $id		The id of the member to update.
	 */
	public function updateMemberByObj($fields, $email = null, $id = null)
	{
		// validate
		if($email === null && $id == null) throw new CampaignCommanderMemberException('Email or id has to be specified');

		// build parameters
		$parameters = array();
		$parameters['member'] = array();

		// add fields
		foreach($fields as $key => $value)
		{
			$parameters['member']['dynContent']['entry'][] = array('key' => $key, 'value' => $value);
		}

		// memberUID
		if($email !== null) $parameters['member']['email'] = (string) $email;
		if($id !== null) $parameters['member']['memberUID'] = (string) $id;

		// make the call
		$response = $this->doCall('updateMemberByObj', $parameters);

		// validate
		if($response == 0) throw new CampaignCommanderMemberException('Invalid response');

		// return the job ID
		return (string) $response;
	}


	/**
	 * Retrieves the job status (i.e. the status of the member insertion or update) using the job ID.
	 * Possible return-values are:
	 *  - Insert: The jobs is busy (I think)
	 *  - Processing: The job is busy
	 *  - Processed: The job was processed and is done
	 *  - Error: Something went wrong, there is no way to see what went wrong.
	 *  - Job_Done_Or_Does_Not_Exist: the job is done or doesn't exists (anymore).
	 *
	 * @return	string		The status of the job.
	 * @param	string $id	The job ID.
	 */
	public function getMemberJobStatus($id)
	{
		// possible responses
		$possibleResponses = array('Insert', 'Processing', 'Processed', 'Error', 'Job_Done_Or_Does_Not_Exist');

		// build parameters
		$parameters = array();
		$parameters['synchroId'] = (string) $id;

		// make the call
		$response = $this->doCall('getMemberJobStatus', $parameters);

		// validate respone
		if(!isset($response->status)) throw new CampaignCommanderMemberException('Invalid response');
		if(!in_array($response->status, $possibleResponses)) throw new CampaignCommanderMemberException('Invalid response');

		// return status
		return (string) $response->status;
	}


	/**
	 * Unsubscribes one or more members who match a given email address.
	 *
	 * @return	string			The job ID of the unjoin, see getJobStatus().
	 * @param	string $email	The email address.
	 */
	public function unjoinMemberByEmail($email)
	{
		// build parameters
		$parameters = array();
		$parameters['email'] = (string) $email;

		// make the call
		$response = $this->doCall('unjoinMemberByEmail', $parameters);

		// validate
		if($response == 0) throw new CampaignCommanderMemberException('Invalid response');

		// return the job ID
		return (string) $response;
	}


	/**
	 * Unsubscribes a member who matches a given ID.
	 *
	 * @return	string		The job ID of the unjoin, see getJobStatus().
	 * @param	int $id		The ID of the member.
	 */
	public function unjoinMemberById($id)
	{
		// build parameters
		$parameters = array();
		$parameters['memberId'] = (string) $id;

		// make the call
		$response = $this->doCall('unjoinMemberById', $parameters);

		// validate
		if($response == 0) throw new CampaignCommanderMemberException('Invalid response');

		// return the job ID
		return (string) $response;
	}


	/**
	 * Unsubscribes a member by object.
	 *
	 * @return	string			The job ID of the unjoin, see getJobStatus().
	 * @param	array $member	The member.
	 */
	public function unjoinMemberByObj(array $member)
	{
		// build parameters
		$parameters = array();
		$parameters['member'] = $member;

		// make the call
		$response = $this->doCall('unjoinMemberByObj', $parameters);

		// validate
		if($response == 0) throw new CampaignCommanderMemberException('Invalid response');

		// return the job ID
		return (string) $response;
	}


	/**
	 * Re-subscribes an unsubscribed member using his/her email address. If there are multiple members with the same email address, they will all be re-subscribed.
	 * REMARK: The number of rejoins per day is limited to avoid massive rejoins and illegal usage of this method.
	 *
	 * @return	string			The job ID of the rejoin, see getJobStatus().
	 * @param	string $email	The email address of the member.
	 */
	public function rejoinMemberByEmail($email)
	{
		// build parameters
		$parameters = array();
		$parameters['email'] = (string) $email;

		// make the call
		$response = $this->doCall('rejoinMemberByEmail', $parameters);

		// validate
		if($response == 0) throw new CampaignCommanderMemberException('Invalid response');

		// return the job ID
		return (string) $response;
	}


	/**
	 * Re-subscribes an unsibscribed member using his/her ID.
	 * REMARK: The number of rejoins per day is limited to avoid massive rejoins and illegal usage of this method.
	 *
	 * @return	string			The job ID of the rejoin, see getJobStatus().
	 * @param	string $id	The ID of the member.
	 */
	public function rejoinMemberById($id)
	{
		// build parameters
		$parameters = array();
		$parameters['memberId'] = (string) $id;

		// make the call
		$response = $this->doCall('rejoinMemberById', $parameters);

		// validate
		if($response == 0) throw new CampaignCommanderMemberException('Invalid response');

		// return the job ID
		return (string) $response;
	}
}


/**
 * Campaign Commander Exception class
 *
 * @author	Tijs Verkoyen <php-campaign-commander-member@verkoyen.eu>
 */
class CampaignCommanderMemberException extends Exception
{
}

?>