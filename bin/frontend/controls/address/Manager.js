/**
 * @module package/quiqqer/frontend-users/bin/frontend/controls/address/Manager
 */
define('package/quiqqer/frontend-users/bin/frontend/controls/address/Manager', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/loader/Loader',
    'qui/controls/windows/Confirm',
    'Locale',
    'Ajax'

], function(QUI, QUIControl, QUILoader, QUIConfirm, QUILocale, QUIAjax) {
    'use strict';

    const lg = 'quiqqer/frontend-users';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/frontend-users/bin/frontend/controls/address/Manager',

        Binds: [
            '$onImport',
            '$editClick',
            '$deleteClick',
            '$addClick',
            '$clickCreateSubmit',
            '$clickEditSave'
        ],

        initialize: function(options) {
            this.parent(options);

            this.Loader = new QUILoader();
            this.$Profile = null;

            this.$imported = false;
            this.$injected = false;

            this.addEvents({
                onImport: this.$onImport,
                onInject: this.$onInject
            });
        },

        /**
         * event: on import
         */
        $onImport: function() {
            if (this.$imported) {
                return;
            }

            this.$imported = true;
            this.getElm().set('data-quiid', this.getId());

            this.getElm().getElements('[name="create"]').addEvent('click', this.$addClick);
            this.getElm().getElements('[name="delete"]').addEvent('click', this.$deleteClick);
            this.getElm().getElements('[name="edit"]').addEvent('click', this.$editClick);

            this.Loader.inject(this.getElm());

            moofx(this.getElm()).animate({
                opacity: 1
            }, {
                duration: 200
            });

            // profile
            const Parent = this.getElm().getParent('.quiqqer-frontendUsers-controls-profile');

            if (Parent && Parent.get('data-quiid')) {
                this.$Profile = QUI.Controls.getById(Parent.get('data-quiid'));
            }

            this.resize();
        },

        /**
         * event: on inject
         */
        $onInject: function() {
            if (this.$imported || this.$injected) {
                return;
            }

            this.getElm().setStyle('opacity', 0);
            this.refresh();
        },

        /**
         * resize the control
         */
        resize: function() {
            if (!this.$Profile) {
                return;
            }

            const Container = this.getElm().getElement('.quiqqer-frontend-users-address-container');

            if (!Container) {
                this.$Profile.resize();
                return;
            }

            const Content = this.getElm().getElement('.quiqqer-frontend-users-address-container-content');

            Container.setStyle('overflow', 'hidden');
            Container.setStyle('marginBottom', 20);
            Content.setStyle('height', 'initial');

            this.$Profile.getElm().setStyle('overflow', 'hidden');

            moofx(this.getElm()).animate({
                height: Container.getScrollSize().y
            }, {
                duration: 200,
                callback: () => {
                    this.getElm().style.height = null;
                    this.$Profile.resize();
                }
            });
        },

        /**
         * Refresh the display
         */
        refresh: function() {
            const self = this;

            this.Loader.show();

            this.$injected = false;
            this.$imported = false;

            return new Promise(function(resolve, reject) {
                QUIAjax.get('package_quiqqer_frontend-users_ajax_frontend_profile_address_control', function(control) {
                    self.getElm().set('html', control);
                    self.resize();
                    self.$onImport();
                    self.Loader.hide();

                    resolve();
                }, {
                    'package': 'quiqqer/frontend-users',
                    onError: reject
                });
            });
        },

        //region add

        /**
         * event click - create address
         *
         * @param event
         */
        $addClick: function(event) {
            event.stop();

            const self = this;

            self.getCreateTemplate().then(function(result) {
                const Form = new Element('form', {
                    'class': 'quiqqer-frontendUsers-controls-profile-control default-content',
                    html: result,
                    'data-name': 'address-container'
                });

                QUI.parse(Form).then(function() {
                    self.$removeUnusedNodes(Form);

                    new QUIConfirm({
                        'class' : 'qui-window-popup--frontendUsers-profile qui-window-popup--frontendUsers-profile-address-add',
                        maxHeight: 800,
                        maxWidth: 700,
                        autoclose: false,
                        backgroundClosable: false,

                        title: QUILocale.get(lg, 'dialog.frontend-users.title'),
                        icon: 'fa fa-address-card-o',

                        ok_button: {
                            text: QUILocale.get(lg, 'dialog.frontend-users.create.address.btn')
                        },
                        cancel_button: {
                            text: QUILocale.get(lg, 'dialog.frontend-users.btn.cancel')
                        },

                        events: {
                            onOpen: function(Popup) {
                                const Content = Popup.getContent();
                                Content.innerHTML = '';
                                Form.inject(Content);
                            },
                            onSubmit: function(Popup) {
                                Popup.Loader.show();

                                self.$clickCreateSubmit(Popup).then(function() {
                                    Popup.close();
                                    self.refresh();
                                }).catch(() => {
                                    Popup.Loader.hide();
                                });
                            }
                        }
                    }).open();
                });
            });
        },

        /**
         * click event - address creation
         *
         * @param {QUIConfirm} Popup
         */
        $clickCreateSubmit: function(Popup) {
            const Content = Popup.getContent(),
                Form = Content.getElement('form');

            return new Promise(function(resolve, reject) {
                require(['qui/utils/Form'], (FormUtils) => {
                    const formData = FormUtils.getFormData(Form);
                    const requiredFields = Form.getElements('[required]');

                    for (let i = 0, len = requiredFields.length; i < len; i++) {
                        if ('reportValidity' in requiredFields[i]) {
                            requiredFields[i].reportValidity();

                            if ('checkValidity' in requiredFields[i]) {
                                if (requiredFields[i].checkValidity() === false) {
                                    reject();
                                    return;
                                }
                            }
                        }
                    }

                    QUIAjax.post('package_quiqqer_frontend-users_ajax_frontend_profile_address_create', resolve, {
                        'package': 'quiqqer/frontend-users',
                        data: JSON.encode(formData),
                        onError: (err) => {
                            QUI.getMessageHandler().then(function(MH) {
                                MH.addError(err.getMessage());
                            });

                            reject();
                        }
                    });
                });
            });
        },

        /**
         * Return the address create template
         *
         * @return {Promise}
         */
        getCreateTemplate: function() {
            return new Promise(function(resolve, reject) {
                QUIAjax.get('package_quiqqer_frontend-users_ajax_frontend_profile_address_getCreate', resolve, {
                    'package': 'quiqqer/frontend-users',
                    onError: reject
                });
            });
        },

        //region

        //region delete

        /**
         *
         * @param event
         */
        $deleteClick: function(event) {
            event.stop();

            let Target = event.target;

            if (Target.nodeName !== 'BUTTON') {
                Target = Target.getParent('button');
            }

            const addressId = Target.getParent('[data-name="address"]').querySelector('[name="address"]').value;
            const self = this;

            self.getDeleteTemplate(addressId).then(function(result) {

                const Form = new Element('form', {
                    'class': 'quiqqer-frontendUsers-controls-profile-control default-content',
                    html: result,
                    'data-name': 'address-container'
                });

                QUI.parse(Form).then(function() {
                    self.$removeUnusedNodes(Form);

                    new QUIConfirm({
                        'class' : 'qui-window-popup--frontendUsers-profile qui-window-popup--frontendUsers-profile-address-delete',
                        maxHeight: 450,
                        maxWidth: 500,
                        autoclose: false,
                        backgroundClosable: false,

                        title: QUILocale.get(lg, 'dialog.frontend-users.address.delete.title'),
                        icon: 'fa fa-address-card-o',

                        ok_button: {
                            text: QUILocale.get(lg, 'dialog.frontend-users.address.delete.btn')
                        },
                        cancel_button: {
                            text: QUILocale.get(lg, 'dialog.frontend-users.btn.cancel')
                        },

                        events: {
                            onOpen: function(Popup) {
                                const Content = Popup.getContent();
                                Content.innerHTML = '';
                                Form.inject(Content);

                                const ConfirmBtn = Popup.getElm().querySelector('[name="submit"]');

                                ConfirmBtn.classList.remove('btn-success');
                                ConfirmBtn.classList.add('btn-danger');
                            },
                            onSubmit: function(Popup) {
                                Popup.Loader.show();

                                self.deleteAddress(addressId).then(function() {
                                    Popup.close();
                                    self.refresh();
                                }).catch(() => {
                                    Popup.Loader.hide();
                                });
                            }
                        }
                    }).open();
                });
            });
        },

        /**
         * Return the address create template
         *
         * @return {Promise}
         */
        getDeleteTemplate: function(addressId) {
            return new Promise(function(resolve, reject) {
                QUIAjax.get('package_quiqqer_frontend-users_ajax_frontend_profile_address_getDelete', resolve, {
                    'package': 'quiqqer/frontend-users',
                    onError: reject,
                    addressId: addressId
                });
            });
        },

        /**
         * Delete an address
         *
         * @param {Integer} addressId
         */
        deleteAddress: function(addressId) {
            return new Promise(function(resolve, reject) {
                QUIAjax.post('package_quiqqer_frontend-users_ajax_frontend_profile_address_delete', resolve, {
                    'package': 'quiqqer/frontend-users',
                    addressId: addressId,
                    onError: reject
                });
            });
        },

        //endregion

        //region edit

        /**
         *
         * @param event
         */
        $editClick: function(event) {
            event.stop();

            let Target = event.target;

            if (Target.nodeName !== 'BUTTON') {
                Target = Target.getParent('button');
            }

            const addressId = Target.getParent('[data-name="address"]').querySelector('[name="address"]').value;
            const self = this;

            self.getEditTemplate(addressId).then(function(result) {
                const Form = new Element('form', {
                    'class': 'quiqqer-frontendUsers-controls-profile-control default-content',
                    html: result,
                    'data-name': 'address-container'
                });

                QUI.parse(Form).then(function() {
                    self.$removeUnusedNodes(Form);

                    new QUIConfirm({
                        'class' : 'qui-window-popup--frontendUsers-profile qui-window-popup--frontendUsers-profile-address-edit',
                        maxHeight: 800,
                        maxWidth: 700,
                        autoclose: false,
                        backgroundClosable: false,

                        title: QUILocale.get(lg, 'dialog.frontend-users.edit.title'),
                        icon: 'fa fa-address-card-o',

                        ok_button: {
                            text: QUILocale.get(lg, 'dialog.frontend-users.edit.address.btn')
                        },
                        cancel_button: {
                            text: QUILocale.get(lg, 'dialog.frontend-users.btn.cancel')
                        },

                        events: {
                            onOpen: function(Popup) {
                                const Content = Popup.getContent();
                                Content.innerHTML = '';
                                Form.inject(Content);
                            },
                            onSubmit: function(Popup) {
                                Popup.Loader.show();

                                self.$clickEditSave(Popup).then(function() {
                                    Popup.close();
                                    self.refresh();
                                }).catch(() => {
                                    Popup.Loader.hide();
                                });
                            }
                        }
                    }).open();
                });
            });
        },

        /**
         * event : click -> save the address edit
         *
         * {QUIConfirm} Popup
         */
        $clickEditSave: function(Popup) {
            const self = this,
                Content = Popup.getContent(),
                Form = Content.getElement('form');

            return new Promise(function(resolve, reject) {
                require(['qui/utils/Form'], (FormUtils) => {
                    const formData = FormUtils.getFormData(Form);

                    if (self.$hasValidityIssues(Form)) {
                        reject();
                        return;
                    }

                    QUIAjax.post('package_quiqqer_frontend-users_ajax_frontend_profile_address_edit', resolve, {
                        'package': 'quiqqer/frontend-users',
                        data: JSON.encode(formData),
                        addressId: formData.addressId,
                        onError: (err) => {
                            QUI.getMessageHandler().then((MH) => {
                                MH.addError(err.getMessage());
                            });

                            reject();
                        }
                    });
                });
            });
        },

        /**
         * Return the address create template
         *
         * @return {Promise}
         */
        getEditTemplate: function(addressId) {
            return new Promise(function(resolve, reject) {
                QUIAjax.get('package_quiqqer_frontend-users_ajax_frontend_profile_address_getEdit', resolve, {
                    'package': 'quiqqer/frontend-users',
                    onError: reject,
                    addressId: addressId
                });
            });
        },

        //endregion

        $removeUnusedNodes: function(Node) {
            if (Node.querySelector('button')) {
                Node.querySelector('button').destroy();
            }
        },

        $hasValidityIssues: function(Form) {
            const requiredFields = Form.getElements('[required]');
            let i = 0,
                len = requiredFields.length;

            for (i; i < len; i++) {
                if ('reportValidity' in requiredFields[i]) {
                    requiredFields[i].reportValidity();

                    if ('checkValidity' in requiredFields[i]) {
                        if (requiredFields[i].checkValidity() === false) {
                            return true;
                        }
                    }
                }
            }

            return false;
        }
    });
});
