/**
 * @module package/quiqqer/frontend-users/bin/frontend/controls/Registration

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

    const lg = 'quiqqer/frontend-users';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/frontend-users/bin/frontend/controls/Registration',

        Binds: [
            '$onImport',
            '$sendForm'
        ],

        options: {
            registrars: [], // list of registrar that are displayed in this controls
            showSuccess: true
        },

        initialize: function (options) {
            this.parent(options);

            this.Loader = null;
            this.$TermsOfUseCheckBox = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            const Elm = this.getElm(),
                forms = Elm.querySelectorAll('form.quiqqer-frontendUsers-controls-registration-registrar');

            QUI.fireEvent('quiqqerFrontendUsersRegisterStart', [this]);

            this.Loader = new QUILoader();
            this.Loader.inject(Elm);

            Array.from(forms).forEach((form) => {
                form.addEventListener('submit', (event) => {
                    event.stopPropagation();
                    event.preventDefault();
                    this.$sendForm(event.target).then(this.$onImport);
                });
            });

            QUI.addEvent('quiqqerUserAuthLoginSuccess', () => {
                this.fireEvent('register', [this]);
                QUI.fireEvent('quiqqerFrontendUsersRegisterSuccess', [this]);
            });


            // Terms Of Use / Privacy Policy
            const termsOfUseElm = Elm.getElement('.quiqqer-frontendUsers-controls-registration-termsOfUse');

            if (termsOfUseElm) {
                const termsOfUseLink = termsOfUseElm.getElement('a.quiqqer-frontendusers-termsofuse-link');
                const privacyPolicyLink = termsOfUseElm.getElement('a.quiqqer-frontendusers-privacypolicy-link');

                if (termsOfUseLink) {
                    termsOfUseLink.addEvent('click', function (event) {
                        event.stop();

                        new QUISiteWindow({
                            closeButtonText: QUILocale.get(lg, 'btn.close'),
                            showTitle: true,
                            project: QUIQQER_PROJECT.name,
                            lang: QUIQQER_PROJECT.lang,
                            id: termsOfUseElm.get('data-termsofusesiteid')
                        }).open();
                    });
                }

                if (privacyPolicyLink) {
                    privacyPolicyLink.addEvent('click', function (event) {
                        event.stop();

                        new QUISiteWindow({
                            showTitle: true,
                            project: QUIQQER_PROJECT.name,
                            lang: QUIQQER_PROJECT.lang,
                            id: termsOfUseElm.get('data-privacypolicysiteid')
                        }).open();
                    });
                }

                this.$TermsOfUseCheckBox = Elm.getElement(
                    '.quiqqer-frontendUsers-controls-registration-termsOfUse input[type="checkbox"]'
                );
            }

            // Redirect
            const redirectElm = Elm.querySelector('.quiqqer-frontendUsers-redirect');

            if (redirectElm) {
                if (redirectElm.get('data-reload')) {
                    window.location.reload();
                    return;
                }

                const url = redirectElm.get('data-url');
                const instant = redirectElm.get('data-instant') === "1";

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

            const formData = QUIFormUtils.getFormData(Form);
            const container = this.getElm();

            if (this.$TermsOfUseCheckBox) {
                formData.termsOfUseAccepted = this.$TermsOfUseCheckBox.checked;
            }

            let run;

            if (!formData.termsOfUseAccepted) {
                run = new Promise((resolve, reject) => {
                    require(['package/quiqqer/frontend-users/bin/Registration'], (registration) => {
                        registration.register(
                            Form.get('data-registrar'),
                            formData
                        ).then(resolve).catch(reject);
                    });
                });
            } else {
                run = new Promise((resolve, reject) => {
                    QUIAjax.post('package_quiqqer_frontend-users_ajax_frontend_register', resolve, {
                        'package': 'quiqqer/frontend-users',
                        registrar: Form.get('data-registrar'),
                        data: JSON.encode(formData),
                        registrars: this.getAttribute('registrars'),
                        onError: reject
                    });
                });
            }

            return run.then((result) => {
                if (result.userActivated) {
                    QUI.fireEvent('quiqqerFrontendUsersUserActivate', [
                        result.userId,
                        result.registrarHash,
                        result.registrarType
                    ]);
                }


                const node = new Element('div', {
                    html: result.html
                });

                const Registration = node.getElement(
                    '[data-qui="package/quiqqer/frontend-users/bin/frontend/controls/Registration"]'
                );

                container.set('html', Registration.get('html'));
                const login = container.querySelector('.quiqqer-frontendUsers-frontendlogin-login');

                if (login) {
                    // 2fa login?
                    Array.from(login.querySelectorAll('h1')).forEach((h1) => {
                        h1.parentNode.removeChild(h1);
                    });
                }

                if (this.getAttribute('showSuccess') === false) {
                    const messages = Array.from(
                        container.querySelectorAll('.content-message-success, .content-message-information')
                    );

                    messages.forEach((messages) => {
                        messages.parentNode.removeChild(messages);
                    });
                }

                return QUI.parse(container).then(() => {
                    this.fireEvent('register', [this]);
                    QUI.fireEvent('quiqqerFrontendUsersRegisterSuccess', [this]);
                });
            });
        }
    });
});
