/**
 * JS-Control for default email registrar
 *
 * @module package/quiqqer/frontend-users/bin/frontend/controls/registrars/Email
 */
define('package/quiqqer/frontend-users/bin/frontend/controls/registrars/Email', [

    'qui/QUI',
    'qui/controls/Control',
    'utils/Controls',
    'qui/utils/Functions',
    'qui/controls/utils/PasswordSecurity',
    'Locale',
    'package/quiqqer/frontend-users/bin/Registration',

    'css!package/quiqqer/frontend-users/bin/frontend/controls/registrars/Email.css'

], function (QUI, QUIControl, QUIControlUtils, QUIFunctionUtils, QUIPwSecurityIndicator, QUILocale, Registration) {
    "use strict";

    const lg = 'quiqqer/frontend-users';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/frontend-users/bin/frontend/controls/registrars/Email',

        options: {
            emailisusername: false,  // checks for existing username if only email field is enabled
            usecaptcha: false,  // Captcha is used
            noBlurCheck: true
        },

        Binds: [
            '$onImport',
            '$checkForm'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$captchaResponse = false;
            this.$passwordFieldset = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            const container = this.getElm();

            const emailInput = container.querySelector('input[name="email"]');
            const emailIsUsername = this.getAttribute('emailisusername');

            const address = container.querySelector('section.quiqqer-registration-address');
            const submitButton = container.querySelector('button[type="submit"]');

            const passwordInput = container.querySelector('input[name="password"]');
            const passwordStrength = container.querySelector('.quiqqer-registration-passwordstrength');

            container.set('data-quiid', this.getId());

            QUI.addEvent('quiqqerFrontendUsersRegisterError', () => {
                this.stopLoading();
            });

            // Address input
            if (address) {
                // if address elm does not have the __hidden class -> address is not optional
                if (address.hasClass('quiqqer-frontendUsers__hidden')) {
                    const AddressHeaderElm = address.getPrevious('h2');

                    AddressHeaderElm.addEvent('click', (event) => {
                        event.stop();

                        if (address.hasClass('quiqqer-frontendUsers__hidden')) {
                            address.removeClass('quiqqer-frontendUsers__hidden');
                        } else {
                            address.addClass('quiqqer-frontendUsers__hidden');
                        }
                    });
                }
            }

            // Validation

            // if yes, show password
            submitButton.addEventListener('click', (e) => {
                e.stopPropagation();
                e.preventDefault();

                const type = submitButton.getAttribute('data-type');

                if (type === 'registration') {
                    this.handleRegistration();
                    return;
                }

                if (type === 'login') {
                    this.handleLogin();
                }
            });

            emailInput.addEventListener('change', () => {
                this.$hideResetPasswordField();
            });

            /**
             * Display error msg on invalid input
             *
             * @param {HTMLInputElement} Input
             * @param {Boolean} isValid
             * @param {String} [errorMsg]
             */
            const HandleInputValidation = (Input, isValid, errorMsg) => {
                const ErrorElm = Input.getNext('[data-name="error-message"]');

                if (isValid) {
                    if (ErrorElm) {
                        ErrorElm.destroy();
                    }

                    Input.classList.remove('quiqqer-registration-field-error');
                    return;
                }

                Input.classList.add('quiqqer-registration-field-error');

                if (ErrorElm) {
                    return;
                }

                new Element('span', {
                    'class': 'q-message q-message-error content-message-error ',
                    'data-name': 'error-message',
                    role: 'alert',
                    html: errorMsg
                }).inject(Input, 'after');
            };

            /**
             * Check if the form is valid in its current state
             * and enable or disable form submit button
             */
            const CheckFormValidation = () => {
                // check if submit btn has to be disabled
                const invalidElements = container.querySelectorAll('[data-name="error-message"]');
                let isValid = invalidElements.length;

                // check for CAPTCHA (if used)
                if (this.getAttribute('usecaptcha')) {
                    isValid = isValid && this.$captchaResponse;
                    submitButton.disabled = !this.$captchaResponse;
                }

                return isValid;
            };

            // Email validation
            if (emailInput) {
                emailInput.addEvents({
                    blur: (event) => {
                        if (this.getAttribute('noBlurCheck')) {
                            return;
                        }

                        const value = event.target.value;
                        const checkPromises = [
                            Registration.emailValidation(value)
                        ];

                        if (emailIsUsername) {
                            checkPromises.push(Registration.usernameValidation(value));
                        }

                        Promise.all(checkPromises).then((result) => {
                            let isValid = true;

                            for (let i = 0, len = result.length; i < len; i++) {
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
                    }
                });
            }

            // Username validation
            const UsernameInput = container.querySelector('input[name="username"]');

            if (UsernameInput) {
                /*
                UsernameInput.addEvent('blur', (event) => {
                    Registration.usernameValidation(event.target.value).then((isValid) => {
                        HandleInputValidation(
                            event.target,
                            isValid,
                            QUILocale.get(lg, 'exception.registrars.email.username_already_exists')
                        );

                        CheckFormValidation();
                    });
                });
                */
            }

            // Password validation
            if (passwordInput && passwordStrength) {
                const PassStrengthIndicator = new QUIPwSecurityIndicator().inject(passwordStrength);
                PassStrengthIndicator.bindInput(passwordInput);
            }

            // Captcha handling
            if (!this.getAttribute('usecaptcha')) {
                submitButton.disabled = false;
                return;
            }

            const CaptchaResponseInput = container.querySelector('input[name="captchaResponse"]');

            QUIControlUtils.getControlByElement(
                container.querySelector('.quiqqer-captcha-display')
            ).then((CaptchaDisplay) => {
                CaptchaDisplay.getCaptchaControl().then((CaptchaControl) => {
                    CaptchaControl.addEvents({
                        onSuccess: (response) => {
                            this.$captchaResponse = response;
                            CaptchaResponseInput.value = response;
                            CheckFormValidation();
                        },
                        onExpired: () => {
                            this.$captchaResponse = false;
                            CaptchaResponseInput.value = '';
                            CheckFormValidation();
                        }
                    });
                }, () => {
                    submitButton.disabled = false;
                });
            }, () => {
                submitButton.disabled = false;
            });
        },

        handleRegistration: function () {
            const container = this.getElm();
            const form = container.closest('form');

            const emailInput = container.querySelector('input[name="email"]');
            const submitButton = container.querySelector('button[type="submit"]');
            const passwordInput = container.querySelector('input[name="password"]');

            if (emailInput.value === '') {
                emailInput.focus();
                return;
            }

            this.startLoading();

            Registration.usernameValidation(emailInput.value).then((isValid) => {
                if (isValid) {
                    form.dispatchEvent(
                        new Event('submit', {
                            bubbles: true,
                            cancelable: true
                        })
                    );

                    return;
                }

                submitButton.setAttribute('data-type', 'login');
                submitButton.innerHTML = `
                    <span class="fa fa-envelope"></span>
                    <span>${QUILocale.get(lg, 'registrars.email.btn.login')}</span>
                `;

                if (passwordInput) {
                    if (passwordInput.value !== '') {
                        this.handleLogin();
                        return;
                    }

                    this.stopLoading();
                    passwordInput.focus();
                    return;
                }

                // show password
                if (container.querySelector('[data-name="password-login"]')) {
                    this.$passwordFieldset = container.querySelector('[data-name="password-login"]');
                } else {
                    this.$passwordFieldset = document.createElement('fieldset');
                    this.$passwordFieldset.setAttribute('data-name', 'password-login');
                    this.$passwordFieldset.style.opacity = '0';
                    this.$passwordFieldset.style.position = 'relative';
                    this.$passwordFieldset.style.overflow = 'hidden';
                    this.$passwordFieldset.style.height = '0px';

                    this.$passwordFieldset.innerHTML = `
                        <label>
                            <span class="quiqqer-registration-field-title">
                                Password
                            </span>
                            <input type="text" name="password" class="quiqqer-registration-field-element" />
                        </label>
                        <div class="q-message q-message-warning content-message-attention">
                            ${QUILocale.get(lg, 'registrars.email.message.already.exists')}
                        </div>
                    `;
                }

                const mailFieldSet = emailInput.closest('fieldset');

                if (mailFieldSet.nextSibling) {
                    mailFieldSet.parentNode.insertBefore(this.$passwordFieldset, mailFieldSet.nextSibling);
                } else {
                    mailFieldSet.parentNode.appendChild(this.$passwordFieldset);
                }

                moofx(this.$passwordFieldset).animate({
                    opacity: 1,
                    height: this.$passwordFieldset.scrollHeight
                }, {
                    duration: 250,
                    callback: () => {
                        this.$passwordFieldset.querySelector('input').focus();
                        this.stopLoading();
                    }
                });
            });
        },

        handleLogin: function () {
            const container = this.getElm();
            const emailInput = container.querySelector('input[name="email"]');
            const passwordInput = container.querySelector('input[name="password"]');

            const email = emailInput.value;
            const password = passwordInput.value;

            this.startLoading();

            require(['controls/users/Login'], (loginClass) => {
                const login = new loginClass({
                    authenticators: ['QUI\\Users\\Auth\\QUIQQER'],
                    onSuccess: () => {
                        // placeholder -> login doesn't make a reload
                    },
                    events: {
                        load: (login) => {
                            const loginNode = container.querySelector(
                                '[data-qui="controls/users/auth/QUIQQERLogin"]'
                            );

                            loginNode.querySelector('[name="username"]').value = email;
                            loginNode.querySelector('[name="password"]').value = password;

                            login.auth(
                                loginNode.querySelector('[name="username"]').closest('form')
                            ).then((responseData) => {
                                if (typeof responseData === 'undefined') {
                                    return;
                                }

                                // consider 2FA
                                // show login
                                const loginNode = login.getElm();

                                container.style.position = 'relative';

                                loginNode.style.position = 'absolute';
                                loginNode.style.opacity = 0;
                                loginNode.style.top = 0;
                                loginNode.style.left = 0;
                                loginNode.style.display = '';

                                moofx(loginNode).animate({
                                    opacity: 1
                                });
                            }).catch(() => {
                                this.stopLoading();
                            });
                        }
                    }
                });

                login.inject(container);
                login.getElm().style.display = 'none';
            });
        },

        $hideResetPasswordField: function () {
            if (!this.$passwordFieldset) {
                return;
            }

            const container = this.getElm();
            const submitButton = container.querySelector('button[type="submit"]');
            const pwField = this.$passwordFieldset;

            this.$passwordFieldset = null;

            submitButton.setAttribute('data-type', 'registration');
            submitButton.innerHTML = `
                <span class="fa fa-envelope"></span>
                <span>${QUILocale.get(lg, 'registrars.email.btn.submit')}</span>
            `;

            moofx(pwField).animate({
                opacity: 0,
                height: 0
            }, {
                duration: 200,
                callback: () => {
                    pwField.parentNode.removeChild(pwField);
                }
            });
        },

        //region login

        startLoading: function () {
            const container = this.getElm();

            const emailInput = container.querySelector('input[name="email"]');
            const passwordInput = container.querySelector('input[name="password"]');
            const submitButton = container.querySelector('button[type="submit"]');

            emailInput.disabled = true;

            if (passwordInput) {
                passwordInput.disabled = true;
            }

            const icon = submitButton.querySelector('.fa');
            icon.classList.add('fa-spinner');
            icon.classList.add('fa-spin');
            icon.classList.remove('fa-envelope');
            submitButton.disabled = true;
        },

        stopLoading: function () {
            const container = this.getElm();

            const emailInput = container.querySelector('input[name="email"]');
            const passwordInput = container.querySelector('input[name="password"]');
            const submitButton = container.querySelector('button[type="submit"]');

            emailInput.disabled = false;

            if (passwordInput) {
                passwordInput.disabled = false;
            }

            const icon = submitButton.querySelector('.fa');
            icon.classList.remove('fa-spinner');
            icon.classList.remove('fa-spin');
            icon.classList.add('fa-envelope');
            submitButton.disabled = false;
        }

        //endregion
    });
});