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
     * Get the "further links" that are shown in the account activation success message box
     * if the user is NOT automatically redirected.
     *
     * @param Project $Project (optional) - QUIQQER Project [default: QUI::getRewrite()->getProject()]
     * @return string
     */
    public static function getFurtherLinksText(Project $Project = null)
    {
        try {
            if (empty($Project)) {
                $Project = QUI::getRewrite()->getProject();
            }

            $nextLinks = [];

            $StartSite   = $Project->get(1);
            $nextLinks[] = '<a href="'.$StartSite->getUrlRewrittenWithHost().'">'.$StartSite->getAttribute('title').'</a>';

            $ProfileSite = QUI\FrontendUsers\Handler::getInstance()->getProfileSite($Project);

            if ($ProfileSite) {
                $nextLinks[] = '<a href="'.$ProfileSite->getUrlRewrittenWithHost().'">'.$ProfileSite->getAttribute('title').'</a>';
            }

            return implode(' | ', $nextLinks);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return '';
        }
    }
}
