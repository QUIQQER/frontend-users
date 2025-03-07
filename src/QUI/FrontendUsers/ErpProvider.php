<?php

/**
 * This file contains QUI\FrontendUsers\ErpProvider
 */

namespace QUI\FrontendUsers;

use QUI;
use QUI\ERP\Api\AbstractErpProvider;

/**
 * Class ErpProvider
 *
 * @package QUI\ERP\Order
 */
class ErpProvider extends AbstractErpProvider
{
    /**
     * @return array[]
     */
    public static function getMailLocale(): array
    {
        return [
            [
                'title' => QUI::getLocale()->get(
                    'quiqqer/frontend-users',
                    'mail.text.registrationWelcome.title'
                ),
                'description' => QUI::getLocale()->get(
                    'quiqqer/frontend-users',
                    'mail.text.registrationWelcome.description'
                ),
                'subject' => ['quiqqer/frontend-users', 'mail.registration_welcome.subject'],
                'content' => ['quiqqer/frontend-users', 'mail.registration_welcome.body'],

                'subject.description' => ['quiqqer/frontend-users', 'mail.registration_welcome.subject.description'],
                'content.description' => ['quiqqer/frontend-users', 'mail.registration_welcome.body.description']
            ],

            [
                'title' => QUI::getLocale()->get(
                    'quiqqer/frontend-users',
                    'mail.text.activation.title'
                ),
                'description' => QUI::getLocale()->get(
                    'quiqqer/frontend-users',
                    'mail.text.activation.description'
                ),
                'subject' => ['quiqqer/frontend-users', 'mail.registration_activation.subject'],
                'content' => ['quiqqer/frontend-users', 'mail.registration_activation.body'],

                'subject.description' => ['quiqqer/frontend-users', 'mail.registration_activation.subject.description'],
                'content.description' => ['quiqqer/frontend-users', 'mail.registration_activation.body.description']
            ],

            [
                'title' => QUI::getLocale()->get(
                    'quiqqer/frontend-users',
                    'mail.text.confirmEmail.title'
                ),
                'description' => QUI::getLocale()->get(
                    'quiqqer/frontend-users',
                    'mail.text.confirmEmail.description'
                ),
                'subject' => ['quiqqer/frontend-users', 'mail.confirm_email_address.subject'],
                'content' => ['quiqqer/frontend-users', 'mail.confirm_email_address.body'],

                'subject.description' => ['quiqqer/frontend-users', 'mail.confirm_email_address.subject.description'],
                'content.description' => ['quiqqer/frontend-users', 'mail.confirm_email_address.body.description']
            ],


            [
                'title' => QUI::getLocale()->get(
                    'quiqqer/frontend-users',
                    'mail.text.changeEmail.title'
                ),
                'description' => QUI::getLocale()->get(
                    'quiqqer/frontend-users',
                    'mail.text.changeEmail.description'
                ),
                'subject' => ['quiqqer/frontend-users', 'mail.change_email_address.subject'],
                'content' => ['quiqqer/frontend-users', 'mail.change_email_address.body'],

                'subject.description' => ['quiqqer/frontend-users', 'mail.change_email_address.subject.description'],
                'content.description' => ['quiqqer/frontend-users', 'mail.change_email_address.body.description']
            ],

            [
                'title' => QUI::getLocale()->get(
                    'quiqqer/frontend-users',
                    'mail.text.deleteUserConfirmation.title'
                ),
                'description' => QUI::getLocale()->get(
                    'quiqqer/frontend-users',
                    'mail.text.deleteUserConfirmation.description'
                ),
                'subject' => ['quiqqer/frontend-users', 'mail.delete_user_confirm.subject'],
                'content' => ['quiqqer/frontend-users', 'mail.delete_user_confirm.body'],

                'subject.description' => ['quiqqer/frontend-users', 'mail.delete_user_confirm.subject.description'],
                'content.description' => ['quiqqer/frontend-users', 'mail.delete_user_confirm.body.description']
            ]
        ];
    }
}
