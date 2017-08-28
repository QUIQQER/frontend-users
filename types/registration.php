<?php

// AGB
$result = $Project->getSites(array(
    'where' => array(
        'type' => 'quiqqer/intranet:registration/termsOfUse'
    ),
    'limit' => 1
));


if (isset($result[0])) {
    $Engine->assign('Site_TermsAndConditions', $result[0]);
}


// Datenschutz
$result = $Project->getSites(array(
    'where' => array(
        'type' => 'quiqqer/intranet:registration/privacy'
    ),
    'limit' => 1
));

if (isset($result[0])) {
    $Engine->assign('Site_Privacy', $result[0]);
}

/**
 * User Registration
 */

$Registration = new QUI\FrontendUsers\Controls\Registration();

$Engine->assign(array(
    'Registration' => $Registration
));
