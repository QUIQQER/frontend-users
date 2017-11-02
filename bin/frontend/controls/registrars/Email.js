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

    'Locale',
    'package/quiqqer/frontend-users/bin/Registration',

    'css!package/quiqqer/frontend-users/bin/frontend/controls/registrars/Email.css'

], function (QUI, QUIControl, QUIFunctionUtils, QUILocale, Registration) {
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
            var FuncHandleInputValidation = function (Input, isValid, errorMsg) {
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

            var CheckFormValidation = function() {
                // check if submit btn has to be disabled
                var invalidElements = Elm.getElements(
                    '.quiqqer-registration-field-error-msg'
                );

                SubmitBtn.disabled = invalidElements.length;
            };

            // Email validation
            this.$EmailInput = Elm.getElement('input[name="email"]');

            this.$EmailInput.addEvent('blur', function (event) {
                Registration.emailValidation(event.target.value).then(function (isValid) {
                    FuncHandleInputValidation(
                        event.target,
                        isValid,
                        QUILocale.get(lg, 'exception.registrars.email.email_already_exists')
                    );

                    CheckFormValidation();
                });
            });

            // Username validation
            this.$UsernameInput = Elm.getElement('input[name="username"]');

            this.$UsernameInput.addEvent('blur', function (event) {
                Registration.usernameValidation(event.target.value).then(function (isValid) {
                    FuncHandleInputValidation(
                        event.target,
                        isValid,
                        QUILocale.get(lg, 'exception.registrars.email.username_already_exists')
                    );

                    CheckFormValidation();
                });
            });

            var Form = Elm.getParent('form');

            Form.addEvent('submit', function (event) {
                event.stop();

                self.$checkForm().then(function (isFormValid) {
                    if (isFormValid) {
                        Form.submit();
                    }
                });
            });
        },

        /**
         * Checks if the form is valid and can be sent
         *
         * @return {Promise} - return true if form can be submitted; false otherwise
         */
        $checkForm: function () {
            var self = this;
            var Elm  = this.getElm();

            var checkInvalidElements = function () {
                var invalidElements = Elm.getElements(
                    '.quiqqer-registration-field-error'
                );

                if (invalidElements.length) {
                    SubmitBtn.disabled = true;
                    return false;
                }

                SubmitBtn.disabled = false;
                return true;
            };

            return new Promise(function (resolve) {
                var checkPromises = [];

                if (self.$UsernameInput) {
                    checkPromises.push(
                        Registration.inputUsernameValidation(self.$UsernameInput)
                    );
                }

                if (self.$EmailInput) {
                    checkPromises.push(
                        Registration.inputEmailValidation(self.$EmailInput)
                    );
                }

                if (!checkPromises.length) {
                    resolve(checkInvalidElements());
                    return;
                }

                Promise.all(checkPromises).then(checkInvalidElements);
            });
        }
    });
});