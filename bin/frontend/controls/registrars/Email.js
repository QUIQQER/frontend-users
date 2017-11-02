/**
 * JS-Control for default email registrar
 *
 * @module package/quiqqer/frontend-users/bin/frontend/controls/registrars/Email
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/quiqqer/frontend-users/bin/frontend/controls/registrars/Email', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/utils/Functions',
    'qui/controls/utils/PasswordSecurity',

    'Locale',
    'package/quiqqer/frontend-users/bin/Registration',

    'css!package/quiqqer/frontend-users/bin/frontend/controls/registrars/Email.css'

], function (QUI, QUIControl, QUIFunctionUtils, QUIPwSecurityIndicator, QUILocale, Registration) {
    "use strict";

    var lg = 'quiqqer/frontend-users';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/frontend-users/bin/frontend/controls/registrars/Email',

        Binds: [
            '$onImport',
            '$checkForm'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$UsernameInput = null;
            this.$EmailInput    = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            var Elm  = this.getElm();
            var self = this;

            // Address input
            var AddressElm = Elm.getElement(
                'section.quiqqer-registration-address'
            );

            if (AddressElm) {
                // if address elm does not have the __hidden class -> address is not optional
                if (AddressElm.hasClass('quiqqer-registration-address__hidden')) {
                    var AddressHeaderElm = AddressElm.getPrevious('h2');

                    AddressHeaderElm.addEvent('click', function (event) {
                        event.stop();

                        if (AddressElm.hasClass('quiqqer-registration-address__hidden')) {
                            AddressElm.removeClass('quiqqer-registration-address__hidden');
                        } else {
                            AddressElm.addClass('quiqqer-registration-address__hidden');
                        }
                    });
                }
            }

            // Validation
            var SubmitBtn = Elm.getElement('button[type="submit"]');

            /**
             * Display error msg on invalid input
             *
             * @param {HTMLInputElement} Input
             * @param {Boolean} isValid
             * @param {String} [errorMsg]
             * @constructor
             */
            var HandleInputValidation = function (Input, isValid, errorMsg) {
                var ErrorElm = Input.getNext(
                    '.quiqqer-registration-field-error-msg'
                );

                if (isValid) {
                    if (ErrorElm) {
                        ErrorElm.destroy();
                    }

                    Input.removeClass('quiqqer-registration-field-error');
                    return;
                }

                Input.addClass('quiqqer-registration-field-error');

                if (ErrorElm) {
                    return;
                }

                new Element('span', {
                    'class': 'quiqqer-registration-field-error-msg',
                    html   : errorMsg
                }).inject(Input, 'after');
            };

            /**
             * Check if the form is valid in its current state
             * and enable or disable form submit button
             */
            var CheckFormValidation = function() {
                // check if submit btn has to be disabled
                var invalidElements = Elm.getElements(
                    '.quiqqer-registration-field-error-msg'
                );

                SubmitBtn.disabled = invalidElements.length;
            };

            // Email validation
            var EmailInput = Elm.getElement('input[name="email"]');

            if (EmailInput) {
                EmailInput.addEvent('blur', function (event) {
                    Registration.emailValidation(event.target.value).then(function (isValid) {
                        HandleInputValidation(
                            event.target,
                            isValid,
                            QUILocale.get(lg, 'exception.registrars.email.email_already_exists')
                        );

                        CheckFormValidation();
                    });
                });
            }

            // Username validation
            var UsernameInput = Elm.getElement('input[name="username"]');

            if (UsernameInput) {
                UsernameInput.addEvent('blur', function (event) {
                    Registration.usernameValidation(event.target.value).then(function (isValid) {
                        HandleInputValidation(
                            event.target,
                            isValid,
                            QUILocale.get(lg, 'exception.registrars.email.username_already_exists')
                        );

                        CheckFormValidation();
                    });
                });
            }

            // Password validation
            var PasswordStrengthElm = this.$Elm.getElement(
                '.quiqqer-registration-passwordstrength'
            );

            if (PasswordStrengthElm) {
                var PasswordInput = this.$Elm.getElement(
                    '.quiqqer-registration-password input'
                );

                var PassStrenghtIndicator = new QUIPwSecurityIndicator().inject(
                    PasswordStrengthElm
                );

                PassStrenghtIndicator.bindInput(PasswordInput);
            }

            var Form = Elm.getParent('form');

            Form.addEvent('submit', function (event) {
                event.stop();

                self.$checkForm().then(function (isFormValid) {
                    if (isFormValid) {
                        Form.submit();
                    }
                });
            });
        }
    });
});