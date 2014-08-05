<?php
namespace Craft;

class FoxyCart_CategoryFieldType extends BaseFieldType
{
    public function getName()
    {
        return Craft::t('FoxyCart Category');
    }

    public function getInputHtml($name, $value)
    {

        $response = craft()->foxyCart->api("category_list");

        $options = array();
        if ($response !== false && $response->result == "SUCCESS") {
            foreach($response->categories->category as $category) {
                $options[] = array(
                    "label" => (string)$category->description,
                    "value" => (string)$category->code 
                );
            }
        }


        return craft()->templates->render('foxycart/_foxycart_categories', array(
            'name'  => $name,
            'value' => $value,
            'options' => $options
        ));
    }
}