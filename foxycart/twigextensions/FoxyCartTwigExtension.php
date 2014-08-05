<?php

namespace Craft;

use Twig_Extension;

class FoxyCartTwigExtension extends Twig_Extension
{

    public function getName()
    {
        return 'foxycart';
    }

    public function getTokenParsers()
    {
        return array(
            new Hmac_TokenParser()
        );
    }
}