<?php

namespace QUI\FrontendUsers\Cleanup;

use QUI\FrontendUsers\Handler as FrontendUsers;

class Cron
{
    /**
     * Delete frontend users matching the configured cron parameters.
     *
     * @param array<string, bool|string> $params
     */
    public static function cleanup(array $params): void
    {
        $ConsoleTool = new Console();

        foreach ($params as $key => $value) {
            if ($key === 'emailVerified') {
                $ConsoleTool->setArgument(
                    'attr-' . FrontendUsers::USER_ATTR_EMAIL_VERIFIED,
                    boolval($value)
                );
                continue;
            }

            $ConsoleTool->setArgument($key, $value);
        }

        $ConsoleTool->setArgument('delete', true);
        $ConsoleTool->execute();
    }
}
