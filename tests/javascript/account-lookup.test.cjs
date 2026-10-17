const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {test} = require('node:test');

const root = path.resolve(__dirname, '../..');
const nextTurn = () => new Promise((resolve) => setImmediate(resolve));
const throttled = {getCode: () => 429};

// Load the production AMD modules without changing their QUIControl method semantics.
function load(file, dependencies = {}, globals = {}) {
    let module;
    vm.runInNewContext(fs.readFileSync(path.join(root, file), 'utf8'), {
        Promise, Event, console,
        Class: function (definition) { return definition; },
        define: (name, names, factory) => {
            module = factory(...names.map((name) => dependencies[name] || {}));
        },
        ...globals
    }, {filename: file});
    return module;
}

function registration(reply) {
    const calls = [];
    const api = load('bin/frontend/classes/Registration.js', {
        Locale: {get: (group, key) => key},
        Ajax: {get: (name, resolve, params) => {
            calls.push({name, params});
            reply(name, resolve, params);
        }}
    });
    return {api, calls};
}

test('availability responses are preserved and errors reject both validation promises', {timeout: 1000}, async () => {
    for (const exists of [false, true]) {
        const {api} = registration((name, resolve) => resolve(exists));
        assert.equal(await api.validateUsername('user'), exists);
        assert.equal(await api.validateEmail('user@example.invalid'), exists);
        assert.equal(await api.usernameValidation('user'), !exists);
        assert.equal(await api.emailValidation('user@example.invalid'), !exists);
    }
    const {api} = registration((name, resolve, params) => params.onError(throttled));
    await assert.rejects(api.usernameValidation('user'), (error) => error === throttled);
    await assert.rejects(api.emailValidation('user@example.invalid'), (error) => error === throttled);
    assert.equal(api.getLookupErrorMessage(throttled), 'exception.account_lookup.throttled');
    assert.equal(api.getLookupErrorMessage(new Error('network')), 'exception.account_lookup.unavailable');
});

test('email registrar still submits new users and switches existing users to login', async () => {
    for (const outcome of ['new', 'existing', 'throttled']) {
        const {api} = registration((name, resolve, params) => {
            if (outcome === 'throttled') { params.onError(throttled); } else { resolve(outcome === 'existing'); }
        });
        const messages = [];
        const definition = load('bin/frontend/controls/registrars/Email.js', {
            'package/quiqqer/frontend-users/bin/Registration': api,
            Locale: {get: (group, key) => key},
            'qui/QUI': {getMessageHandler: (callback) => callback({addAttention: (message) => messages.push(message)})}
        });
        const email = {value: 'user@example.invalid'};
        const password = {value: 'unchanged-secret'};
        const button = {setAttribute: () => {}};
        let submits = 0;
        let logins = 0;
        let loading = false;
        const control = {
            getElm: () => ({
                closest: () => ({dispatchEvent: () => submits++}),
                querySelector: (selector) => ({
                    'input[name="email"]': email,
                    'input[name="password"]': password,
                    'button[type="submit"]': button
                })[selector]
            }),
            startLoading: () => { loading = true; },
            stopLoading: () => { loading = false; },
            handleLogin: () => logins++
        };
        definition.handleRegistration.call(control);
        await nextTurn();
        assert.equal(submits, outcome === 'new' ? 1 : 0);
        assert.equal(logins, outcome === 'existing' ? 1 : 0);
        assert.equal(email.value, 'user@example.invalid');
        assert.equal(password.value, 'unchanged-secret');
        if (outcome === 'throttled') {
            assert.equal(loading, false);
            assert.deepEqual(messages, ['exception.account_lookup.throttled']);
        }
    }
});

test('signup preserves account decisions and stops its loader on lookup failure', {timeout: 1000}, async () => {
    for (const outcome of ['new', 'existing', 'throttled']) {
        const {api} = registration((name, resolve, params) => {
            if (outcome === 'throttled') { params.onError(throttled); } else { resolve(outcome === 'existing'); }
        });
        const messages = [];
        const definition = load('bin/frontend/controls/RegistrationSignUp.js', {
            'package/quiqqer/frontend-users/bin/Registration': api,
            'qui/QUI': {getMessageHandler: (callback) => callback({addAttention: (message) => messages.push(message)})}
        });
        let loading = false;
        let logins = 0;
        const Field = {value: 'user@example.invalid', disabled: true};
        const control = {
            getAttribute: () => true,
            Loader: {show: () => { loading = true; }, hide: () => { loading = false; }},
            $showLoginControl: () => { logins++; return Promise.resolve(false); }
        };
        const result = definition.emailValidation.call(control, Field);
        if (outcome === 'throttled') {
            await assert.rejects(result, (error) => error === throttled);
            assert.deepEqual(messages, ['exception.account_lookup.throttled']);
        } else {
            assert.equal(await result, outcome === 'new');
        }
        assert.equal(logins, outcome === 'existing' ? 1 : 0);
        assert.equal(loading, false);
        assert.equal(Field.value, 'user@example.invalid');
        assert.equal(Field.disabled, true);
    }
});

test('signup restores the email step buttons after a throttled request', async () => {
    const {api} = registration((name, resolve, params) => {
        if (name.endsWith('emailBlacklisted')) { resolve(false); } else { params.onError(throttled); }
    });
    const definition = load('bin/frontend/controls/RegistrationSignUp.js', {
        'package/quiqqer/frontend-users/bin/Registration': api,
        'qui/QUI': {getMessageHandler: (callback) => callback({addAttention: () => {}})}
    });
    const email = {value: 'user@example.invalid', disabled: false, set(key, value) { this[key] = value; }};
    const button = {disabled: false, set(key, value) { this[key] = value; }};
    const element = {getElement: (selector) => selector.includes('email-next') ? button : email};
    const control = {
        getElm: () => element, getAttribute: () => true,
        Loader: {show: () => {}, hide: () => {}}, hideTerms: () => {}, fireEvent: () => {},
        emailValidation: definition.emailValidation
    };
    definition.$onMailCreateClick.call(control);
    await nextTurn();
    assert.equal(email.disabled, false);
    assert.equal(button.disabled, false);
    assert.equal(email.value, 'user@example.invalid');
});

test('activation lookup failure stops the loader and renders a retry message as text', async () => {
    const {api} = registration(() => {});
    let rendered;
    let loading = false;
    const message = {set: () => {}, replaceChildren: (node) => { rendered = node; }};
    const definition = load('bin/frontend/controls/auth/FrontendLogin.js', {
        'package/quiqqer/frontend-users/bin/Registration': api
    }, {document: {createElement: () => ({setAttribute: () => {}})}});
    const control = {
        $Elm: {getElement: () => message},
        getElm: () => ({querySelector: () => message}),
        Loader: {show: () => { loading = true; }, hide: () => { loading = false; }},
        $existsUnverifiedActivationVerification: () => Promise.reject(throttled)
    };
    await definition.$checkForUnverifiedActivationVerification.call(control, 'user-id');
    assert.equal(loading, false);
    assert.equal(rendered.textContent, 'exception.account_lookup.throttled');
});

test('resend button becomes usable after a network error', async () => {
    const definition = load('bin/frontend/controls/auth/ResendActivationLinkBtn.js', {
        Locale: {get: (group, key) => key}
    });
    let disabled = false;
    let event;
    const control = {
        disable: () => { disabled = true; }, enable: () => { disabled = false; },
        setAttribute: () => {}, fireEvent: (name) => { event = name; },
        $resendActivationMail: () => Promise.reject(new Error('network'))
    };
    await definition.$onClick.call(control);
    assert.equal(disabled, false);
    assert.equal(event, 'resendFail');
});
