<?php
/** No WordPress mock: exercise the policy with real, hand-checked launch state. */
$policy_file = dirname(__DIR__) . '/shop-launch-policy.php';
if (is_file($policy_file)) { require $policy_file; }
function shop_policy_assert($condition, $message) {
    if (! $condition) { fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL); exit(1); }
}
shop_policy_assert(function_exists('shop_template_policy_errors'), 'No launch policy exists to reject unsafe purchases.');
$ready = array('mode' => 'production', 'company_name' => 'Example AB', 'organisation_number' => '556000-0000', 'address' => 'Testgatan 1', 'contact_email' => 'test@example.test', 'legal_reviewed' => true, 'products_reviewed' => true, 'shipping_reviewed' => true, 'email_reviewed' => true, 'legal_pages_published' => true, 'payment_ready' => true, 'withdrawal_ready' => true, 'price_history_ready' => true, 'mail_retry_ready' => true, 'launch_requested' => true);
shop_policy_assert(shop_template_policy_errors($ready) === array(), 'Complete production configuration must be eligible for checkout.');
foreach (array('payment_ready', 'withdrawal_ready', 'price_history_ready', 'mail_retry_ready', 'legal_pages_published', 'launch_requested') as $key) {
    $incomplete = $ready;
    $incomplete[$key] = false;
    shop_policy_assert(isset(shop_template_policy_errors($incomplete)[$key]), 'Unsafe launch accepted when ' . $key . ' is missing.');
}
$demo = $ready; $demo['mode'] = 'demo';
shop_policy_assert(isset(shop_template_policy_errors($demo)['mode']), 'Demo must never accept a payment, even with all integration flags.');
$unknown = $ready; $unknown['mode'] = 'live';
shop_policy_assert(isset(shop_template_policy_errors($unknown)['mode']), 'Unrecognized deployment mode must fail closed.');
$missing = $ready; unset($missing['company_name']);
shop_policy_assert(isset(shop_template_policy_errors($missing)['company_name']), 'Company identity cannot be empty.');
foreach (array('', '   ', 'invalid') as $email) {
    $invalid = $ready; $invalid['contact_email'] = $email;
    shop_policy_assert(isset(shop_template_policy_errors($invalid)['contact_email']), 'Invalid contact email accepted.');
}
$empty_errors = shop_template_policy_errors(array());
shop_policy_assert(isset($empty_errors['mode'], $empty_errors['company_name'], $empty_errors['payment_ready'], $empty_errors['legal_pages_published']), 'Empty configuration must not silently enable checkout.');
echo 'PASS: production readiness, unavailable integrations, demo, invalid mode, company identity and contact email.' . PHP_EOL;
