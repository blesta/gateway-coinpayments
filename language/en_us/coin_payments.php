<?php
$lang['CoinPayments.name'] = 'CoinPayments.net';
$lang['CoinPayments.description'] = 'A checkout system for cryptocurrencies such as Bitcoin and Litecoin with low fees';

$lang['CoinPayments.api_version'] = 'API Version';
$lang['CoinPayments.getapiversions.legacy'] = 'Legacy';
$lang['CoinPayments.getapiversions.new'] = 'New';

$lang['CoinPayments.merchant_id'] = 'CoinPayments Merchant ID';
$lang['CoinPayments.ipn_secret'] = 'IPN Secret';
$lang['CoinPayments.client_id'] = 'Client ID';
$lang['CoinPayments.client_secret'] = 'Client Secret';

$lang['CoinPayments.api_url'] = 'API URL';
$lang['CoinPayments.getapiurls.a'] = 'Instance A (https://a-api.coinpayments.net)';
$lang['CoinPayments.getapiurls.b'] = 'Instance B (https://b-api.coinpayments.net)';
$lang['CoinPayments.getapiurls.c'] = 'Instance C (https://c-api.coinpayments.net)';
$lang['CoinPayments.getapiurls.sandbox'] = 'Sandbox (https://api.coinpayments.net)';
$lang['CoinPayments.api_url_note'] = 'Select the instance your CoinPayments account is registered on. Client ID and Secret are not interchangeable between instances.';

$lang['CoinPayments.webhook'] = 'Webhook URL';
$lang['CoinPayments.webhook_note'] = 'Payment notifications are sent to this URL. It is registered automatically with every invoice, no configuration is required in CoinPayments.';

$lang['CoinPayments.buildprocess.submit'] = 'Pay with CoinPayments';
$lang['CoinPayments.buildprocess.description'] = 'Payment';

$lang['CoinPayments.!error.api_version.valid'] = 'Please select a valid API version.';
$lang['CoinPayments.!error.api_url.valid'] = 'Please select a valid API URL.';
$lang['CoinPayments.!error.client_id.empty'] = 'Please enter a Client ID.';
$lang['CoinPayments.!error.client_secret.empty'] = 'Please enter a Client Secret.';
$lang['CoinPayments.!error.currency.unsupported'] = 'CoinPayments does not support the %1$s currency.'; // %1$s is the ISO 4217 currency code
$lang['CoinPayments.!error.api.internal'] = 'The gateway responded with an unexpected error (HTTP %1$s).'; // %1$s is the HTTP status code
