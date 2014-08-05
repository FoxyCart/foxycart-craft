# FoxyCart helper plugin for Craft CMS

This plugin adds in a number of FoxyCart features to Craft to make it easier for you to add a FoxyCart powered checkout to your site.

## Features

 - Webhooks
 - SSO
 - HMAC add to cart encryption
 - FoxyCart category field
 - Basic transaction reports

## Requirements

 - Craft 2.1+
 - Craft Pro (For SSO)
 - FoxyCart 1.1+

## Installation

1. Copy the folder into your Craft CMS plugins folder
2. Edit the plugin settings to set your FoxyCart store domain and API key

## Usage

For an overview of integrating FoxyCart into Craft utilising this plugin, see [Craft CMS integration notes](https://wiki.foxycart.com/integration/craftcms) on the FoxyCart wiki

### HMAC

To enable HMAC encryption, simply wrap any links or forms (or your whole page if desired) in `{% hmac %}` and `{% endhmac %}` tags. Note that the add to cart requires a code. For more information see [this page](http://wiki.foxycart.com/static/redirect/price_validation "FoxyCart wiki on HMAC encryption")

### Events

`foxyCart.onProcessWebhook`

Raised whenever a webhook (transaction or subscription) is processed.

**Params:**

 - `xml` – A decrypted _SimpleXMLElement_ instance of the received webhook payload.
 - `feedType` - A string describing the type of webhook received ("transaction" or "subscription")

### Variables

 - `craft.foxyCart.storedomain` - The store subdomain as set in your plugin settings.
 
### Fields

 - FoxyCart Category - Displays a dropdown of the categories as set within the FoxyCart store

### Reports

This plugin includes some very basic reports accessible from the "FoxyCart" CP section. This is more of a proof of concept currently than a full-fledged reports area. Currently when Craft is in devMode, test transactions are displayed, otherwise live transactions are displayed.

## Debugging

Some helpful debug messages are logged to a plugin specific log file of `foxycart.log`.

## Got an improvement or found a bug?

We're accepting pull requests! All we request is that any changes you make remain accessible to all users of the plugin.

## Changelog

**v1.0**   

- Initial commit
