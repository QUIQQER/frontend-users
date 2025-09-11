<?php

/**
 * This file contains QUI\FrontendUsers\Utils
 */

namespace QUI\FrontendUsers;

use QUI;
use QUI\FrontendUsers\Controls\Profile\ControlInterface;
use QUI\FrontendUsers\Exception\EmailAddressNotVerifiableException;
use QUI\Interfaces\Users\User as QUIUserInterface;
use QUI\Permissions;
use QUI\Users\Attribute\AttributeVerificationStatus;
use QUI\Utils\Security\Orthos;

use function class_exists;
use function in_array;
use function is_a;
use function json_decode;

/**
 * Class Utils
 *
 * @package QUI\FrontendUsers
 */
class Utils
{
    /**
     * Return all packages which have a frontend-users.xml
     *
     * @return array
     */
    public static function getFrontendUsersPackages(): array
    {
        $packages = QUI::getPackageManager()->getInstalled();
        $list = [];

        foreach ($packages as $package) {
            try {
                $Package = QUI::getPackage($package['name']);
            } catch (QUI\Exception) {
                continue;
            }

            if (!$Package->isQuiqqerPackage()) {
                continue;
            }

            $dir = $Package->getDir();

            if (file_exists($dir . '/frontend-users.xml')) {
                $list[] = $Package;
            }
        }

        return $list;
    }

    /**
     * Return all extra profile categories
     * - search frontend-users.xml
     *
     * @return array
     */
    public static function getProfileCategories(): array
    {
        $cache = 'package/quiqqer/frontendUsers/profileCategories';

        try {
            return QUI\Cache\Manager::get($cache);
        } catch (QUI\Exception) {
        }

        $result = [];
        $packages = self::getFrontendUsersPackages();
        $Engine = QUI::getTemplateManager()->getEngine();

        foreach ($packages as $Package) {
            $Parser = new QUI\Utils\XML\Settings();
            $Parser->setXMLPath('//quiqqer/frontend-users/profile');

            $Collection = $Parser->getCategories($Package->getDir() . '/frontend-users.xml');

            foreach ($Collection as $entry) {
                $categoryName = $entry['name'];
                $items = $entry['items']->toArray();

                if (!isset($result[$categoryName])) {
                    $result[$categoryName]['name'] = $entry['name'];
                    $result[$categoryName]['title'] = $entry['title'];
                    $result[$categoryName]['items'] = [];
                }

                foreach ($items as $item) {
                    $item['content'] = '';

                    if (
                        empty($item['items'])
                        && empty($item['template'])
                        && empty($item['control'])
                    ) {
                        continue;
                    }

                    // template
                    if (isset($item['template'])) {
                        if (file_exists($item['template'])) {
                            $item['content'] = $Engine->fetch($item['template']);
                        }
                    }

                    // xml
//                    if (isset($item['items'])) {
//
//                    }

                    $result[$categoryName]['items'][] = $item;
                }
            }
        }

        try {
            QUI\Cache\Manager::set($cache, $result);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }


        return $result;
    }

    /**
     * Return a setting for the profile
     *
     * @param string $category
     * @param bool|string $settings
     * @return array
     *
     * @throws Exception
     */
    public static function getProfileSetting(string $category, bool | string $settings = false): array
    {
        if ($category) {
            $categories = [self::getProfileCategory($category)];
        } else {
            $categories = self::getProfileCategories();
        }

        foreach ($categories as $category) {
            foreach ($category['items'] as $settingEntry) {
                if ($settingEntry['name'] == $settings) {
                    return $settingEntry;
                }
            }
        }

        throw new Exception([
            'quiqqer/frontend-users',
            'exception.profile.setting.not.found'
        ]);
    }

    /**
     * Return a setting control for the profile
     *
     * @param string $category
     * @param bool|string $settings
     * @return ControlInterface|null
     *
     * @throws Exception
     */
    public static function getProfileSettingControl(
        string $category,
        bool | string $settings = false
    ): ?ControlInterface {
        $setting = self::getProfileSetting($category, $settings);
        $Control = null;

        if (isset($setting['control'])) {
            $cls = $setting['control'];

            if (class_exists($cls) && is_a($cls, ControlInterface::class, true)) {
                $Control = new $cls();
            }
        }

        return $Control;
    }

    /**
     * Return a specific category
     *
     * @param string $category
     * @return array
     *
     * @throws Exception
     */
    public static function getProfileCategory(string $category): array
    {
        $categories = self::getProfileCategories();

        if (isset($categories[$category])) {
            return $categories[$category];
        }

        throw new Exception([
            'quiqqer/frontend-users',
            'exception.profile.category.not.found'
        ]);
    }

    /**
     * Return all categories and settings for the profile control
     *
     * @return array
     */
    public static function getProfileCategorySettings(): array
    {
        $categories = Utils::getProfileCategories();

        foreach ($categories as $key => $category) {
            $items = $category['items'];

            foreach ($items as $iKey => $setting) {
                if (!isset($setting['showinprofile'])) {
                    continue;
                }

                if (!(int)$setting['showinprofile']) {
                    unset($categories[$key]['items'][$iKey]);
                }
            }

            // reindex
            $categories[$key]['items'] = array_values($categories[$key]['items']);
        }

        // sort
        $sorting = function ($a, $b) {
            $priority1 = $a['priority'] ?? 0;
            $priority2 = $b['priority'] ?? 0;

            return $priority1 > $priority2 ? +1 : -1;
        };

        foreach ($categories as $key => $values) {
            $items = $values['items'];
            usort($items, $sorting);

            $categories[$key]['items'] = $items;
        }

        return $categories;
    }

    /**
     * Return all categories and settings for the profile bar control
     *
     * @return array
     */
    public static function getProfileBarCategorySettings(): array
    {
        $categories = Utils::getProfileCategories();

        foreach ($categories as $key => $category) {
            $items = $category['items'];
            $newItems = [];

            foreach ($items as $iKey => $setting) {
                if (!isset($setting['showinprofilebar'])) {
                    continue;
                }

                if (!(int)$setting['showinprofilebar']) {
                    continue;
                }

                $newItems[$iKey] = $setting;
            }

            // reindex
            $categories[$key]['items'] = array_values($newItems);
        }

        return $categories;
    }

    /**
     * Checks if the given User is allowed to view a category
     *
     * @param string $category - Name of the category
     * @param bool|string $setting (optional) - category settings
     * @param QUI\Interfaces\Users\User|null $User (optional) - If omitted use \QUI::getUserBySession()
     * @return bool
     */
    public static function hasPermissionToViewCategory(
        string $category,
        bool | string $setting = false,
        null | QUI\Interfaces\Users\User $User = null
    ): bool {
        if ($User === null) {
            $User = QUI::getUserBySession();
        }

        $permissionPrefix = 'quiqqer.frontendUsers.profile.view.';
        $permission = $permissionPrefix . $category;

        if ($setting) {
            $permission = $permission . '.' . $setting;
        }

        return Permissions\Permission::hasPermission($permission, $User);
    }

    /**
     * Search title arrays and set the locale translations to it
     *
     * @param array $categories
     * @return array
     */
    public static function loadTranslationForCategories(array $categories = []): array
    {
        // load the translations
        foreach ($categories as $key => $category) {
            $categories[$key]['title'] = QUI::getLocale()->get(
                $category['title'][0],
                $category['title'][1]
            );

            foreach ($category['items'] as $itemKey => $item) {
                if (!is_array($categories[$key]['items'][$itemKey]['title'])) {
                    continue;
                }

                $categories[$key]['items'][$itemKey]['title'] = QUI::getLocale()->get(
                    $categories[$key]['items'][$itemKey]['title'][0],
                    $categories[$key]['items'][$itemKey]['title'][1]
                );
            }
        }

        return $categories;
    }


    /**
     * Search title arrays and set the locale translations to it
     *
     * @param array $categories
     * @param null|QUI\Projects\Project $Project
     * @return array
     */
    public static function setUrlsToCategorySettings(
        array $categories = [],
        null | QUI\Projects\Project $Project = null
    ): array {
        try {
            if ($Project === null) {
                $Project = QUI::getRewrite()->getProject();
            }

            $ids = $Project->getSitesIds([
                'where' => [
                    'type' => 'quiqqer/frontend-users:types/profile'
                ],
                'limit' => 1
            ]);

            if (!isset($ids[0])) {
                $Site = $Project->firstChild();
            } else {
                $Site = $Project->get($ids[0]['id']);
            }

            $url = $Site->getUrlRewritten();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            return [];
        }

        // load the translations
        foreach ($categories as $key => $category) {
            foreach ($category['items'] as $itemKey => $item) {
                $itemUrl = $url . '/' . $category['name'];
                $itemUrl = $itemUrl . '/' . $item['name'];

                $categories[$key]['items'][$itemKey]['url'] = $itemUrl;
            }
        }

        return $categories;
    }

    /**
     * Is quiqqer/captcha installed?
     *
     * @return bool
     */
    public static function isCaptchaModuleInstalled(): bool
    {
        try {
            QUI::getPackage('quiqqer/captcha');
        } catch (\Exception) {
            return false;
        }

        return true;
    }

    /**
     * Get URL for Gravatar Avatar image
     *
     * @param string $email
     * @param int $s [default] - Size [default: 80x80 px]
     * @return string
     */
    public static function getGravatarUrl(string $email, int $s = 80): string
    {
        if ($s < 1) {
            $s = 1;
        } elseif ($s > 2048) {
            $s = 2048;
        }

        $url = 'https://www.gravatar.com/avatar/';
        $url .= md5(mb_strtolower(trim($email)));
        $url .= "?s=$s&d=mm";

        return $url;
    }

    /**
     * Check if the STANDARD e-mail address of a user is verified
     *
     * @param QUIUserInterface $User
     * @return bool
     * @deprecated use isDefaultUserEmailVerified
     */
    public static function isUserEmailVerified(QUIUserInterface $User): bool
    {
        return self::isDefaultUserEmailVerified($User);
    }

    /**
     * Check if the STANDARD e-mail address of a user is verified
     *
     * @param QUIUserInterface $User
     * @return bool
     */
    public static function isDefaultUserEmailVerified(QUIUserInterface $User): bool
    {
        $email = $User->getAttribute('email');

        if (empty($email)) {
            return false;
        }

        return $User->getAttribute(Handler::USER_ATTR_EMAIL_VERIFIED);
    }

    /**
     * Check if any e-mail address (user, user address) is verified for a specific user.
     *
     * @param string $email
     * @param QUIUserInterface $User
     * @return bool
     */
    public static function isEmailAddressVerifiedForUser(string $email, QUIUserInterface $User): bool
    {
        if (method_exists($User, 'isAttributeVerified')) {
            $isVerified = $User->isAttributeVerified(
                $email,
                QUI\Users\Attribute\Verifiable\MailAttribute::class
            );

            if ($isVerified) {
                return true;
            }
        }

        $verifiedEmailAddresses = $User->getAttribute(Handler::USER_ATTR_EMAIL_ADDRESSES_VERIFIED);

        if (empty($verifiedEmailAddresses)) {
            return false;
        }

        return in_array($email, $verifiedEmailAddresses);
    }

    /**
     * Set a specific email address as verified for a user.
     *
     * @param string $email
     * @param QUIUserInterface $User
     * @return void
     *
     * @throws EmailAddressNotVerifiableException
     */
    public static function setEmailAddressAsVerifiedForUser(string $email, QUIUserInterface $User): void
    {
        if (self::isEmailAddressVerifiedForUser($email, $User)) {
            return;
        }

        if (empty($email)) {
            throw new EmailAddressNotVerifiableException('Cannot verify empty email address.');
        }

        if (!QUI\Utils\Security\Orthos::checkMailSyntax($email)) {
            throw new EmailAddressNotVerifiableException("Cannot verify invalid email address $email.");
        }

        if (!self::doesUserHaveEmailAddress($email, $User)) {
            throw new EmailAddressNotVerifiableException(
                "Cannot verify email address $email for user {$User->getId()}, because this email address"
                . " is not associated with this user (neither saved in user or user addresses)."
            );
        }

        $verifiedEmailAddresses = $User->getAttribute(Handler::USER_ATTR_EMAIL_ADDRESSES_VERIFIED);

        if (empty($verifiedEmailAddresses)) {
            $verifiedEmailAddresses = [];
        }

        $verifiedEmailAddresses[] = $email;

        if (method_exists($User, 'setStatusToVerifiableAttribute')) {
            $User->setStatusToVerifiableAttribute(
                $email,
                QUI\Users\Attribute\Verifiable\MailAttribute::class,
                AttributeVerificationStatus::VERIFIED
            );
        }

        $User->setAttribute(Handler::USER_ATTR_EMAIL_ADDRESSES_VERIFIED, $verifiedEmailAddresses);
        $User->save(QUI::getUsers()->getSystemUser());
    }

    /**
     * Check if a user has a specific email address (either in user or one of user addresses).
     *
     * @param string $email
     * @param QUIUserInterface $User
     * @return bool
     */
    public static function doesUserHaveEmailAddress(string $email, QUIUserInterface $User): bool
    {
        $userEmail = $User->getAttribute('email');

        if ($email === $userEmail) {
            return true;
        }

        foreach ($User->getAddressList() as $Address) {
            if (!($Address instanceof QUI\Users\Address)) {
                continue;
            }

            $addressEmails = $Address->getMailList();

            if (in_array($email, $addressEmails)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Set the standard e-mail address of a user to status "verified"
     *
     * @param QUIUserInterface $User
     * @return void
     * @throws EmailAddressNotVerifiableException
     * @deprecated use setDefaultUserEmailVerified
     */
    public static function setUserEmailVerified(QUIUserInterface $User): void
    {
        self::setDefaultUserEmailVerified($User);
    }

    /**
     * Set the standard e-mail address of a user to status "verified"
     *
     * @param QUIUserInterface $User
     * @return void
     * @throws EmailAddressNotVerifiableException
     */
    public static function setDefaultUserEmailVerified(QUIUserInterface $User): void
    {
        self::setEmailAddressAsVerifiedForUser($User->getAttribute('email'), $User);

        $User->setAttribute(Handler::USER_ATTR_EMAIL_VERIFIED, true);
        $User->save(QUI::getUsers()->getSystemUser());
    }

    public static function getMissingAddressFields(QUI\Users\Address $Address): array
    {
        $missing = [];

        try {
            $Conf = QUI::getPackage('quiqqer/frontend-users')->getConfig();
            $settings = $Conf->getValue('profile', 'addressFields');

            if (!empty($settings)) {
                $settings = json_decode($settings, true);
            }
        } catch (QUI\Exception) {
            return $missing;
        }

        if (empty($settings)) {
            return $missing;
        }

        foreach ($settings as $setting => $data) {
            if (empty($data['required'])) {
                continue;
            }

            if ($setting === 'mobile') {
                if ($Address->getMobile() === '') {
                    $missing[] = $setting;
                }

                continue;
            }

            if ($setting === 'fax') {
                if ($Address->getFax() === '') {
                    $missing[] = $setting;
                }

                continue;
            }

            if ($setting === 'tel') {
                if ($Address->getPhone() === '') {
                    $missing[] = $setting;
                }

                continue;
            }

            if (!$Address->getAttribute($setting)) {
                $missing[] = $setting;
            }
        }

        return $missing;
    }

    /**
     * Check if an email address is blacklisted from registration.
     *
     * @param string $email
     * @return bool
     */
    public static function isEmailBlacklisted(string $email): bool
    {
        foreach (self::getBlacklistedEmailPatterns() as $pattern) {
            if (self::doesEmailMatchPattern($email, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $email
     * @param string $pattern
     * @return bool
     */
    private static function doesEmailMatchPattern(string $email, string $pattern): bool
    {
        if (!Orthos::checkMailSyntax($email)) {
            return false;
        }

        $partsPattern = explode('@', $pattern);

        if (empty($partsPattern[1])) {
            return false;
        }

        $patternName = $partsPattern[0];
        $patternNameIsWildcard = $patternName === '*';
        $patternDomain = $partsPattern[1];
        $patternDomainIsWildcard = $patternDomain === '*';

        $partsEmail = explode('@', $email);
        $emailName = $partsEmail[0];
        $emailDomain = $partsEmail[1];

        $nameMatch = $patternNameIsWildcard || $emailName === $patternName;
        $domainMatch = $patternDomainIsWildcard || $emailDomain === $patternDomain;

        return $nameMatch && $domainMatch;
    }

    /**
     * @return array
     */
    private static function getBlacklistedEmailPatterns(): array
    {
        try {
            $Conf = QUI::getPackage('quiqqer/frontend-users')->getConfig();
            $setting = $Conf->get('registration', 'emailBlacklist');
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return [];
        }

        if (empty($setting)) {
            return [];
        }

        return json_decode($setting, true);
    }
}
