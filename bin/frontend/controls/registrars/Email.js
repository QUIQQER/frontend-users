/**
 * JS-Control for default email registrar
 *
 * @module package/quiqqer/frontend-users/bin/frontend/controls/registrars/Email
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/quiqqer/frontend-users/bin/frontend/controls/registrars/Email', [

    'qui/QUI',
    'qui/controls/Control',
    'Locale',
    'package/quiqqer/frontend-users/bin/Registration',

    'css!package/quiqqer/frontend-users/bin/frontend/controls/registrars/Email.css'

], function (QUI, QUIControl, QUILocale, Registration) {
    "use strict";

    var lg = 'quiqqer/frontend-users';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/frontend-users/bin/frontend/controls/registrars/Email',

        Binds: [
            '$onImport'
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
            var Elm = this.getElm();

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

            // Email validation
            var EmailInputElm = Elm.getElement('input[name="email"]');

            //EmailInputElm.addEvent('change', function(event) {
            //    event.stop();
            //
            //    Registration.validateEmail
            //});

            // Username validation
            var UsernameInputElm = Elm.getElement('input[name="username"]');

            if (UsernameInputElm) {
                UsernameInputElm.addEvent('blur', function(event) {
                    event.stop();

                    var username = event.target.value.trim();

                    if (username === '') {
                        return;
                    }

                    Registration.validateUsername(username).then(function(usernameExists) {
                        if (!usernameExists) {
                            return;
                        }

                        QUI.getMessageHandler().then(function(MH) {
                            MH.addAttention(
                                QUILocale.get(lg, 'controls.registrars.email.invalid_username'),
                                UsernameInputElm
                            );
                        });
                    });
                });
            }
        }
    });
});