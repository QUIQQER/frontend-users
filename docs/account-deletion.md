# Account deletion

Account deletion requires two steps. The existing email link verifies the mail
step and opens `user/deleteaccount` on the project's profile page. It never
deactivates, anonymizes or deletes a user, fires the deletion event, or logs out
the visitor. This also applies to links issued before this change.

The verification package records the mail step as verified when the link is
opened. A mail scanner can complete that step, but cannot authorize deletion.
Opening an already verified, unexpired link leads back to the profile. The user
can also open account deletion directly in their profile after signing in.

The final confirmation requires all of the following:

- A POST with the current session's Core `_csrf` token.
- The authenticated session of the account named in the stored verification.
- A complete Core sign-in within the last ten minutes, including configured
  frontend MFA, as described in [profile-security.md](profile-security.md).
- The current, verified, unexpired account deletion request and the existing
  `quiqqerFrontendUsersDeleteAccountCheck` event allowing deletion.

The final step reloads the stored request and consumes it once before executing
the configured `delete`, `wipe` or `destroy` mode. Existing Core deletion checks
remain in effect. Cancellation or resending invalidates the old request. If an
error occurs after the request is consumed, a new deletion request is necessary;
external event side effects are not rolled back.

The control renders a shared final confirmation view with a cancel button for
both template variants, independently of existing custom deletion templates.
The supplied control submits this final step as a native form POST,
because successful deletion logs out the session. It displays the existing
deletion success message after completion. Earlier request/resend/cancel flows
keep their existing behavior.

No public method signature, link format, dependency or database schema changes
are required. Custom profile forms retain the existing requirement to include
the enclosing profile form's `_csrf` field. The link handler's `onSuccess()` now
only completes the non-destructive mail step; it no longer deletes accounts.
Run package setup to import the new German and English instructions. Customized
mail text should explain the additional confirmation in the profile.
