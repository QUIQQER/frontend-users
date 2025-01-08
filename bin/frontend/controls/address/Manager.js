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
            '$openContainer',
            '$closeContainer',
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
                    dataName: 'address-container'
                });

                QUI.parse(Form).then(function() {
                    self.$removeUnusedNodes(Form);

                    new QUIConfirm({
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

            const self = this;
            let Target = event.event.target;

            if (!Target.hasAttribute('[data-name="address"]')) {
                Target = Target.getParent('[data-name="address"]');
            }

            Target.style.position = 'relative';

            const Address = Target;

            // open delete dialog
            this.$openContainer(Target).then(function(Container) {
                Container.addClass(
                    'quiqqer-frontend-users-address-container-delete'
                );

                const Content = Container.getElement('.quiqqer-frontend-users-address-container-content');

                new Element('div', {
                    'class': 'quiqqer-frontend-users-address-container-delete-message',
                    html: QUILocale.get(lg, 'dialog.frontend-users.delete.address')
                }).inject(Content);

                new Element('button', {
                    'class': 'quiqqer-frontend-users-address-container-delete-button',
                    html: QUILocale.get('quiqqer/system', 'delete'),
                    events: {
                        click: function(event) {
                            let Target = event.target;

                            if (Target.nodeName !== 'BUTTON') {
                                Target = Target.getParent('button');
                            }

                            Target.disabled = true;
                            Target.setStyle('width', Target.getSize().x);
                            Target.set('html', '<span class="fa fa-spinner fa-spin"></span>');

                            self.Loader.show();

                            self.deleteAddress(
                                Target.getParent('[data-name="address"]').getElement(
                                    '[name="address"]').value
                            ).then(function() {
                                return self.$closeContainer(Container);
                            }).then(function() {
                                Address.setStyles({
                                    overflow: 'hidden',
                                    height: Address.getSize().y
                                });

                                moofx(Address).animate({
                                    height: 0,
                                    opacity: 0
                                }, {
                                    duration: 250,
                                    callback: function() {
                                        self.refresh();
                                    }
                                });
                            }).catch(function() {
                                self.$closeContainer(Container);
                                self.Loader.hide();
                            });
                        }
                    }
                }).inject(Content);
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
                    dataName: 'address-container'
                });

                QUI.parse(Form).then(function() {
                    self.$removeUnusedNodes(Form);

                    new QUIConfirm({
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

        /**
         * Open a div container with effect
         *
         * @return {Promise}
         */
        $openContainer: function(Parent) {
            const self = this;

            const Container = new Element('div', {
                'class': 'quiqqer-frontend-users-address-container',
                html: '<div class="quiqqer-frontend-users-address-container-header" data-name="header"></div>' +
                    '<div class="quiqqer-frontend-users-address-container-content" data-name="content"></div>',
                tabIndex: -1,
                dataName: 'address-container'
            }).inject(Parent);

            new Element('span', {
                'class': 'fa fa-close quiqqer-frontend-users-address-container-close',
                events: {
                    click: function() {
                        self.$closeContainer(Container);
                    }
                }
            }).inject(Container, 'top');

            return new Promise(function(resolve) {
                moofx(Container).animate({
                    left: 0,
                    opacity: 1
                }, {
                    duration: 250,
                    callback: function() {
                        // no scroll animation because after address edit is open
                        // there may be an animation depend on selected option in "businessType" select
                        self.getElm().scrollIntoView();
                        resolve(Container);
                    }
                });
            });
        },

        /**
         * Close a div container with effect
         *
         * @param {HTMLDivElement} Container
         * @return {Promise}
         */
        $closeContainer: function(Container) {
            const self = this;

            return new Promise(function(resolve) {
                moofx(Container).animate({
                    left: -50,
                    opacity: 0
                }, {
                    duration: 250,
                    callback: function() {
                        Container.destroy();
                        self.getElm().setStyle('height', null);

                        if (self.$Profile) {
                            self.$Profile.getElm().setStyle('overflow', null);
                        }

                        self.resize();
                        resolve();
                    }
                });
            });
        },

        $removeUnusedNodes: function(Node) {
            if (Node.querySelector('h2')) {
                Node.querySelector('h2').destroy();
            }

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
