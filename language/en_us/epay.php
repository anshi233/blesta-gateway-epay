<?php
/**
 * en_us language for the EPay gateway.
 */
// Basics
$lang['Epay.name'] = 'EPay Gateway';
$lang['Epay.description'] = 'EPay gateway.';


// Errors
$lang['Epay.!error.pid.empty'] = 'pid is empty.';
$lang['Epay.!error.pid.valid'] = 'pid is invalid.';
$lang['Epay.!error.key.empty'] = 'key is empty.';
$lang['Epay.!error.key.valid'] = 'key is invalid.';
$lang['Epay.!error.apiurl.empty'] = 'apiurl is empty.';
$lang['Epay.!error.apiurl.valid'] = 'apiurl is invalid.';

$lang['Epay.!error.event.invalid_sign'] = 'Invalid API signature is received.';
$lang['Epay.!error.event.unsupported'] = 'Unsupported order status. We only looking at success order';
$lang['Epay.!error.event.fake_success_payment'] = 'User return success redirect payment result. But remote payment gateway do not recieve payment.';

// Settings
$lang['Epay.meta.pid'] = 'EPay Merchant (Shop) ID';
$lang['Epay.meta.key'] = 'EPay API Key';
$lang['Epay.meta.apiurl'] = 'EPay Gateway URL';

$lang['Epay.webhook'] = 'EPay Webhook';
$lang['Epay.webhook_note'] = 'Epay webhook is already sent in request. But this will help for debugging';
