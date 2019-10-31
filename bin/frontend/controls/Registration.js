/**
 * @module package/quiqqer/frontend-users/bin/frontend/controls/Registration
 * @author www.pcsg.de (Henning Leutz)
 * @author www.pcsg.de (Patrick Müller)
 *
 * @event onRegister [this] - fires if the user successfully registers a user account
 * @event onQuiqqerFrontendUsersRegisterStart [this]
 * @event onQuiqqerFrontendUsersRegisterSuccess [this]
 */
define('package/quiqqer/frontend-users/bin/frontend/controls/Registration', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/loader/Loader',
    'package/quiqqer/controls/bin/site/Window',
    'qui/utils/Form',
    'Locale',
    'Ajax'

], function (QUI, QUIControl, QUILoader, QUISiteWindow, QUIFormUtils, QUILocale, QUIAjax) {
    "use strict";

    var lg = 'quiqqer/frontend-users';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/frontend-users/bin/frontend/controls/Registration',

        Binds: [
            '$onImport',
            '$sendForm'
        ],

        options: {
            registrars: [] // list of registar that are displayed in this controls
        },

        initialize: function (options) {
            this.parent(options);

            this.Loader              = null;
            this.$TermsOfUseCheckBox = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            var self  = this,
                Elm   = this.getElm(),
                forms = Elm.getElements('form.quiqqer-frontendUsers-controls-registration-registrar');

            QUI.fireEvent('quiqqerFrontendUsersRegisterStart', [this]);

            this.Loader = new QUILoader();
            this.Loader.inject(Elm);

            forms.addEvent('submit', function (event) {
                event.stop();
                self.$sendForm(event.target).then(self.$onImport);
            });

            // Terms Of Use / Privacy Policy
            var TermsOfUseElm = Elm.getElement('.quiqqer-frontendUsers-controls-registration-termsOfUse');

            if (TermsOfUseElm) {
                var TermsOfUseLink    = TermsOfUseElm.getElement('a.quiqqer-frontendusers-termsofuse-link');
                var PrivacyPolicyLink = TermsOfUseElm.getElement('a.quiqqer-frontendusers-privacypolicy-link');

                if (TermsOfUseLink) {
                    TermsOfUseLink.addEvent('click', function (event) {
                        event.stop();

                        new QUISiteWindow({
                            closeButtonText: QUILocale.get(lg, 'btn.close'),
                            showTitle      : true,
                            project        : QUIQQER_PROJECT.name,
                            lang           : QUIQQER_PROJECT.lang,
                            id             : TermsOfUseElm.get('data-termsofusesiteid')
                        }).open();
                    });
                }

                if (PrivacyPolicyLink) {
                    PrivacyPolicyLink.addEvent('click', function (event) {
                        event.stop();

                        new QUISiteWindow({
                            showTitle: true,
                            project  : QUIQQER_PROJECT.name,
                            lang     : QUIQQER_PROJECT.lang,
                            id       : TermsOfUseElm.get('data-privacypolicysiteid')
                        }).open();
                    });
                }

                this.$TermsOfUseCheckBox = Elm.getElement(
                    '.quiqqer-frontendUsers-controls-registration-termsOfUse input[type="checkbox"]'
                );
            }

            // Redirect
            var RedirectElm = Elm.getElement(
                '.quiqqer-frontendUsers-redirect'
            );

            if (RedirectElm) {
                var url     = RedirectElm.get('data-url');
                var instant = RedirectElm.get('data-instant') === "1";

                if (instant) {
                    window.location = url;
                    return;
                }

                (function () {
                    window.location = url;
                }.delay(10000));
            }
        },

        /**
         * Send the registrar form
         *
         * @param {HTMLFormElement} Form
         * @return {Promise}
         */
        $sendForm: function (Form) {
            this.Loader.show();

            var self     = this,
                formData = QUIFormUtils.getFormData(Form);

            if (this.$TermsOfUseCheckBox) {
                formData.termsOfUseAccepted = this.$TermsOfUseCheckBox.checked;
            }

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_frontend-users_ajax_frontend_register', function (html) {
                    var Elm = self.getElm();

                    var Container = new Element('div', {
                        html: html
                    });

                    var Registration = Container.getElement(
                        '[data-qui="package/quiqqer/frontend-users/bin/frontend/controls/Registration"]'
                    );

                    Elm.set('html', Registration.get('html'));

                    QUI.parse(Elm).then(function () {
                        if (Elm.getElement('.content-message-success') ||
                            Elm.getElement('.content-message-attention')) {

                            self.fireEvent('register', [self]);
                            QUI.fireEvent('quiqqerFrontendUsersRegisterSuccess', [self]);
                        }

                        resolve();
                    }, reject);
                }, {
                    'package' : 'quiqqer/frontend-users',
                    registrar : Form.get('data-registrar'),
                    data      : JSON.encode(formData),
                    registrars: self.getAttribute('registrars'),
                    onError   : reject
                });
            });
        }
    });
});