<?php
namespace Craft;

class FoxyCartVariable
{
    public function storedomain()
    {
        return craft()->plugins->getPlugin('foxycart')->getSettings()->storedomain;
    }

    public function isSetup()
    {
        $settings = craft()->plugins->getPlugin('foxycart')->getSettings();
        return (!empty($settings->storedomain) && !empty($settings->apikey));
    }

    public function listTransactions()
    {
        return craft()->foxyCart->listTransactions();
    }

    public function getTransaction($id)
    {
        return craft()->foxyCart->getTransaction($id);
    }

    public function listCustomers()
    {
        return craft()->foxyCart->listCustomers();
    }

    public function getCustomer($id)
    {
        return craft()->foxyCart->getCustomer($id);
    }

    public function getCustomerTransactions($id)
    {
        return craft()->foxyCart->listTransactions(array("customer_id_filter" => $id));
    }
}