/**
 * @module package/quiqqer/frontend-users/bin/frontend/classes/Registration
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/frontend-users/bin/frontend/classes/Registration', [

    'qui/QUI',
    'Locale',
    'Ajax'

], function (QUI, QUILocale, QUIAjax) {
    "use strict";

    var lg = 'quiqqer/frontend-users';

    return new Class({

        Type: 'package/quiqqer/frontend-users/bin/frontend/classes/Registration',

        /**
         * Validate QUIQQER username
         *
         * @param {String} username
         * @return {Promise} - returns true if username is taken; false otherwise
         */
        validateUsername: function (username) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('ajax_users_exists', resolve, {
                    username: username,
                    onError : reject
                });
            });
        },

        /**
         * Validate QUIQQER email address
         *
         * @param {String} email
         * @return {Promise} - returns true if email is taken; false otherwise
         */
        validateEmail: function (email) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('ajax_users_emailExists', resolve, {
                    email  : email,
                    onError: reject
                });
            });
        },

        /**
         * Execute username validation for the given Input
         *
         * @param {HTMLInputElement} UsernameInput
         * @return {Promise} - return true if valid and false if invalid
         */
        inputUsernameValidation: function (UsernameInput) {
            var self     = this;
            var username = UsernameInput.value.trim();

            if (username === '') {
                return Promise.resolve(true);
            }

            return new Promise(function (resolve) {
                self.validateUsername(username).then(function (usernameExists) {
                    if (!usernameExists) {
                        UsernameInput.removeClass('quiqqer-registration-field-error');
                        resolve(true);
                        return;
                    }

                    UsernameInput.addClass('quiqqer-registration-field-error');

                    QUI.getMessageHandler().then(function (MH) {
                        MH.addAttention(
                            QUILocale.get(lg, 'frontend.classes.registration.username_taken'),
                            UsernameInput
                        );

                        resolve(false);
                    });
                });
            });
        },

        /**
         * Exdecute email address validation for the given Input
         *
         * @param {HTMLInputElement} EmailInput
         * @return {Promise} - return true if valid and false if invalid
         */
        inputEmailValidation: function (EmailInput) {
            var self  = this;
            var email = EmailInput.value.trim();

            if (email === '') {
                return Promise.resolve(true);
            }

            return new Promise(function (resolve) {
                self.validateEmail(email).then(function (emailExists) {
                    if (!emailExists) {
                        EmailInput.removeClass('quiqqer-registration-field-error');
                        resolve(true);
                        return;
                    }

                    EmailInput.addClass('quiqqer-registration-field-error');

                    QUI.getMessageHandler().then(function (MH) {
                        MH.addAttention(
                            QUILocale.get(lg, 'frontend.classes.registration.email_taken'),
                            EmailInput
                        );

                        resolve(false);
                    });
                });
            });
        }
    });
});