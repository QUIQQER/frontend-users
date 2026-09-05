# Avatar replacement

The profile uploader removes previous managed avatar images only after the new
upload has been activated and the user's avatar has been saved successfully.
This also works when the media project has no placeholder image.

New uploads use the filename namespace `frontend-users-avatar-<owner hash>-<UUID>`.
The owner hash is SHA-256 of the user's UUID. Cleanup requires this namespace,
the system user as media creator, an image in the configured avatar folder, and
an image that is not the project's placeholder. Images owned by another user,
images moved outside the avatar folder, and unmarked legacy images are retained.
Legacy UUID filenames and image titles alone do not establish ownership; this
change does not perform a retrospective purge.

Cleanup candidates consist of the previous avatar and failed deletions recorded
in the internal user attribute `quiqqer.frontendUsers.avatarCleanup`. This list is
saved together with the replacement; cleanup does not sweep other uploads that
may still be in progress. Missing or already deleted entries are discarded on
the next upload. Ownership, folder and placeholder checks also apply to retries.
Cleanup failures are logged and retried on the next successful upload in the same
folder. Upload, activation or user-save failures leave the previous image intact.

Deletion uses the regular QUIQQER media trash lifecycle. Physical reclamation of
trashed originals remains subject to the installation's trash retention and
cleanup configuration. No cumulative upload quota or new setting is introduced.

Run package setup after deployment to refresh the user attribute declaration:

```sh
./console quiqqer:package --setup=quiqqer/frontend-users
```

Targeted regression tests:

```sh
./tools/phpunit --filter AvatarReplacementWorkflowTest
```
