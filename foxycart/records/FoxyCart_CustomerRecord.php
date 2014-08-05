<?php
namespace Craft;

class FoxyCart_CustomerRecord extends BaseRecord
{
    public function getTableName()
    {
        return 'foxycart_customers';
    }

    protected function defineAttributes()
    {
        return array(
            'customerId' => array(AttributeType::Number, 'required' => true),
        );
    }

    public function defineRelations()
    {
        return array(
            'user' => array(static::BELONGS_TO, 'UserRecord', 'required' => true, 'onDelete' => static::CASCADE),
        );
    }
}