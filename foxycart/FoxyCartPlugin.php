<?php
namespace Craft;

class FoxyCartPlugin extends BasePlugin
{

    function getName()
    {
         return 'FoxyCart';
    }

    function getVersion()
    {
        return '1.0';
    }

    function getDeveloper()
    {
        return 'FoxyCart';
    }

    function getDeveloperUrl()
    {
        return 'http://foxycart.com';
    }

    public function hasCpSection()
    {
        return true;
    }

    public function init()
    {
        parent::init();

        require_once("vendor/class.rc4crypt.php");
        require_once("vendor/foxycart.cart_validation.php");

        $settings = craft()->plugins->getPlugin('foxycart')->getSettings();
        \FoxyCart_Helper::setSecret($settings->apikey);
        \FoxyCart_Helper::setCartUrl("https://" . $settings->storedomain . "/cart");

        if (craft()->request->isCpRequest()) {
            craft()->templates->includeCssFile(UrlHelper::getResourceUrl('foxycart/css/foxycart.css'));
        }

        craft()->on('users.onSaveUser', function(Event $event) {
            $customerId = craft()->foxyCart->updateFoxyCartCustomer($event->params['user']);
            if ($customerId) {
                craft()->foxyCart->saveCustomerId($event->params['user'], $customerId);
            }
        });
    }

    protected function defineSettings()
    {
        return array(
            'storedomain' => array(AttributeType::String, 'required' => true),
            'apikey' => array(AttributeType::String, 'required' => true),
            'ssoEnabled' => array(AttributeType::Bool),
            'ssoRequireLogin' => array(AttributeType::Bool),
            'ssoGroup' => array(AttributeType::Number)
        );
    }

    public function getSettingsHtml()
    {
       return craft()->templates->render('foxycart/_settings', array(
           'settings' => $this->getSettings()
       ));
    }

    public function registerCpRoutes()
    {
        return array(
            'foxycart\/customers\/'                    => 'foxyCart/customers',
            'foxycart\/customer\/(?P<customerId>\d+)'  => 'foxyCart/customer'
        );
    }

    function registerUserPermissions()
    {
        return array(
            'viewStoreInformation' => array('label' => Craft::t('View store information'))
        );
    }

    public function addTwigExtension()
    {
        Craft::import('plugins.foxycart.twigextensions.Hmac_Node');
        Craft::import('plugins.foxycart.twigextensions.Hmac_TokenParser');
        Craft::import('plugins.foxycart.twigextensions.FoxyCartTwigExtension');

        return new FoxyCartTwigExtension();
    }
}