function signUponLoad() {
    var Button = document.getElement('button[name="registration-sign-in-login-button"]');

    require(['controls/users/LoginWindow'], function (Login) {
        Button.addEvent('click', function () {
            new Login().open();
        });

        Button.set('disabled', false);
    });
}

if (typeof window.whenQuiLoaded !== 'undefined') {
    window.whenQuiLoaded.then(signUponLoad);
} else {
    document.addEvent('domready', signUponLoad);
}
