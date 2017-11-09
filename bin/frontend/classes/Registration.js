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

    var pkg = 'quiqqer/frontend-users';

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
                QUIAjax.get('package_quiqqer_frontend-users_ajax_frontend_registrars_userExists', resolve, {
                    'package': pkg,
                    username : username,
                    onError  : reject
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
                QUIAjax.get('package_quiqqer_frontend-users_ajax_frontend_registrars_emailExists', resolve, {
                    'package': pkg,
                    email    : email,
                    onError  : reject
                });
            });
        },

        /**
         * Execute username validation
         *
         * @param {String} username
         * @return {Promise} - return true if valid and false if invalid
         */
        usernameValidation: function (username) {
            var self = this;

            if (username === '') {
                return Promise.resolve(true);
            }

            return new Promise(function (resolve) {
                self.validateUsername(username).then(function (usernameExists) {
                    resolve(!usernameExists);
                });
            });
        },

        /**
         * Exdecute email address validation for the e-mail address
         *
         * @param {String} email
         * @return {Promise} - return true if valid and false if invalid
         */
        emailValidation: function (email) {
            var self = this;

            if (email === '') {
                return Promise.resolve(true);
            }

            return new Promise(function (resolve) {
                self.validateEmail(email).then(function (emailExists) {
                    resolve(!emailExists);
                });
            });
        }
    });
});