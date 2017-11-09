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

        options: {
            emailisusername: false  // checks for existing username if only email field is enabled
        },

        Binds: [
            '$onImport',
            '$checkForm'
        ],

        initialize: function (options) {
            this.parent(options);

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
                if (AddressElm.hasClass('quiqqer-frontendUsers__hidden')) {
                    var AddressHeaderElm = AddressElm.getPrevious('h2');

                    AddressHeaderElm.addEvent('click', function (event) {
                        event.stop();

                        if (AddressElm.hasClass('quiqqer-frontendUsers__hidden')) {
                            AddressElm.removeClass('quiqqer-frontendUsers__hidden');
                        } else {
                            AddressElm.addClass('quiqqer-frontendUsers__hidden');
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
            var CheckFormValidation = function () {
                // check if submit btn has to be disabled
                var invalidElements = Elm.getElements(
                    '.quiqqer-registration-field-error-msg'
                );

                var isValid = invalidElements.length;

                SubmitBtn.disabled = isValid;

                return isValid;
            };

            // Email validation
            var EmailInput        = Elm.getElement('input[name="email"]');
            var EmailConfirmInput = Elm.getElement('input[name="emailConfirm"]');
            var emailIsUsername   = this.getAttribute('emailisusername');

            if (EmailInput) {
                EmailInput.addEvent('blur', function (event) {
                    var value         = event.target.value;
                    var checkPromises = [
                        Registration.emailValidation(value)
                    ];

                    if (emailIsUsername) {
                        checkPromises.push(Registration.usernameValidation(value));
                    }

                    Promise.all(checkPromises).then(function (result) {
                        var isValid = true;

                        for (var i = 0, len = result.length; i < len; i++) {
                            if (!result[i]) {
                                isValid = false;
                                break;
                            }
                        }

                        HandleInputValidation(
                            event.target,
                            isValid,
                            QUILocale.get(lg, 'exception.registrars.email.email_already_exists')
                        );

                        CheckFormValidation();
                    });
                });
            }

            if (EmailConfirmInput) {
                EmailConfirmInput.addEvent('blur', function (event) {
                    HandleInputValidation(
                        event.target,
                        EmailInput.value === event.target.value,
                        QUILocale.get(lg, 'exception.registrars.email.email_addresses_not_equal')
                    );
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
            var PasswordInput        = Elm.getElement('input[name="password"]');
            var PasswordConfirmInput = Elm.getElement('input[name="passwordConfirm"]');

            var PasswordStrengthElm = this.$Elm.getElement(
                '.quiqqer-registration-passwordstrength'
            );

            if (PasswordInput) {
                if (PasswordConfirmInput) {
                    PasswordConfirmInput.addEvent('blur', function (event) {
                        HandleInputValidation(
                            event.target,
                            PasswordInput.value === event.target.value,
                            QUILocale.get(lg, 'exception.registrars.email.passwords_not_equal')
                        );
                    });
                }

                if (PasswordStrengthElm) {
                    var PassStrenghtIndicator = new QUIPwSecurityIndicator().inject(
                        PasswordStrengthElm
                    );

                    PassStrenghtIndicator.bindInput(PasswordInput);
                }
            }

            // Handle form submit
            var Form = Elm.getParent('form');

            Form.addEvent('submit', function (event) {
                event.stop();

                if (CheckFormValidation()) {
                    Form.submit();
                }
            });
        }
    });
});