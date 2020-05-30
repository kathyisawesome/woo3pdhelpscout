# Woo3pd Helpscout

Parse WooCommerce.com support emails into HelpScout conversations

NB: Currently this is not handling webhooks... while it's in progress it's parsing data from a [JSON file](https://github.com/kathyisawesome/woo3pd-helpscout/blob/master/includes/webhook-payload.json).

## Create a webhook App in Helpscout

1. Install the "Webhooks" app at https://secure.helpscout.net/apps/
2. This plugin only handles the "Conversation Created" webhook, so that's the only one you need to check.
3. Copy the secret key 
4. Set the callback URL to `http://yourwebsite.com/?woo3pd-api=helpscout`

![install webhooks app](https://user-images.githubusercontent.com/507025/82216488-423def80-98d6-11ea-83ce-5adcfe53b5c5.png)

5. Go to your profile>My Apps and click "Create My App"
6. Copy app ID and app secret.

![app created, show app id/secret](https://user-images.githubusercontent.com/507025/82217520-c3e24d00-98d7-11ea-95ee-f7a5fb5fb852.png)

I've set the redirection URL the same as the webhook URL as I'm unclear as to what the difference is.

## Plugin Configuration

1. Clone the plugin
2. In the plugin's folder, run `composer install` to install dependencies
3. Activate the plugin and go to Settings > Woo3pd Helpscout
4. Enter App Id, App Secret, and Secret Key that you copied from Helpscout.

![image](https://user-images.githubusercontent.com/507025/82217689-0ad04280-98d8-11ea-824b-038d4acc1159.png)
