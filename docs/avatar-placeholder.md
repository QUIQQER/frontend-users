# Default avatar image

The existing default avatar setting under **Settings → Users**
(`profileBar.defaultAvatar`) also supplies the fallback image for
`$User->getAvatar()` through `onUserGetAvatar`.

The handler runs at priority `1000`, after avatar providers using the normal
priority. A valid user avatar is preserved. For an absent, invalid or missing
user image, the configured default media image is returned. An empty setting,
a CSS icon, or an image that cannot be loaded leaves Core's general project
placeholder fallback in place. Core may return `null` if that image is also
unavailable. The handler does not change or save the user's avatar attribute.

CSS icons and username initials remain presentation options of the user bar.
The profile editor's avatar and Gravatar presentation are unchanged. This hook
applies to callers of `QUI\Users\User::getAvatar()`; user classes that override
that method without firing the event keep their own behavior.

Run package setup after deployment to register the event:

```sh
./console quiqqer:package --setup=quiqqer/frontend-users
```

Targeted regression tests:

```sh
./tools/phpunit --filter AvatarPlaceholderTest
```
