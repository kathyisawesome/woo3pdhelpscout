# Woo3pd Helpscout

Parse WooCommerce.com support emails into HelpScout conversations

## 1. Get HelpScout API credentials.

1. Go to your profile>My Apps and click "Create My App"
2. Copy App ID and App Secret.

![app created, show app id/secret](https://user-images.githubusercontent.com/507025/82217520-c3e24d00-98d7-11ea-95ee-f7a5fb5fb852.png)

You can set the Redirect URL to a dummy URL as it's not used.

## Option 1. Create a webhook App in Helpscout

1. Install the "Webhooks" app at https://secure.helpscout.net/apps/
2. This plugin only handles the "Conversation Created" webhook, so that's the only one you need to check.
3. Copy the Secret Key 
4. Set the callback URL to `http://yourwebsite.com/?woo3pd-api=helpscout`

![install webhooks app](https://user-images.githubusercontent.com/507025/82216488-423def80-98d6-11ea-83ce-5adcfe53b5c5.png)

5. On your website go to `Settings > Woo3pd Helpscout` and enter the App Id, App Secret, and Secret Key.

![plugin settings fields at your website for HelpScout](https://user-images.githubusercontent.com/507025/83977349-2f0eb600-a8bd-11ea-90af-4014209d2794.png)

6. Set up an Email forwarder to automatically forward your WooCommerce.com support email to the private HelpScout email address.

## Option 2. SendGrid Inbound Parse

Inbound Parse means that SendGrid will be the MX record for all emails to a subdomain of your website. Example, `support@sendgrid.yourdomain.com` will be handled by SendGrid and sent to a webhook.

1. Verify your domain with SendGrid (requires adding CNAME records to your DNS). 
2. Add SendGrid's MX record to your DNS on a subdomain. 
3. Add the Inbound Parse rule in your SendGrid settings. See SendGrid's [Inbound Parse documentation](https://sendgrid.com/docs/for-developers/parsing-email/setting-up-the-inbound-parse-webhook/).
4. Set the rule's delivery webhook to `http://yourwebsite.com/?woo3pd-api=sendgrid`
5. Get your Mailbox ID from HelpScout. Go to Manage > Mailboxes and the ID will be in the URL after mailbox: `https://secure.helpscout.net/settings/mailbox/XXXXX/`

![plugin settings fields at your website for SendGrid](https://user-images.githubusercontent.com/507025/83977529-56b24e00-a8be-11ea-982b-f1d493b5d92b.png)

6. On your website go to `Settings > Woo3pd Helpscout`, select SendGrid and enter the App Id, App Secret, and HelpScout Mailbox ID.
7. Change your WooCommerce.com store settings and set your support email to `support@sendgrid.yourdomain.com`. The username ie `support` can be pretty much anything as it's the subdomain that tells SendGrid to handle it.

## Plugin Installation

1. Clone the plugin.
2. In the plugin's folder, run `composer install --no-dev` to install only required dependencies.
3. Activate the plugin and go to `Settings > Woo3pd Helpscout` to choose service and enter settings.
