<?php

use QUI\Projects\Site\Utils as QUISiteUtils;

QUI::getAjax()->registerFunction(
    'package_quiqqer_frontend-users_ajax_frontend_termsOfUse',
    function () {
        $RegistrarHandler = QUI\FrontendUsers\Handler::getInstance();
        $registrationSettings = $RegistrarHandler->getRegistrationSettings();
        $projectLang = QUI::getRewrite()->getProject()->getLang();

        // Terms Of Use
        $required = false;
        $label = '';
        $title = '';
        $content = '';

        $termsOfUse = null;
        $privacyPolicy = null;

        if (
            $registrationSettings['termsOfUseRequired']
            && (
                !empty($registrationSettings['termsOfUseSite'][$projectLang])
                || !empty($registrationSettings['privacyPolicySite'][$projectLang])
            )
        ) {
            if (!empty($registrationSettings['termsOfUseSite'][$projectLang])) {
                $termsOfUse = QUISiteUtils::getSiteByLink(
                    $registrationSettings['termsOfUseSite'][$projectLang]
                );
            }

            if (!empty($registrationSettings['privacyPolicySite'][$projectLang])) {
                $privacyPolicy = QUISiteUtils::getSiteByLink(
                    $registrationSettings['privacyPolicySite'][$projectLang]
                );
            }

            // determine the label for terms of use / privacy policy checkbox
            if ($termsOfUse && $privacyPolicy) {
                $label = QUI::getLocale()->get(
                    'quiqqer/frontend-users',
                    'control.registration.terms_of_use_and_privacy_policy.label',
                    [
                        'termsOfUseUrl' => $termsOfUse->getUrlRewrittenWithHost(),
                        'termsOfUseSiteTitle' => $termsOfUse->getAttribute('title'),
                        'privacyPolicyUrl' => $privacyPolicy->getUrlRewrittenWithHost(),
                        'privacyPolicySiteTitle' => $privacyPolicy->getAttribute('title')
                    ]
                );

                $title = QUI::getLocale()->get('quiqqer/frontend-users', 'confirm.registration.tou_tou_pp.title');
                $content = QUI::getLocale()->get('quiqqer/frontend-users', 'confirm.registration.tou_tou_pp.content');
            } elseif ($termsOfUse) {
                $label = QUI::getLocale()->get(
                    'quiqqer/frontend-users',
                    'control.registration.terms_of_use.label',
                    [
                        'termsOfUseUrl' => $termsOfUse->getUrlRewrittenWithHost(),
                        'termsOfUseSiteTitle' => $termsOfUse->getAttribute('title')
                    ]
                );

                $title = QUI::getLocale()->get('quiqqer/frontend-users', 'confirm.registration.tou.title');
                $content = QUI::getLocale()->get('quiqqer/frontend-users', 'confirm.registration.tou.content');
            } elseif ($privacyPolicy) {
                $label = QUI::getLocale()->get(
                    'quiqqer/frontend-users',
                    'control.registration.privacy_policy.label',
                    [
                        'privacyPolicyUrl' => $privacyPolicy->getUrlRewrittenWithHost(),
                        'privacyPolicySiteTitle' => $privacyPolicy->getAttribute('title')
                    ]
                );

                $title = QUI::getLocale()->get('quiqqer/frontend-users', 'confirm.registration.tou_pp.title');
                $content = QUI::getLocale()->get('quiqqer/frontend-users', 'confirm.registration.ttouou_pp.content');
            }

            $required = true;
        }

        return [
            'required' => $required,
            'label' => $label,
            'title' => $title,
            'content' => $content,
            'termsOfUse' => $termsOfUse?->getId(),
            'privacyPolicy' => $privacyPolicy?->getId(),
        ];
    }
);
