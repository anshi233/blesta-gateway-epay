<?php
/**
 * zh_cn language for the EPay gateway.
 */
// Basics
$lang['Epay.name'] = '易支付（支付宝，微信支付和其他）';
$lang['Epay.description'] = '易支付网关。';


// Errors
$lang['Epay.!error.pid.empty'] = '商户ID不应为空';
$lang['Epay.!error.pid.valid'] = '无效的商户ID';
$lang['Epay.!error.key.empty'] = 'API密钥key不应为空';
$lang['Epay.!error.key.valid'] = '无效的API密钥key';
$lang['Epay.!error.apiurl.empty'] = 'API网关URL不应为空';
$lang['Epay.!error.api.valid'] = '无法验证API凭据。请检查您的设置。';

$lang['Epay.!error.event.invalid_sign'] = 'API签名无效。';
$lang['Epay.!error.event.unsupported'] = '不支持的订单状态。我们只查看成功订单';
$lang['Epay.!error.event.fake_success_payment'] = '用户返回成功重定向支付结果。但远程支付网关未收到支付。';

// Settings
$lang['Epay.meta.pid'] = '易支付商户ID';
$lang['Epay.meta.key'] = '易支付API密钥 Key';
$lang['Epay.meta.apiurl'] = '易支付API网关URL';

$lang['Epay.webhook'] = '易支付插件Webhook';
$lang['Epay.webhook_note'] = 'Epay webhook已在请求中发送。但这将有助于调试';
