<?php

namespace QUI\FrontendUsers\Controls\Bricks;

use QUI;

use function array_walk;
use function explode;
use function json_decode;
use function json_last_error;
use function str_replace;

use const JSON_ERROR_NONE;

/**
 * Class AuthContent
 *
 * Shows content based on auth status and group assignment.
 */
class AuthContent extends QUI\Control
{
    /**
     * constructor
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        // default options
        $this->setAttributes([
            'class' => 'quiqqer-frontendusers-AuthContent',
            'content_guest' => '',
            'groups' => '',
            'content_not_in_groups' => '',
            'content_in_groups' => ''
        ]);

//        $this->addCSSFile(dirname(__FILE__).'/Author.css');

        parent::__construct($attributes);

        $this->setAttribute('cacheable', 0);
    }

    /**
     * (non-PHPdoc)
     *
     * @see \QUI\Control::create()
     */
    public function getBody(): string
    {
        $lang = QUI::getLocale()->getCurrent();
        $Engine = QUI::getTemplateManager()->getEngine();

        $groups = $this->getAttribute('groups');
        $groupIds = [];

        if (!empty($groups)) {
            $groupIds = explode(',', $groups);

            array_walk($groupIds, function (&$groupId) {
                $groupId = (int)$groupId;
            });
        }

        // User is not authenticated
        $User = QUI::getUserBySession();

        if (!QUI::getUsers()->isAuth($User)) {
            $content = $this->getAttribute('content_guest');
        } else {
            // Check if user is in group(s)
            $isInGroup = false;

            foreach ($groupIds as $groupId) {
                if ($User->isInGroup($groupId)) {
                    $isInGroup = true;
                    break;
                }
            }

            if ($isInGroup) {
                $content = $this->getAttribute('content_in_groups');
            } else {
                $content = $this->getAttribute('content_not_in_groups');
            }
        }

        if (empty($content)) {
            $content = '';
        } else {
            $content = json_decode($content, true);

            if (empty($content) || json_last_error() !== JSON_ERROR_NONE || empty($content[$lang])) {
                $content = '';
            } else {
                $content = $content[$lang];

                $content = str_replace(
                    [
                        '[username]'
                    ],
                    [
                        $User->getName()
                    ],
                    $content
                );
            }
        }

        $Engine->assign('content', $content);

        return $Engine->fetch(dirname(__FILE__) . '/AuthContent.html');
    }
}
