<?php
/**
 * en_us language for the EPay gateway.
 */
// Basics
$lang['EPayGateway.name'] = 'EPay Gateway';
$lang['EPayGateway.description'] = 'EPay gateway.';


// Errors
$lang['EPayGateway.!error.pid.empty'] = 'pid is empty.';
$lang['EPayGateway.!error.pid.valid'] = 'pid is invalid.';
$lang['EPayGateway.!error.key.empty'] = 'key is empty.';
$lang['EPayGateway.!error.key.valid'] = 'key is invalid.';
$lang['EPayGateway.!error.apiurl.empty'] = 'apiurl is empty.';
$lang['EPayGateway.!error.apiurl.valid'] = 'apiurl is invalid.';

$lang['EPayGateway.!error.event.invalid_sign'] = 'Invalid API signature is received.';
$lang['EPayGateway.!error.event.unsupported'] = 'Unsupported order status. We only looking at success order';
$lang['EPayGateway.!error.event.fake_success_payment'] = 'User return success redirect payment result. But remote payment gateway do not recieve payment.';

// Settings
$lang['EPayGateway.meta.pid'] = 'EPay Merchant(Shop) ID';
$lang['EPayGateway.meta.key'] = 'EPay API Key';
$lang['EPayGateway.meta.apiurl'] = 'EPay Gateway URL';

$lang['EPayGateway.webhook'] = 'EPay Webhook';
$lang['EPayGateway.webhook_note'] = 'Epay webhook is already sent in request. But this will help for debugging';
