<?php
namespace Craft;

class FoxyCartService extends BaseApplicationComponent
{
	private static $curl_connecttimeout = 5;
	private static $curl_timeout = 15;
	private static $curl_ssl_verifypeer = false;

	protected $apikey;
	protected $storedomain;
	protected $ssoEnabled;
	protected $ssoGroup;
	protected $activeWebhook = false;

    public function __construct()
	{
		$settings = craft()->plugins->getPlugin('foxycart')->getSettings();

		$this->apikey = $settings->apikey;
		$this->storedomain = $settings->storedomain;
		$this->ssoEnabled = $settings->ssoEnabled;
		$this->ssoGroup = $settings->ssoGroup;
	}

	public function processWebhook($xml, $feedType)
	{
		$this->activeWebhook = true;

		if ($feedType == "transaction" && $this->ssoEnabled) 
		{
			$this->syncCustomer($xml);
		}

		foreach($xml->transactions->transaction as $transaction) 
		{
			$customer_first_name = (string)$transaction->customer_first_name;
			$customer_last_name = (string)$transaction->customer_last_name;

			FoxyCartPlugin::log("[webhook] Order received from " . $customer_first_name . " " . $customer_last_name);
		}

		$this->onProcessWebhook(new Event($this, array(
			'xml' => $xml,
			'type' => $feedType
		)));

		$this->activeWebhook = false;

		return true;
	}

	public function syncCustomer($xml) 
    {
    	craft()->requireEdition(Craft::Pro);

    	if (!$this->ssoEnabled) return false;

    	if (!craft()->systemSettings->getSetting('users', 'allowPublicRegistration'))
		{
			FoxyCartPlugin::log('[sso] Unable to save user as allowPublicRegistration is set to false', LogLevel::Error);
			return false;
		}


		foreach($xml->transactions->transaction as $transaction) 
		{
			$customerId = (string)$transaction->customer_id;
			$customerFirstName = (string)$transaction->customer_first_name;
			$customerLastName = (string)$transaction->customer_last_name;
			$customerEmail = (string)$transaction->customer_email;
			$customerPassword = (string)$transaction->customer_password;
			$customerPasswordHashType = (string)$transaction->customer_password_hash_type;
			$customerPasswordHashConfig = (string)$transaction->customer_password_hash_config;

			// Make sure they have the right password algorithm
			if ($customerPasswordHashType !== "craftcms") 
			{
				FoxyCartPlugin::log(sprintf('[sso] Unable to save user as FoxyCart password hashing method is set to "%s", needs to be "Craft CMS"', $customerPasswordHashType), LogLevel::Error);
				return false;
			}
			if ($customerPasswordHashConfig != craft()->config->get('blowfishHashCost')) 
			{
				FoxyCartPlugin::log(sprintf('[sso] Unable to save user due to password encryption algorithm config mismatch. FoxyCart: "%s" - Craft: "%s"', $customerPasswordHashConfig, craft()->config->get('blowfishHashCost')), LogLevel::Error);
				return false;
			}

			// Ignore them if they checked out as a guest
			if ($customerId != '0') 
			{
				$isNewUser = false;
				$user = craft()->users->getUserByUsernameOrEmail($customerEmail);
				
				if (!$user) 
				{
					$user = new UserModel();
					$isNewUser = true;
				}

				$user->firstName = $customerFirstName;
				$user->lastName = $customerLastName;

				if ($isNewUser)
				{	
					$user->email = $customerEmail;
					$user->username = $customerEmail;
					$user->status = UserStatus::Active;
				}

				// ToDo: Do we need to raise an onSyncUser event here, or will users.onSaveUser cover it?

				if ($user->validate(null, false) && craft()->users->saveUser($user))
				{
					// Save their password directly, as there isn't an API to save an already hashed password in Craft
					$sql = craft()->db->createCommand();
					$sql->update('users', array(
					    'password' => $customerPassword,
					), 'id=:id', array(':id' => $user->id));

					// Save their FoxyCart customer ID
					$this->saveCustomerId($user, $customerId);

					// Assign them to a group					
					if ($this->ssoGroup)
					{
						craft()->userGroups->assignUserToGroups($user->id, array($this->ssoGroup));
					}

					return true;
				} 
				else 
				{
					FoxyCartPlugin::log('[sso] Couldn’t save user from webhook', LogLevel::Error);
					return false;
				}
			}
		}
	}

	public function getCustomerRecord(UserModel $user) 
	{
		$customerRecord = FoxyCart_CustomerRecord::model()->findByAttributes(array(
			'userId' => $user->id,
		));

		if (empty($customerRecord)) 
		{
			$customerRecord = new FoxyCart_CustomerRecord;
		}

		return $customerRecord;
	}

	public function getCustomerId(UserModel $user)
	{
		$customerRecord = $this->getCustomerRecord($user);
		return $customerRecord->customerId;
	}

	public function saveCustomerId(UserModel $user, $customerId = null)
	{
		$customerRecord = $this->getCustomerRecord($user);

		$attr = array(
			'userId' => $user->id,
			'customerId' => $customerId,
		);
		$customerRecord->setAttributes($attr, false);
		$customerRecord->save();
	}

	public function updateFoxyCartCustomer(UserModel $user)
	{
    	craft()->requireEdition(Craft::Pro);

		if (!$this->activeWebhook && $this->ssoEnabled && $user->password != "") 
		{
			$foxy_data = array(
				"customer_email" => $user->email, 
				"customer_password_hash" => $user->password
			);
			if ($user->firstName != '') 
			{
				$foxy_data = array_merge($foxy_data, array("customer_first_name" => $user->firstName));
			}
			if ($user->lastName != '') 
			{
				$foxy_data = array_merge($foxy_data, array("customer_last_name" => $user->lastName));
			}
			if ($customerId = $this->getCustomerId($user)) {
				$foxy_data = array_merge($foxy_data, array("customer_id" => $customerId));
			}

			$xml = craft()->foxyCart->api("customer_save", $foxy_data);
			
			if ($xml !== false) 
			{
				return (string)$xml->customer_id;
			} 
			else 
			{
				FoxyCartPlugin::log("[sso] API call to 'customer_save' failed.", LogLevel::Error);
				return false;
			}
		} else {
			return false;
		}
	}

	public function listTransactions($params = array())
	{
		$isTest = (craft()->config->get('devMode')) ? 1 : 0;

		$xml = $this->api("transaction_list", array_merge(array("is_test_filter" => $isTest), $params));

		if (!$xml)
		{
			return false;
		}
		else
		{
			$transactions = array();
			foreach($xml->transactions->transaction as $transaction) 
			{
				array_push($transactions, json_decode(json_encode($transaction), true));
			}
			return $transactions;
		}
	}

	public function getTransaction($transactionId) 
	{
		$xml = $this->api("transaction_get", array("transaction_id" => $transactionId));

		if (!$xml)
		{
			return false;
		}
		else
		{
			return json_decode(json_encode($xml->transaction), true);
		}
	}

	public function listCustomers($params = array())
	{
		$xml = $this->api("customer_list", $params);

		if (!$xml)
		{
			return false;
		}
		else
		{
			$customers = array();
			foreach($xml->customers->customer as $customer) {
				array_push($customers, json_decode(json_encode($customer), true));
			}
			return $customers;
		}
	}

	public function getCustomer($customerId) 
	{
		$xml = $this->api("customer_get", array("customer_id" => $customerId));

		if (!$xml)
		{
			return false;
		}
		else
		{
			return json_decode(json_encode($xml), true);
		}
	}


	public function api($method, $params = array()) 
    {
    	// Decide if the call can be cached
    	$cached = false;
		$cacheableMethods = array(
			'store_includes_get', 
			'attribute_list', 
			'category_list', 
			'downloadable_list',
			'customer_list',
			'customer_get', 
			'customer_address_get', 
			'transaction_list', 
			'transaction_get', 
			'subscription_get', 
			'subscription_list'
		);

		if (in_array($method, $cacheableMethods)) 
		{
			$cached = true;
			$cacheKey = "foxycart_" . $method;
			if (count($params) > 0) 
			{
				$cacheKey .= "_" . hash('sha256', http_build_query($params));
			}

    		$cachedResponse = craft()->cache->get($cacheKey);

    		if ($cachedResponse) 
    		{
				FoxyCartPlugin::log("[api] Returning cached data for " . $method . "?" . http_build_query($params), LogLevel::Info);
    			return simplexml_load_string($cachedResponse, NULL, LIBXML_NOCDATA);;
    		}
		}

    	try {
			$client  = new \Guzzle\Http\Client("https://" . $this->storedomain);
			$foxy_data = array_merge(array("api_token" => $this->apikey, "api_action" => $method), $params);
			$request = $client->post("/api", array(), array(
				'verify'  => self::$curl_ssl_verifypeer,
				'timeout' => self::$curl_timeout,
				'connect_timeout' => self::$curl_connecttimeout
			));
			$request = $request->addPostFields($foxy_data);
			$response = $request->send();

			if (!$response->isSuccessful()) 
			{
				return false;
			}

			$xml = simplexml_load_string($response->getBody(true), NULL, LIBXML_NOCDATA);

			if ($xml->result == "ERROR") 
			{
				$errorMessages = array();
				foreach($xml->messages->message as $message) 
				{
					array_push($errorMessages, $message);
				}
				FoxyCartPlugin::log('[api] An API request returned an error: ' . join(", ", $errorMessages), LogLevel::Error);

				return false;
			}

			if ($cached) 
			{
				// Cache this call for 10 minutes
				craft()->cache->set($cacheKey, $response->getBody(true), 600);
			}

			return $xml;

		} 
		catch(\Exception $e) 
		{
			FoxyCartPlugin::log('[api] An API request failed: ' . $e->getMessage(), LogLevel::Error);

			return false;
		}
	}

	/* Events

	/**
	 * Fires an 'onProcessWebhook' event.
	 *
	 * @param Event $event
	 */
	public function onProcessWebhook(Event $event)
	{
		$this->raiseEvent('onProcessWebhook', $event);
	}
}