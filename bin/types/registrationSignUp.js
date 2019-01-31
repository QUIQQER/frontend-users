function signUpOnLoad() {
    var Button = document.getElement('button[name="registration-sign-in-login-button"]');

    require(['controls/users/LoginWindow'], function (Login) {
        Button.addEvent('click', function () {
            new Login().open();
        });

        Button.set('disabled', false);
    });

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
}

if (typeof window.whenQuiLoaded !== 'undefined') {
    window.whenQuiLoaded.then(signUpOnLoad);
} else {
    document.addEvent('domready', signUpOnLoad);
}
