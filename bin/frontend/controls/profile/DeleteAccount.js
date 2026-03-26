/**
 * Frontend Profile: Delete account
 *
 * @module package/quiqqer/frontend-users/bin/frontend/controls/profile/DeleteAccount
 */
define('package/quiqqer/frontend-users/bin/frontend/controls/profile/DeleteAccount', [

    'qui/controls/Control',
    'qui/controls/windows/SimpleConfirmWindow',
    'Locale',
    'Ajax',

    'css!package/quiqqer/frontend-users/bin/frontend/controls/profile/DeleteAccount.css'

], function (QUIControl, QUISimpleConfirmWindow, QUILocale, QUIAjax) {
    'use strict';

    var lg = 'quiqqer/frontend-users';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/frontend-users/bin/frontend/controls/profile/DeleteAccount',

        Binds: [
            '$onImport',
            '$checkDeleteAccount'
        ],

        options: {
            username: '',
            deletestarted: 0
        },

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

            var SubmitBtn = Elm.querySelector('[type="submit"]'),
                confirmed = false;

            if (!SubmitBtn) {
                return;
            }

            var self = this;
            var username = this.getAttribute('username');

            if (!username) {
                username = '';
            }

            SubmitBtn.addEvent('click', function (event) {
                if (confirmed || self.getAttribute('deletestarted')) {
                    return;
                }

                event.stop();

                new QUISimpleConfirmWindow({
                    'class': 'quiqqer-frontend-users-delete-account-confirm',
                    maxHeight: 500,
                    maxWidth: 600,
                    autoclose: true,
                    message: false,
                    title: QUILocale.get(lg, 'controls.profile.DeleteAccount.confirm.title'),
                    buttonCancel: {
                        'class': 'btn btn-link-body',
                        order: 1,
                        text: QUILocale.get('quiqqer/system', 'cancel'),
                        icon: false
                    },
                    buttonSubmit: {
                        'class': 'btn btn-danger',
                        order: 2,
                        text: QUILocale.get(lg, 'controls.profile.DeleteAccount.confirm.btn'),
                        icon: 'fa fa-trash'
                    },

                    events: {
                        onOpen: function (Popup) {
                            var Content = Popup.getContent(),
                                Wrapper,
                                Header,
                                SubmitBtn,
                                Information;

                            Content.set('html', '<div class="quiqqer-frontend-users-delete-account-window" data-name="delete-account-window"></div>');

                            Wrapper = Content.querySelector('[data-name="delete-account-window"]');
                            Header = new Element('div', {
                                'class': 'quiqqer-frontend-users-delete-account-window__header',
                                'data-name': 'delete-account-header'
                            }).inject(Wrapper);

                            new Element('span', {
                                'class': 'quiqqer-frontend-users-delete-account-window__icon fa fa-trash',
                                'data-name': 'delete-account-icon'
                            }).inject(Header);

                            new Element('h1', {
                                'class': 'quiqqer-frontend-users-delete-account-window__title',
                                'data-name': 'delete-account-title',
                                html: QUILocale.get(lg, 'controls.profile.DeleteAccount.confirm.text')
                            }).inject(Header);

                            new Element('div', {
                                'class': 'quiqqer-frontend-users-delete-account-window__information',
                                'data-name': 'delete-account-information',
                                html: QUILocale.get(lg, 'controls.profile.DeleteAccount.confirm.information', {
                                    username: username
                                })
                            }).inject(Wrapper);

                            SubmitBtn = Popup.getButton('submit');
                            Information = Content.querySelector('[data-name="delete-account-information"]');

                            if (SubmitBtn && typeof SubmitBtn.disable === 'function') {
                                SubmitBtn.disable();
                            }

                            Popup.Loader.show();

                            self.$checkDeleteAccount().then(function () {
                                if (SubmitBtn && typeof SubmitBtn.enable === 'function') {
                                    SubmitBtn.enable();
                                }

                                Popup.Loader.hide();
                            }, function (Error) {
                                if (Information) {
                                    Information.classList.add('quiqqer-frontend-users-delete-account-window__information--error');
                                    Information.innerHTML = QUILocale.get(
                                        lg,
                                        'controls.profile.DeleteAccount.confirm.information_error',
                                        {
                                            username: username,
                                            error: Error.getMessage()
                                        }
                                    );
                                }

                                Popup.Loader.hide();
                            });
                        },
                        onSubmit: function (Popup) {
                            confirmed = true;
                            Popup.close();
                            SubmitBtn.click();
                        }
                    }
                }).open();
            });
        },

        /**
         * Check if a user account can be deleted
         *
         * @return {Promise}
         */
        $checkDeleteAccount: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_frontend-users_ajax_frontend_profile_checkDeleteAccount', resolve, {
                    'package': 'quiqqer/frontend-users',
                    onError: reject,
                    showError: false
                });
            });
        }
    });
});
