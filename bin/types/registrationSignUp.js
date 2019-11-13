function signUpOnLoad() {
    "use strict";

    var Button = document.getElement('button[name="registration-sign-in-login-button"]');

    if (Button) {
        require([
            'package/quiqqer/frontend-users/bin/frontend/controls/login/Window'
        ], function (Login) {
            Button.addEvent('click', function () {
                new Login({
                    events: {
                        onClose: function () {
                            window.location.hash = '';
                        },

                        onOpen: function () {
                            window.location.hash = '#login';
                        }
                    }
                }).open();
            });

            Button.set('disabled', false);

            if (window.QUIQQER_USER.id) {
                Button.setStyle('display', 'none');
            }

            if (window.location.hash === '#login') {
                Button.click();
            }
        });
    }

    require(['qui/QUI'], function (QUI) {
        var Container        = document.getElement('.registration-sign-in-container');
        var ControlContainer = document.getElement('.registration-sign-in-container-instance');
        var Control          = ControlContainer.getElement(
            '[data-qui="package/quiqqer/frontend-users/bin/frontend/controls/RegistrationSignUp"]'
        );

        var show = function () {
            Container.getElement('.loader').destroy();

            moofx(Container).animate({
                height: Container.getScrollSize().y
            }, {
                duration: 250,
                callback: function () {
                    Container.setStyles({
                        height  : null,
                        overflow: null
                    });
                }
            });
        };

        var instanceLoaded = function (Instance) {
            if (Instance.isLoaded()) {
                return show();
            }

            show();
        };

        if (!Control) {
            return;
        }

        if (Control.get('data-quiid')) {
            instanceLoaded(
                QUI.Controls.getById(Control.get('data-quiid'))
            );
        } else {
            Control.addEvent('load', function () {
                instanceLoaded(
                    QUI.Controls.getById(Control.get('data-quiid'))
                );
            });
        }
    });

    var SignInLinks = document.getElements('.registration-sign-in-links');

    if (SignInLinks) {
        SignInLinks.getElements('a').addEvent('click', function (event) {
            event.stop();

            var Target = event.target;

            if (Target.nodeName !== 'A') {
                Target = Target.getParent('a');
            }

            require([
                'package/quiqqer/controls/bin/site/Window',
                'Locale'
            ], function (QUISiteWindow, QUILocale) {
                var lg     = 'quiqqer/frontend-users',
                    sideId = Target.get('data-id');

                new QUISiteWindow({
                    closeButtonText: QUILocale.get(lg, 'btn.close'),
                    showTitle      : true,
                    project        : QUIQQER_PROJECT.name,
                    lang           : QUIQQER_PROJECT.lang,
                    id             : sideId
                }).open();
            });
        });
    }
}

if (typeof window.whenQuiLoaded !== 'undefined') {
    window.whenQuiLoaded.then(signUpOnLoad);
} else {
    document.addEvent('domready', signUpOnLoad);
}
