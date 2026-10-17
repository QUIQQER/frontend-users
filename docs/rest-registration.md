# REST registration

The optional `quiqqer/rest` package provides `POST /frontend-users/register` and
`GET /frontend-users/register/required-fields`. Paths are relative to the
installation's REST base path. Request and response schemas are in [openapi.json](openapi.json).

REST registration uses the email registrar. It must be enabled in the Frontend
Users settings, even when the REST caller is authenticated. The same registration
policy applies to browser and REST requests:

- Required terms of use: send `termsOfUseAccepted: true` after the user accepts them.
- Enabled CAPTCHA: send `captchaResponse` from the configured CAPTCHA provider.
  A missing provider does not permit registration.
- Email blacklist and configured user/address requirements apply before user creation.

The required-fields endpoint includes the terms and CAPTCHA fields when configured;
their `max_length` is `null`. Existing user fields retain their response format.

| Email registrar activation mode | Result |
| --- | --- |
| `manual` | Inactive account awaiting administrator approval; no activation link. |
| `mail` | Inactive account with an activation email. |
| `auto` | Active account without an activation email. |
| `autoWithEmailConfirm` | Active account with a separate email verification. |

Successful registration still returns HTTP 200 with `{"message":"OK"}`. It does
not imply that the account is active. Policy violations return HTTP 400 in the
existing `message` response format. Pending accounts carry the same registration
project, registrar and activation-required attributes as browser registrations,
so the existing inactive-account cleanup applies.

## Authentication and scopes

`quiqqer/rest` alone does not require authentication for these routes. When
`quiqqer/oauth-server` is installed and active, its middleware enforces the
configured protection for `/frontend-users/register` and
`/frontend-users/register/required-fields`. Protected routes require an access
token and the corresponding scope; a scope explicitly configured as public is
accessible without that check.

Configure these scopes for the intended clients in each deployment. An OAuth
token grants access to the route; it does not bypass the registration policy.
Registration does not accept client-supplied groups or privilege attributes.
