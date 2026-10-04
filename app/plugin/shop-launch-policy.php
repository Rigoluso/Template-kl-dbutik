<?php
/** Pure launch policy, kept separate from WordPress for executable policy tests. */
function shop_template_policy_errors(array $state): array {
    $errors = array();
    if (($state['mode'] ?? '') !== 'production') {
        $errors['mode'] = 'Produktionsköp är avstängda i demoläge eller vid ogiltigt SHOP_MODE.';
    }
    foreach (array('company_name' => 'Företagsnamn saknas.', 'organisation_number' => 'Organisationsnummer saknas.', 'address' => 'Fullständig företagsadress saknas.') as $key => $message) {
        if (! isset($state[$key]) || ! is_scalar($state[$key]) || trim((string) $state[$key]) === '') { $errors[$key] = $message; }
    }
    if (! isset($state['contact_email']) || ! is_string($state['contact_email']) || ! filter_var(trim($state['contact_email']), FILTER_VALIDATE_EMAIL)) {
        $errors['contact_email'] = 'Giltig kontaktadress för e-post saknas.';
    }
    foreach (array(
        'legal_reviewed' => 'Villkor och tillämpliga juridiska krav måste granskas för företaget.',
        'products_reviewed' => 'Produktinformation, textilinformation och GPSR måste kontrolleras.',
        'shipping_reviewed' => 'Moms, svensk fraktzon och fraktpriser måste konfigureras och testas.',
        'email_reviewed' => 'E-postleverantör och leverans av transaktionsmejl måste testas.',
        'legal_pages_published' => 'Företagsinformation, villkor, integritet, retur och ångerrätt måste publiceras.',
        'payment_ready' => 'Verifierad Stripe Checkout-integration saknas i denna version.',
        'withdrawal_ready' => 'Digital ångerfunktion med mottagningsbevis saknas i denna version.',
        'price_history_ready' => 'Verifierad prishistorik för prissänkningar saknas i denna version.',
        'mail_retry_ready' => 'Verifierad återförsöksfunktion för ordermejl saknas i denna version.',
        'launch_requested' => 'Butiksägaren har inte aktiverat lansering efter slutförd kontroll.',
    ) as $key => $message) {
        if (($state[$key] ?? false) !== true) { $errors[$key] = $message; }
    }
    return $errors;
}
