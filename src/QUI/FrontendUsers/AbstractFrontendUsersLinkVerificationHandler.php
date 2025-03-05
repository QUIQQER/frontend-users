<?php

namespace QUI\FrontendUsers;

use QUI;
use QUI\Projects\Project;
use QUI\Verification\AbstractLinkVerificationHandler;
use QUI\Verification\Entity\LinkVerification;

/**
 * Base class for all link verification handlers of quiqqer/frontend-users.
 */
abstract class AbstractFrontendUsersLinkVerificationHandler extends AbstractLinkVerificationHandler
{
    /**
     * Get the Project this ActivationVerification is intended for
     *
     * @param LinkVerification $verification
     * @return Project|null
     * @throws QUI\Exception
     */
    protected function getProject(LinkVerification $verification): ?Project
    {
        $project = $verification->getCustomDataEntry('project');
        $projectLang = $verification->getCustomDataEntry('projectLang');

        if (empty($project) || empty($projectLang)) {
            return null;
        }

        return QUI::getProjectManager()->getProject($project, $projectLang);
    }
}
