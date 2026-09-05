# Activation error redirects

Activation error redirects contain the error code and registrar hash. They never
look up or append the account's email address, including for expired, already used,
invalid-code and invalid-request links. Both the sign-up page and the fallback
registration page use this behavior.

The sign-up page for an expired activation link provides an email input for the
existing resend action. The visitor enters the address again; the action becomes
available when the address is syntactically valid. Existing resend limits and
generic responses still apply. Expired verification records are not renewed by
this change; the resend policy is described in [activation-resend.md](activation-resend.md).

Existing explicit email-prefill URLs remain supported. Activation links, error
codes, registrar context, PHP method signatures and settings remain unchanged.
Custom sign-up templates should include the resend email input and the `data-name`
attributes from the package template to support the updated resend control.
