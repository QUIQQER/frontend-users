/**
 * Manage settings for all Registrars
 *
 * @module package/quiqqer/frontend-users/bin/controls/settings/SignUpRegistrars
 */
define('package/quiqqer/frontend-users/bin/controls/settings/SignUpRegistrars', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/loader/Loader',

    'Locale',
    'Ajax',

    'css!package/quiqqer/frontend-users/bin/controls/settings/SignUpRegistrars.css'

], function (QUI, QUIControl, QUILoader, QUILocale, QUIAjax) {
    "use strict";

    var lg = 'quiqqer/frontend-users';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/frontend-users/bin/controls/settings/SignUpRegistrars',

        Binds: [
            '$onImport',
            '$onChange'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Input = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            var self = this;

            this.$Input = this.getElm();
            this.$Input.type = 'hidden';

            this.$Elm = new Element('div', {
                'class': 'field-container-field'
            }).wraps(this.$Input);

            var Parent = this.$Elm.getParent('label.field-container');

            if (Parent) {
                var NewParent = new Element('div.field-container');
                NewParent.replaces(Parent);

                this.$Input.inject(NewParent);
                Parent.getElements('.field-container-item').inject(NewParent);
                this.$Elm = NewParent;
            }

            var Container = new Element('div.field-container-field').inject(this.$Elm);

            var values = [];

            try {
                values = JSON.decode(this.$Input.value);
            } catch (e) {
                values = [];
            }

            if (!values) {
                values = [];
            }

            this.$getRegistrars().then(function (registrars) {
                var i, len, Label, Checkbox;

                for (i = 0, len = registrars.length; i < len; i++) {
                    Label = new Element('label', {
                        'class': 'quiqqer-frontendUsers-signUp-settings-label'
                    }).inject(Container);

                    Checkbox = new Element('input', {
                        type: 'checkbox',
                        value: registrars[i].type,
                        events: {
                            change: self.$onChange
                        }
                    }).inject(Label);

                    if (values.indexOf(registrars[i].type) !== -1) {
                        Checkbox.checked = true;
                    }

                    new Element('span', {
                        html: registrars[i].title
                    }).inject(Label);
                }
            });
        },

        /**
         * Get list of all registrars
         *
         * @return {Promise}
         */
        $getRegistrars: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_frontend-users_ajax_settings_getRegistrars', resolve, {
                    'package': 'quiqqer/frontend-users',
                    onError: reject
                });
            });
        },

        /**
         * event: on checkbox change
         */
        $onChange: function () {
            var lists = this.getElm().getElements('input[type="checkbox"]');
            var values = lists.filter(function (Item) {
                return Item.checked;
            }).map(function (Item) {
                return Item.value;
            });

            this.$Input.value = JSON.encode(values);
        }
    });
});
