<?php

namespace QUI\FrontendUsers\Console;

use DateInterval;
use DateTime;
use Doctrine\DBAL\ArrayParameterType;
use QUI;
use QUI\Exception;

use function date;
use function date_create;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function json_decode;
use function json_encode;
use function preg_split;
use function trim;
use function unlink;

/**
 * Console tool to send an e-mail to all (or a subset of) users in the system
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class SendUserMails extends QUI\System\Console\Tool
{
    /**
     * Mail settings
     *
     * @var array{body: string, senderMail: string, senderName: string, subject: string}
     */
    protected array $mail = [
        'body' => '',
        'senderMail' => '',
        'senderName' => '',
        'subject' => ''
    ];

    /**
     * @var list<array{id: int|string, username: string, email: string, firstname?: string, lastname?: string}>
     */
    protected array $recipients = [];

    /**
     * General settings
     *
     * @var array{setNewPassword: bool, forcePasswordReset: bool}
     */
    protected array $settings = [
        'setNewPassword' => false,
        'forcePasswordReset' => true
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setName('frontend-users:sendUserMails')
            ->setDescription(
                "Send an e-mail to all (or subset of) users in the system"
            );

        $this->addArgument(
            'bodyfile',
            'File that contains the e-mail body (plaintext or html)'
        );
    }

    /**
     * Execute the console tool
     * @throws Exception
     * @throws QUI\Database\Exception
     */
    public function execute(): void
    {
        QUI\Permissions\Permission::isAdmin();

        $bodyFile = $this->getArgument('bodyfile');

        if (!file_exists($bodyFile) || !is_readable($bodyFile)) {
            $this->exitFail("Body file $bodyFile was not found or is not readable by PHP.");
        }

        $body = file_get_contents($bodyFile);

        if ($body === false) {
            $this->exitFail("Body file $bodyFile could not be read.");
        }

        // Determine users the email is being sent to

        // LOCALE
        $this->writeLn("System LOCALE language? [en]: ");
        $lang = $this->readInput();

        QUI::getLocale()->setCurrent($lang);

        // INCLUDE INACTIVE USERS?
        $this->writeLn("Send mail to INACTIVE users? (y/N): ");
        $inactiveUsers = mb_strtolower($this->readInput()) === 'y';

        // USER LANGUAGE
        $this->writeLn("Languages of the users? (comma separated language abbreviations) [en]: ");
        $languages = $this->readInput();

        if (!empty($languages)) {
            $languages = explode(',', $languages);
        } else {
            $languages = ['en'];
        }

        // RESTRICT TO GROUPS
        $this->writeLn(
            "Send mail to users in the following GROUPS only (comma separated list of group ids;"
            . " leave empty to ignore groups): "
        );

        $groupIds = $this->readInput();

        if (empty($groupIds)) {
            $groupIds = [];
        } else {
            $groupIds = explode(',', $groupIds);
        }

        // GENERATE NEW PASSWORD?
        $this->writeLn(
            "Shall a new password be generated for each user? The new password will be available via the"
            . " [password] placeholder in the e-mail body. (y/N): "
        );

        $generatePassword = mb_strtolower($this->readInput()) === 'y';
        $forcePasswordReset = false;

        if ($generatePassword) {
            $this->writeLn(
                "Shall users be forced to set a new password immediately after logging in with their"
                . " generated password? (Y/n): "
            );

            $input = $this->readInput();
            $forcePasswordReset = empty($input) || mb_strtolower($input) !== 'n';
        }

        // ORDER BY
        $this->writeLn(
            "ORDER BY column and optional direction (id, username, email, firstname, lastname, lang or active;"
            . " leave empty to use default order): "
        );
        $orderBy = $this->readInput();

        // Get all users
        $Connection = QUI::getDataBaseConnection();
        $QueryBuilder = $Connection->createQueryBuilder()
            ->select('id', 'username', 'email', 'firstname', 'lastname')
            ->from(QUI\Utils\Doctrine::quoteIdentifier(QUI::getUsers()::table()));
        $QueryBuilder->where(
            $QueryBuilder->expr()->in(QUI\Utils\Doctrine::quoteIdentifier('lang'), ':languages')
        )->setParameter('languages', $languages, ArrayParameterType::STRING);

        if (!$inactiveUsers) {
            $QueryBuilder->andWhere(QUI\Utils\Doctrine::quoteIdentifier('active') . ' = :active')
                ->setParameter('active', 1);
        }

        if (!empty($groupIds)) {
            $groupConditions = [];

            foreach ($groupIds as $index => $groupId) {
                $parameter = 'group' . $index;
                $groupConditions[] = $QueryBuilder->expr()->like(
                    QUI\Utils\Doctrine::quoteIdentifier('usergroup'),
                    ':' . $parameter
                );
                $QueryBuilder->setParameter($parameter, '%,' . $groupId . ',%');
            }

            $QueryBuilder->andWhere($QueryBuilder->expr()->or(...$groupConditions));
        }

        if (!empty($orderBy)) {
            $orderParts = preg_split('/\s+/', trim($orderBy)) ?: [];
            $allowedColumns = ['id', 'username', 'email', 'firstname', 'lastname', 'lang', 'active'];
            $orderColumn = $orderParts[0] ?? '';
            $orderDirection = mb_strtoupper($orderParts[1] ?? 'ASC');

            if (
                count($orderParts) > 2
                || !in_array($orderColumn, $allowedColumns, true)
                || !in_array($orderDirection, ['ASC', 'DESC'], true)
            ) {
                $this->exitFail('Invalid ORDER BY value. Use an allowed column and optional ASC or DESC.');
            }

            $QueryBuilder->orderBy(QUI\Utils\Doctrine::quoteIdentifier($orderColumn), $orderDirection);
        }

        try {
            $result = $QueryBuilder->executeQuery()->fetchAllAssociative();
        } catch (\Doctrine\DBAL\Exception $Exception) {
            throw new QUI\Database\Exception(
                $Exception->getMessage(),
                (int)$Exception->getCode()
            );
        }
        $recipients = [];

        foreach ($result as $row) {
            if (empty($row['email'])) {
                continue;
            }

            $recipients[] = [
                'id' => (int)$row['id'],
                'username' => (string)$row['username'],
                'email' => (string)$row['email'],
                'firstname' => (string)$row['firstname'],
                'lastname' => (string)$row['lastname']
            ];
        }

        // DELETE USER STATISTICS?
        $infoFile = QUI::getPackage('quiqqer/frontend-users')->getVarDir() . 'send_user_mails';

        if (file_exists($infoFile)) {
            $this->writeLn(
                "Statistics of e-mails aready sent to user found. Delete statistics and start from scratch? (y/N): "
            );

            $deleteStatistics = mb_strtolower($this->readInput()) === 'y';

            if ($deleteStatistics) {
                unlink($infoFile);
                $this->writeLn("Statistics file deleted.");
            }
        }

        // EMAIL SETTINGS
        $this->writeLn("E-Mail subject?: ");
        $subject = $this->readInput();

        $this->writeLn("E-Mail sender mail? [system default]: ");
        $senderMail = $this->readInput();

        if (empty($senderMail)) {
            $senderMail = QUI::conf('mail', 'MAILFrom');
        }

        $this->writeLn("E-Mail sender name? [system default]: ");
        $senderName = $this->readInput();

        if (empty($senderName)) {
            $senderName = QUI::conf('mail', 'MAILFromText');
        }

        // LIMITS CONFIGURATION
        $limits = $this->getLimits();
        $setLimits = true;

        if (!empty($limits)) {
            $this->writeLn("The following mailing limits have been found:\n");

            $this->writeLn("Mails / 24 hours: " . ($limits['per24h'] ?: 'unlimited'));
            $this->writeLn("Mails / hour: " . ($limits['perHour'] ?: 'unlimited'));
            $this->writeLn("Mails / minute: " . ($limits['perMinute'] ?: 'unlimited'));

            $this->writeLn("\nDo you want to set new limits? (y/N)");
            $input = $this->readInput();
            $setLimits = !empty($input) && mb_strtolower($input) === 'y';
        }

        if ($setLimits) {
            $this->writeLn("(Limit) Mails / 24 hours? [unlimited]: ");
            $limitPer24h = $this->readInput();

            $this->writeLn("(Limit) Mails / hour? [unlimited]: ");
            $limitPerHour = $this->readInput();

            $this->writeLn("(Limit) Mails / minute? [unlimited]: ");
            $limitPerMinute = $this->readInput();

            $limits = [
                'per24h' => !empty($limitPer24h) ? (int)$limitPer24h : false,
                'perHour' => !empty($limitPerHour) ? (int)$limitPerHour : false,
                'perMinute' => !empty($limitPerMinute) ? (int)$limitPerMinute : false,
                'start24h' => false,
                'startHour' => false,
                'startMinute' => false,
                'current24h' => 0,
                'currentHour' => 0,
                'currentMinute' => 0
            ];

            $this->setLimits($limits);
        }

        if ($limits === false) {
            $this->writeLn('ERROR: Mail limits could not be initialized.');
            return;
        }

        // SUMMARY
        $this->writeLn("\nSUMMARY\n===============================================\n");

        $this->writeLn("LOCALE language: " . $lang);
        $this->writeLn("Include INACTIVE users: " . ($inactiveUsers ? "YES" : "NO"));
        $this->writeLn("User languages: " . implode(', ', $languages));
        $this->writeLn("User groups: " . (empty($groupIds) ? "ALL" : implode(', ', $groupIds)));
        $this->writeLn("Generate new password: " . ($generatePassword ? "YES" : "NO"));
        $this->writeLn("Force password reset: " . ($forcePasswordReset ? "YES" : "NO"));
        $this->writeLn("ORDER BY: " . (empty($orderBy) ? "DEFAULT" : $orderBy));
        $this->writeLn("\nE-Mail subject: " . $subject);
        $this->writeLn("\nE-Mail sender mail: " . $senderMail);
        $this->writeLn("\nE-Mail sender name: " . $senderName);
        $this->writeLn(
            "\nE-Mail will be sent to " . count($recipients) . " out of " . count($result) . " selected users."
            . " " . (count($result) - count($recipients)) . " users have no e-mail address and are ignored."
        );

        $this->writeLn("\nLimits:");
        $this->writeLn("Mails / 24 hours: " . ($limits['per24h'] ?: 'unlimited'));
        $this->writeLn("Mails / hour: " . ($limits['perHour'] ?: 'unlimited'));
        $this->writeLn("Mails / minute: " . ($limits['perMinute'] ?: 'unlimited'));

        $this->mail['body'] = $body;
        $this->mail['senderMail'] = $senderMail;
        $this->mail['senderName'] = $senderName;
        $this->mail['subject'] = $subject;

        $this->recipients = $recipients;
        $this->settings['forcePasswordReset'] = $forcePasswordReset;
        $this->settings['setNewPassword'] = $generatePassword;

        // TEST MAIL
        $this->writeLn("\n\nSend test mail? (Y/n): ");
        $testMail = mb_strtolower($this->readInput()) !== 'n';

        if ($testMail) {
            $this->writeLn("Test e-mail address: ");
            $testEmailAddress = $this->readInput();

            if (!empty($testEmailAddress)) {
                $this->writeLn("\nSend test mail...");
                $this->sendMails($testEmailAddress);
            }
        }

        // CONFIRM AND SEND E-MAILS
        $this->writeLn("\n\nIs everything correct? Send e-mails NOW? (Y/n): ");
        $confirm = mb_strtolower($this->readInput()) !== 'n';

        if (!$confirm) {
            $this->execute();
            return;
        }

        $this->sendMails();

        $this->exitSuccess();
    }

    /**
     * Get mail status info for a specific user
     *
     * @param int $userId
     * @return array{sent: bool, sent_date: string|false, error?: string}|array{}
     */
    protected function getUserInfo(int $userId): array
    {
        try {
            $infoFile = QUI::getPackage('quiqqer/frontend-users')->getVarDir() . 'send_user_mails';
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            $this->writeLn("ERROR on reading user info file: " . $Exception->getMessage());
            return [];
        }

        $userInfo = [];

        if (file_exists($infoFile)) {
            $content = file_get_contents($infoFile);

            if ($content === false) {
                $this->writeLn("ERROR on reading user info file $infoFile.");
                return [];
            }

            $userInfo = json_decode($content, true);

            if (!is_array($userInfo)) {
                $this->writeLn("ERROR: User info file $infoFile contains invalid JSON.");
                return [];
            }
        }

        if (empty($userInfo[$userId])) {
            $user = [
                'sent' => false,
                'sent_date' => false
            ];

            $this->writeUserInfo($userId, $user);
        } else {
            $user = $userInfo[$userId];
        }

        return $user;
    }

    /**
     * Write mail status info for a specific user to a file
     *
     * @param int $userId
     * @param array{sent: bool, sent_date: string|false, error?: string} $info
     * @return void
     */
    protected function writeUserInfo(int $userId, array $info): void
    {
        try {
            $infoFile = QUI::getPackage('quiqqer/frontend-users')->getVarDir() . 'send_user_mails';
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            $this->writeLn("ERROR on reading user info file: " . $Exception->getMessage());
            return;
        }

        $userInfo = [];

        if (file_exists($infoFile)) {
            $content = file_get_contents($infoFile);

            if ($content === false) {
                $this->writeLn("ERROR on reading user info file $infoFile.");
                return;
            }

            $userInfo = json_decode($content, true);

            if (!is_array($userInfo)) {
                $this->writeLn("ERROR: User info file $infoFile contains invalid JSON.");
                return;
            }
        }

        $userInfo[$userId] = $info;
        $json = json_encode($userInfo);

        if ($json === false) {
            $this->writeLn('ERROR: Mail status data could not be encoded.');
            return;
        }

        if (file_put_contents($infoFile, $json) === false) {
            $this->writeLn("ERROR on writing user info file $infoFile.");
            return;
        }
    }

    /**
     * Check if the sending of a mail is currently within the configured limits
     *
     * @param array{
     *     per24h: int|false,
     *     perHour: int|false,
     *     perMinute: int|false,
     *     start24h: string|false,
     *     startHour: string|false,
     *     startMinute: string|false,
     *     current24h: int,
     *     currentHour: int,
     *     currentMinute: int
     * } $limits - Limits config
     * @return void
     */
    protected function setLimits(array $limits): void
    {
        try {
            $limitsFile = QUI::getPackage('quiqqer/frontend-users')->getVarDir() . 'send_user_mails_limits';
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            $this->writeLn("ERROR on reading user info file: " . $Exception->getMessage());
            return;
        }

        $json = json_encode($limits);

        if ($json === false) {
            $this->writeLn('ERROR: Mail limits could not be encoded.');
            return;
        }

        if (file_put_contents($limitsFile, $json) === false) {
            $this->writeLn("ERROR on writing limits file $limitsFile.");
            return;
        }
    }

    /**
     * Get current limits configuration
     *
     * @return array{
     *     per24h: int|false,
     *     perHour: int|false,
     *     perMinute: int|false,
     *     start24h: string|false,
     *     startHour: string|false,
     *     startMinute: string|false,
     *     current24h: int,
     *     currentHour: int,
     *     currentMinute: int
     * }|false - Limit config or false if limits not yet configured
     */
    protected function getLimits(): bool | array
    {
        try {
            $limitsFile = QUI::getPackage('quiqqer/frontend-users')->getVarDir() . 'send_user_mails_limits';
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            $this->writeLn("ERROR on reading user info file: " . $Exception->getMessage());
            return false;
        }

        if (!file_exists($limitsFile)) {
            return false;
        }

        $content = file_get_contents($limitsFile);

        if ($content === false) {
            $this->writeLn("ERROR on reading limits file $limitsFile.");
            return false;
        }

        $limits = json_decode($content, true);

        if (!is_array($limits)) {
            $this->writeLn("ERROR: Limits file $limitsFile contains invalid JSON.");
            return false;
        }

        if (
            !array_key_exists('per24h', $limits)
            || (!is_int($limits['per24h']) && $limits['per24h'] !== false)
            || !array_key_exists('perHour', $limits)
            || (!is_int($limits['perHour']) && $limits['perHour'] !== false)
            || !array_key_exists('perMinute', $limits)
            || (!is_int($limits['perMinute']) && $limits['perMinute'] !== false)
            || !array_key_exists('start24h', $limits)
            || (!is_string($limits['start24h']) && $limits['start24h'] !== false)
            || !array_key_exists('startHour', $limits)
            || (!is_string($limits['startHour']) && $limits['startHour'] !== false)
            || !array_key_exists('startMinute', $limits)
            || (!is_string($limits['startMinute']) && $limits['startMinute'] !== false)
            || !isset($limits['current24h'])
            || !is_int($limits['current24h'])
            || !isset($limits['currentHour'])
            || !is_int($limits['currentHour'])
            || !isset($limits['currentMinute'])
            || !is_int($limits['currentMinute'])
        ) {
            $this->writeLn("ERROR: Limits file $limitsFile has an invalid structure.");
            return false;
        }

        return $limits;
    }

    /**
     * Update limits
     *
     * This assumes that ONE e-mail has been successfully sent.
     *
     * @return void
     */
    protected function updateLimits(): void
    {
        $limits = $this->getLimits();
        $Now = new DateTime();

        // Update minute limit
        if (!empty($limits['perMinute'])) {
            if (empty($limits['startMinute'])) {
                $Start = new DateTime();
                $limits['startMinute'] = $Start->format('Y-m-d H:i:s');
            } else {
                $Start = date_create($limits['startMinute']);

                if ($Start === false) {
                    $this->writeLn('ERROR: Mail limits contain an invalid minute start date.');
                    return;
                }
            }

            $End = clone $Start;
            $End->add(new DateInterval('PT1M'));

            // Reset limit
            if ($Now > $End) {
                $Start = new DateTime();
                $limits['startMinute'] = $Start->format('Y-m-d H:i:s');
                $limits['currentMinute'] = 0;
            }

            $limits['currentMinute']++;
        }

        // Update hour limit
        if (!empty($limits['perHour'])) {
            if (empty($limits['startHour'])) {
                $Start = new DateTime();
                $limits['startHour'] = $Start->format('Y-m-d H:i:s');
            } else {
                $Start = date_create($limits['startHour']);

                if ($Start === false) {
                    $this->writeLn('ERROR: Mail limits contain an invalid hour start date.');
                    return;
                }
            }

            $End = clone $Start;
            $End->add(new DateInterval('PT1H'));

            // Reset limit
            if ($Now > $End) {
                $Start = new DateTime();
                $limits['startHour'] = $Start->format('Y-m-d H:i:s');
                $limits['currentHour'] = 0;
            }

            $limits['currentHour']++;
        }

        // Update 24 hour limit
        if (!empty($limits['per24h'])) {
            if (empty($limits['start24h'])) {
                $Start = new DateTime();
                $limits['start24h'] = $Start->format('Y-m-d H:i:s');
            } else {
                $Start = date_create($limits['start24h']);

                if ($Start === false) {
                    $this->writeLn('ERROR: Mail limits contain an invalid 24-hour start date.');
                    return;
                }
            }

            $End = clone $Start;
            // A calendar day preserves the legacy "24 hours" behavior across DST changes.
            $End->add(new DateInterval('P1D'));

            // Reset limit
            if ($Now > $End) {
                $Start = new DateTime();
                $limits['start24h'] = $Start->format('Y-m-d H:i:s');
                $limits['current24h'] = 0;
            }

            $limits['current24h']++;
        }

        try {
            $limitsFile = QUI::getPackage('quiqqer/frontend-users')->getVarDir() . 'send_user_mails_limits';
            $json = json_encode($limits);

            if ($json === false) {
                $this->writeLn('ERROR: Mail limits could not be encoded.');
                return;
            }

            if (file_put_contents($limitsFile, $json) === false) {
                $this->writeLn("ERROR on writing limits file $limitsFile.");
                return;
            }
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            $this->writeLn("ERROR on writing limits file: " . $Exception->getMessage());
        }
    }

    /**
     * Check if the sending of a mail is currently within the configured limits
     *
     * @return bool
     */
    protected function isMailAllowed(): bool
    {
        $limits = $this->getLimits();
        $Now = new DateTime();

        // Check minute limit
        if (!empty($limits['perMinute'])) {
            if (empty($limits['startMinute'])) {
                $Start = new DateTime();
            } else {
                $Start = date_create($limits['startMinute']);

                if ($Start === false) {
                    $this->writeLn('ERROR: Mail limits contain an invalid minute start date.');
                    return false;
                }
            }

            $End = clone $Start;
            $End->add(new DateInterval('PT1M'));

            // Limit applies
            if ($Now < $End) {
                $mailCountMax = $limits['perMinute'];
                $mailCount = $limits['currentMinute'];

                if ($mailCount >= $mailCountMax) {
                    return false;
                }
            }
        }

        // Check hour limit
        if (!empty($limits['perHour'])) {
            if (empty($limits['startHour'])) {
                $Start = new DateTime();
            } else {
                $Start = date_create($limits['startHour']);

                if ($Start === false) {
                    $this->writeLn('ERROR: Mail limits contain an invalid hour start date.');
                    return false;
                }
            }

            $End = clone $Start;
            $End->add(new DateInterval('PT1H'));

            // Limit applies
            if ($Now < $End) {
                $mailCountMax = $limits['perHour'];
                $mailCount = $limits['currentHour'];

                if ($mailCount >= $mailCountMax) {
                    return false;
                }
            }
        }

        // Check 24 hour limit
        if (!empty($limits['per24h'])) {
            if (empty($limits['start24h'])) {
                $Start = new DateTime();
            } else {
                $Start = date_create($limits['start24h']);

                if ($Start === false) {
                    $this->writeLn('ERROR: Mail limits contain an invalid 24-hour start date.');
                    return false;
                }
            }

            $End = clone $Start;
            // A calendar day preserves the legacy "24 hours" behavior across DST changes.
            $End->add(new DateInterval('P1D'));

            // Limit applies
            if ($Now < $End) {
                $mailCountMax = $limits['per24h'];
                $mailCount = $limits['current24h'];

                if ($mailCount >= $mailCountMax) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param string|null $testMailAddress (optional) - If set, a single test mail will be sent to this address
     * @return void
     */
    protected function sendMails(null | string $testMailAddress = null): void
    {
        $Users = QUI::getUsers();
        $SystemUser = $Users->getSystemUser();

        if ($testMailAddress === null) {
            $recipients = $this->recipients;
        } else {
            $recipients = [
                0 => [
                    'id' => 0,
                    'username' => 'Test-User',
                    'email' => $testMailAddress
                ]
            ];
        }

        // Queue mails
        foreach ($recipients as $recipient) {
            $userId = (int)$recipient['id'];

            $this->writeLn("### User $userId ###");

            // Check if user already got an e-mail
            if (!$testMailAddress) {
                $userInfo = $this->getUserInfo($userId);

                if (!empty($userInfo['sent'])) {
                    $this->writeLn("Mail already sent at " . $userInfo['sent_date'] . " -> Skipping user.");
                    continue;
                }

                // Check if mail limit(s) apply
                while (true) {
                    if ($this->isMailAllowed()) {
                        break;
                    }

                    $this->writeLn(
                        "[" . date('Y-m-d H:i:s') . "] Current mail limit reached. Waiting 60s and then retry..."
                    );

                    sleep(60);
                }
            }

            if (!empty($recipient['firstname']) && !empty($recipient['lastname'])) {
                $name = $recipient['firstname'] . ' ' . $recipient['lastname'];
            } else {
                $name = $recipient['username'];
            }

            $email = $recipient['email'];

            if (!QUI\Utils\Security\Orthos::checkMailSyntax($email)) {
                $this->writeLn("Email address \"$email\" is invalid and can therefore not be used. Skipping.");

                $this->writeUserInfo($userId, [
                    'sent' => true,
                    'sent_date' => date('Y-m-d H:i:s'),
                    'error' => $email . " is no valid email syntax. email was not sent."
                ]);

                continue;
            }

            $newPassword = '';

            if (!$testMailAddress && $this->settings['setNewPassword']) {
                $this->writeLn("Generating new password for $email...");

                try {
                    $User = $Users->get($userId);

                    if ($User->isSU()) {
                        $this->writeLn("User is SuperUser. Skipping...");
                        continue;
                    }

                    $newPassword = QUI\Security\Password::generateRandom();

                    $User->setPassword($newPassword, $SystemUser);

                    $this->write(" OK!");

                    if ($this->settings['forcePasswordReset']) {
                        $this->writeLn("Set force new password...");

                        $User->setAttribute('quiqqer.set.new.password', true);
                        $User->save($SystemUser);

                        $this->write(" OK!");
                    }
                } catch (\Exception $Exception) {
                    QUI\System\Log::writeException($Exception);
                    $this->write(" Error: " . $Exception->getMessage());

                    continue;
                }
            }

            $body = str_replace(
                ['[name]', '[email]', '[password]'],
                [$name, $email, $newPassword],
                $this->mail['body']
            );

            $Mailer = QUI::getMailManager()->getMailer();
            $Mailer->setFrom($this->mail['senderMail']);
            $Mailer->setFromName($this->mail['senderName']);
            $Mailer->setSubject($this->mail['subject']);
            $Mailer->setHTML(true);

            $Mailer->setBody($body);
            $Mailer->addRecipient($email);

            $this->writeLn("Sending mail to $name ($email)...");

            try {
                $Mailer->send();
            } catch (\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
                $this->write(" Error: " . $Exception->getMessage());
                continue;
            }

            $this->write(" OK!");

            $this->writeUserInfo($userId, [
                'sent' => true,
                'sent_date' => date('Y-m-d H:i:s')
            ]);

            $this->updateLimits();
        }
    }

    /**
     * Exits the console tool with a success msg and status 0
     *
     * @return never
     */
    protected function exitSuccess(): never
    {
        $this->writeLn("\n\nMails have been successfully queued and will be sent via cron.");
        $this->writeLn();

        exit(0);
    }

    /**
     * Exits the console tool with an error msg and status 1
     *
     * @param string $msg
     * @return never
     */
    protected function exitFail($msg): never
    {
        $this->writeLn("Script aborted due to an error:");
        $this->writeLn();
        $this->writeLn($msg);
        $this->writeLn();
        $this->writeLn();

        exit(1);
    }
}
