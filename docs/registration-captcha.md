# Registration CAPTCHA

`registration.useCaptcha` remains optional. When enabled, the email registrar used
by browser and REST registrations requires the installed `quiqqer/captcha`
package, its handler and display control, and a resolvable default module.
Both registration forms use the same capability check. Missing components produce
a translated configuration error instead of silently hiding the CAPTCHA.

The capability check resolves the module control without rendering a new challenge.
The submitted response must still pass the CAPTCHA handler's validation. Empty,
invalid, and non-string responses cannot create a user or an activation verification.

The `onPackageConfigSave` listener checks availability when CAPTCHA is enabled in
the package settings and reports a configuration error to the administration.
The Core dispatches this event **after** persisting settings: the enabled setting
is retained, so registrations remain blocked until the configuration is repaired
or an administrator explicitly disables CAPTCHA. Direct configuration-file edits
bypass this notification, but never the registration or rendering checks.

Run package setup after deploying to import the event listener and translations.
No required CAPTCHA dependency, PHP signature change, or schema change is introduced.
