/**
 * @module package/quiqqer/frontend-users/bin/frontend/classes/Registration
 */
define('package/quiqqer/frontend-users/bin/frontend/classes/Registration', [

    'qui/QUI',
    'Locale',
    'Ajax',

    'css!package/quiqqer/frontend-users/bin/frontend/classes/Registration.css'

], function (QUI, QUILocale, QUIAjax) {
    "use strict";

    const pkg = 'quiqqer/frontend-users';

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
                    username: username,
                    onError: reject
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
                    email: email,
                    onError: reject
                });
            });
        },

        /**
         * Check if an email address is blacklisted.
         *
         * @param {String} email
         * @return {Promise<Boolean>}
         */
        isEmailBlacklisted: function (email) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_frontend-users_ajax_frontend_registrars_emailBlacklisted', resolve, {
                    'package': pkg,
                    email: email,
                    onError: reject
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
         * Execute email address validation for the e-mail address
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
        },

        /**
         * Validate e-mail syntax
         *
         * @param email
         * @return {Promise}
         */
        emailSyntaxValidation: function (email) {
            if (email === '') {
                return Promise.resolve(true);
            }

            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_frontend-users_ajax_frontend_registrars_validateEmailSyntax', resolve, {
                    'package': pkg,
                    email: email,
                    onError: reject
                });
            });
        },

        register: function (registrar, data) {
            if (typeof data === 'undefined' || typeof data !== 'object') {
                data = {};
            }

            data.registrar = registrar;
            data.registration = 1;

            return new Promise((resolve, reject) => {
                QUIAjax.get('package_quiqqer_frontend-users_ajax_frontend_termsOfUse', (touNeeded) => {
                    if (!touNeeded.required) {
                        return resolve();
                    }

                    require(['qui/controls/windows/Confirm'], (QUIConfirm) => {
                        new QUIConfirm({
                            title: touNeeded.title,
                            maxWidth: 600,
                            maxHeight: 500,
                            ok_button: {
                                text: QUILocale.get(pkg, 'confirm.registration.tou_pp.submit'),
                                textimage: 'fa fa-check'
                            },
                            cancel_button: {
                                text: QUILocale.get(pkg, 'confirm.registration.tou_pp.cancel'),
                                textimage: 'fa fa-close'
                            },
                            events: {
                                onOpen: (win) => {
                                    win.Loader.show();

                                    const content = win.getContent();
                                    content.classList.add('quiqqer-frontendUsers-touConfirm');

                                    content.innerHTML = `
                                        <header>
                                            <h1>${touNeeded.title}</h1>
                                        </header>
                                        <div class="quiqqer-frontendUsers-touConfirm-content default-content">
                                            ${touNeeded.content}
                                        </div>      
                                        <label>
                                            <input type="checkbox" />
                                            <span>
                                                ${touNeeded.label}
                                            </span>
                                        </label>
                                    `;

                                    const submitButton = win.getElm().querySelector('button[name="submit"]');
                                    const checkbox = win.getElm().querySelector('input[type="checkbox"]');

                                    submitButton.disabled = true;
                                    checkbox.addEventListener('change', () => {
                                        submitButton.disabled = !checkbox.checked;
                                    });

                                    win.Loader.hide();
                                },
                                onCancel: () => {
                                    reject();
                                },
                                onSubmit: (win) => {
                                    const content = win.getContent();
                                    if (!content.querySelector('input[type="checkbox"]').checked) {
                                        return;
                                    }

                                    data.termsOfUseAccepted = 1;
                                    resolve();
                                }
                            }
                        }).open();
                    });
                }, {
                    'package': pkg,
                    onError: reject
                })
            }).then(() => {
                return new Promise((resolve, reject) => {
                    QUIAjax.post('package_quiqqer_frontend-users_ajax_frontend_register', resolve, {
                        'package': pkg,
                        registrar: registrar,
                        data: JSON.encode(data),
                        onError: reject
                    });
                });
            });
        }
    });
});