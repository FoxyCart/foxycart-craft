<?php
namespace Craft;

class FoxyCartController extends BaseController
{
	protected $apikey;
	protected $storedomain;
	protected $ssoEnabled = false;
	protected $ssoRequireLogin = false;
	protected $allowAnonymous = array('actionWebhook', 'actionSso');

    public function __construct()
	{
		$settings = craft()->plugins->getPlugin('foxycart')->getSettings();

		$this->apikey = $settings->apikey;
		$this->storedomain = $settings->storedomain;
		$this->ssoEnabled = $settings->ssoEnabled;
		$this->ssoRequireLogin = $settings->ssoRequireLogin;
	}

    public function actionWebhook()
    {
    	$this->requirePostRequest();

        craft()->log->removeRoute('WebLogRoute');
        craft()->log->removeRoute('ProfileLogRoute');

    	if (isset($_POST["FoxyData"]) || isset($_POST['FoxySubscriptionData'])) 
    	{
			$encrypted = (isset($_POST["FoxyData"])) ? urldecode($_POST["FoxyData"]) : urldecode($_POST["FoxySubscriptionData"]);
			$decrypted = \rc4crypt::decrypt($this->apikey, $encrypted);
			$xml = new \SimpleXMLElement($decrypted);
			$feedType = (isset($_POST['FoxySubscriptionData'])) ? "subscription" : "transaction";

			if (craft()->foxyCart->processWebhook($xml, $feedType))
			{
				$message = 'foxy';
			}
			else 
			{
				$message = 'Error processing webhook. Please consult your Craft logs';
			}
			
		} 
		else 
		{
			$message = "No FoxyData or FoxySubscriptionData received.";
		}

    	exit($message);
    }

    public function actionSso()
    {
        craft()->log->removeRoute('WebLogRoute');
        craft()->log->removeRoute('ProfileLogRoute');

    	if ($this->ssoEnabled) 
    	{
			$customerId = 0;
			$auth_token = '';
			$redirect_url = '';
			$fcsid = craft()->request->getParam("fcsid", "");
			$timestamp = craft()->request->getParam("timestamp", 0) + (60 * 30); // valid for 30 minutes

			if (!craft()->userSession->isLoggedIn()) 
			{
				// No member
				if ($this->ssoRequireLogin) 
				{
					// No guest checkouts allowed, redirect to the sites login page
					$redirect_url = UrlHelper::getUrl(craft()->config->getLoginPath());;
				}
			} 
			else 
			{
				$user = craft()->userSession->getUser();
				$customerId = craft()->foxyCart->getCustomerId($user);

				if ($user && !$customerId) 
				{
					// Member doesn't have a FoxyCart customer id, see if the member exists on FoxyCart
					$xml = craft()->foxyCart->api("customer_get", array("customer_email" => $user->email) );
					if ($xml !== false) 
					{
						$customerId = (string)$xml->customer_id;
					}

					if (!$customerId || !$xml) 
					{
						// Member doesn't exist, create one for FoxyCart
						$customerId = craft()->foxyCart->updateFoxyCartCustomer($user);
					}

					if (!$customerId) 
					{
						FoxyCartPlugin::log("[sso] User creation failed.", LogLevel::Error);
						// TODO: What should happen here? A user is logged in, but everything failed to get their current customerId? Would that even happen?
					} 
					else 
					{
						// Update the current user's customerId as retrieved from FoxyCart
						craft()->foxyCart->saveCustomerId($user, $customerId);
					}
				}
			}
			$auth_token = sha1($customerId . '|' . $timestamp . '|' . $this->apikey);
			$redirect_url = ($redirect_url != '') ? $redirect_url : 'https://' . $this->storedomain . '/checkout?fc_auth_token=' . $auth_token . '&fc_customer_id=' . $customerId . '&timestamp=' . $timestamp . '&fcsid=' . $fcsid;
			
			craft()->request->redirect($redirect_url);
		}
	}
}