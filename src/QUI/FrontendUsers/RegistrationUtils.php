<?php

namespace QUI\FrontendUsers;

use QUI;
use QUI\Projects\Project;

/**
 * Class RegistrationUtils
 *
 * Helper methods for the registration process
 */
class RegistrationUtils
{
    /**
     * @return list<string>
     */
    public static function parseDefaultGroupIds(string $groupIds): array
    {
        $result = [];

        foreach (explode(',', $groupIds) as $groupId) {
            $groupId = trim($groupId);

            if ($groupId === '') {
                continue;
            }

            $result[] = $groupId;
        }

        return $result;
    }

    /**
     * Get the "further links" that are shown in the account activation success message box
     * if the user is NOT automatically redirected.
     *
     * @param Project|null $Project $Project (optional) - QUIQQER Project [default: QUI::getRewrite()->getProject()]
     * @return string
     */
    public static function getFurtherLinksText(null | Project $Project = null): string
    {
        try {
            if (empty($Project)) {
                $Project = QUI::getRewrite()->getProject();
            }

            $nextLinks = [];

            $StartSite = $Project?->get(1);

            if ($StartSite === null) {
                return '';
            }

            $nextLinks[] = '<a href="' . $StartSite->getUrlRewrittenWithHost() . '">' .
                $StartSite->getAttribute('title') .
                '</a>';

            $ProfileSite = QUI\FrontendUsers\Handler::getInstance()->getProfileSite($Project);

            if ($ProfileSite) {
                $nextLinks[] = '<a href="' . $ProfileSite->getUrlRewrittenWithHost() . '">' .
                    $ProfileSite->getAttribute('title') .
                    '</a>';
            }

            return implode(' | ', $nextLinks);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return '';
        }
    }
}
