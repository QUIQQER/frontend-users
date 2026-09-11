/**
 * Manage fields that are shown for address input on registration
 *
 * @module package/quiqqer/frontend-users/bin/controls/settings/AddressFields
 */
define('package/quiqqer/frontend-users/bin/controls/settings/AddressFields', [

    'qui/QUI',
    'qui/controls/Control',

    'Locale',
    'Ajax',
    'Mustache',

    'text!package/quiqqer/frontend-users/bin/controls/settings/AddressFields.html',
    'css!package/quiqqer/frontend-users/bin/controls/settings/AddressFields.css'

], function (QUI, QUIControl, QUILocale, QUIAjax,
             Mustache, template) {
    "use strict";

    var lg = 'quiqqer/frontend-users';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/frontend-users/bin/controls/settings/AddressFields',

        Binds: [
            '$onImport',
            '$setSettings'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Input = null;
            this.$Settings = {};

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

            var lgPrefix = 'controls.settings.addressfields.template.';

            this.$Content = new Element('div', {
                'class': 'quiqqer-frontendusers-settings-addressfields',
                html: Mustache.render(template, {
                    field: QUILocale.get(lg, lgPrefix + 'field'),
                    fieldShow: QUILocale.get(lg, lgPrefix + 'fieldShow'),
                    fieldRequired: QUILocale.get(lg, lgPrefix + 'fieldRequired'),
                    labelSalutation: QUILocale.get('quiqqer/core', 'salutation'),
                    labelFirstName: QUILocale.get('quiqqer/core', 'firstname'),
                    labelLastName: QUILocale.get('quiqqer/core', 'lastname'),
                    labelStreet: QUILocale.get('quiqqer/core', 'street'),
                    labelZip: QUILocale.get('quiqqer/core', 'zip'),
                    labelCity: QUILocale.get('quiqqer/core', 'city'),
                    labelCountry: QUILocale.get('quiqqer/core', 'country'),
                    labelCompany: QUILocale.get('quiqqer/core', 'company'),
                    labelPhone: QUILocale.get('quiqqer/core', 'tel'),
                    labelMobile: QUILocale.get('quiqqer/core', 'mobile'),
                    labelFax: QUILocale.get('quiqqer/core', 'fax'),
                    labelEmail: QUILocale.get(lg, 'email')
                })
            }).inject(this.$Input, 'after');

            if (this.$Input.value.length) {
                this.$Settings = JSON.decode(this.$Input.value);
                this.$setSettings();
            }

            this.$Content.getElements('input').addEvent('change', function (event) {
                var n = event.target.get('name');
                var c = event.target.get('class');
                var status = event.target.checked;

                if (!(n in self.$Settings)) {
                    self.$Settings[n] = {
                        show: false,
                        required: false
                    };
                }

                switch (c) {
                    case 'quiqqer-frontendusers-settings-addressfields-show':
                        self.$Settings[n].show = status;
                        break;

                    case 'quiqqer-frontendusers-settings-addressfields-required':
                        self.$Settings[n].required = status;
                        break;
                }

                if (!self.$Settings[n].show && self.$Settings[n].required) {
                    if (c === 'quiqqer-frontendusers-settings-addressfields-required') {
                        self.$Content.getElement(
                            'input[name="' + n + '"].quiqqer-frontendusers-settings-addressfields-show'
                        ).checked = true;

                        self.$Settings[n].show = true;
                    } else {
                        self.$Content.getElement(
                            'input[name="' + n + '"].quiqqer-frontendusers-settings-addressfields-required'
                        ).checked = false;

                        self.$Settings[n].required = false;
                    }
                }

                self.$Input.value = JSON.encode(self.$Settings);
            });
        },

        /**
         * Set settings to input
         */
        $setSettings: function () {
            for (var field in this.$Settings) {
                if (!this.$Settings.hasOwnProperty(field)) {
                    continue;
                }

                var ShowInput = this.$Content.getElement(
                    'input[name="' + field + '"].quiqqer-frontendusers-settings-addressfields-show'
                );

                var RequiredInput = this.$Content.getElement(
                    'input[name="' + field + '"].quiqqer-frontendusers-settings-addressfields-required'
                );

                if (!ShowInput || !RequiredInput) {
                    continue;
                }

                ShowInput.checked = this.$Settings[field].show;
                RequiredInput.checked = this.$Settings[field].required;
            }
        }
    });
});