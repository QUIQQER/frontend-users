<?php

namespace QUI\FrontendUsers\Console;

use JetBrains\PhpStorm\NoReturn;
use QUI;
use QUI\Exception;

use function date;
use function date_create;
use function date_interval_create_from_date_string;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function json_decode;
use function json_encode;
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
     * @var array
     */
    protected array $mail = [
        'body' => '',
        'senderMail' => '',
        'senderName' => '',
        'subject' => ''
    ];

    /**
     * @var array
     */
    protected array $recipients = [];

    /**
     * General settings
     *
     * @var array
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
     */
    public function execute(): void
    {
        QUI\Permissions\Permission::isAdmin();

        $bodyFile = $this->getArgument('bodyfile');

        if (!file_exists($bodyFile) || !is_readable($bodyFile)) {
            $this->exitFail("Body file $bodyFile was not found or is not readable by PHP.");
        }

        $body = file_get_contents($bodyFile);

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
        $this->writeLn("ORDER BY clause for the `users` table (leave empty to use default order): ");
        $orderBy = $this->readInput();

        // Get all users
        $sql = "SELECT `id`, `username`, `email`, `firstname`, `lastname` FROM " . QUI::getUsers()::table();
        $where[] = "`lang` IN ('" . implode("','", $languages) . "')";

        if (!$inactiveUsers) {
            $where[] = "`active` = 1";
        }

        if (!empty($groupIds)) {
            $whereOR = [];

            foreach ($groupIds as $groupId) {
                $whereOR[] = "`usergroup` LIKE '%,$groupId,%'";
            }

            $where[] = "(" . implode(" OR ", $whereOR) . ")";
        }

        $sql .= " WHERE " . implode(" AND ", $where);

        if (!empty($orderBy)) {
            $sql .= " ORDER BY $orderBy";
        }

        $result = QUI::getDataBase()->fetchSQL($sql);
        $recipients = [];

        foreach ($result as $row) {
            if (empty($row['email'])) {
                continue;
            }

            $recipients[] = $row;
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
     * @return array
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
            $userInfo = json_decode(file_get_contents($infoFile), true);
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
     * @param array $info
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
            $userInfo = json_decode(file_get_contents($infoFile), true);
        }

        $userInfo[$userId] = $info;

        file_put_contents($infoFile, json_encode($userInfo));
    }

    /**
     * Check if the sending of a mail is currently within the configured limits
     *
     * @param array $limits - Limits config
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

        file_put_contents($limitsFile, json_encode($limits));
    }

    /**
     * Get current limits configuration
     *
     * @return array|false - Limit config or false if limits not yet configured
     */
    protected function getLimits(): bool|array
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

        return json_decode(file_get_contents($limitsFile), true);
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
        $Now = date_create();

        // Update minute limit
        if (!empty($limits['perMinute'])) {
            if (empty($limits['startMinute'])) {
                $Start = date_create();
                $limits['startMinute'] = $Start->format('Y-m-d H:i:s');
            } else {
                $Start = date_create($limits['startMinute']);
            }

            $End = clone $Start;
            $End->add(date_interval_create_from_date_string('1 minutes'));

            // Reset limit
            if ($Now > $End) {
                $Start = date_create();
                $limits['startMinute'] = $Start->format('Y-m-d H:i:s');
                $limits['currentMinute'] = 0;
            }

            $limits['currentMinute']++;
        }

        // Update hour limit
        if (!empty($limits['perHour'])) {
            if (empty($limits['startHour'])) {
                $Start = date_create();
                $limits['startHour'] = $Start->format('Y-m-d H:i:s');
            } else {
                $Start = date_create($limits['startHour']);
            }

            $End = clone $Start;
            $End->add(date_interval_create_from_date_string('1 hours'));

            // Reset limit
            if ($Now > $End) {
                $Start = date_create();
                $limits['startHour'] = $Start->format('Y-m-d H:i:s');
                $limits['currentHour'] = 0;
            }

            $limits['currentHour']++;
        }

        // Update 24 hour limit
        if (!empty($limits['per24h'])) {
            if (empty($limits['start24h'])) {
                $Start = date_create();
                $limits['start24h'] = $Start->format('Y-m-d H:i:s');
            } else {
                $Start = date_create($limits['start24h']);
            }

            $End = clone $Start;
            $End->add(date_interval_create_from_date_string('24 hours'));

            // Reset limit
            if ($Now > $End) {
                $Start = date_create();
                $limits['start24h'] = $Start->format('Y-m-d H:i:s');
                $limits['current24h'] = 0;
            }

            $limits['current24h']++;
        }

        try {
            $limitsFile = QUI::getPackage('quiqqer/frontend-users')->getVarDir() . 'send_user_mails_limits';
            file_put_contents($limitsFile, json_encode($limits));
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
        $Now = date_create();

        // Check minute limit
        if (!empty($limits['perMinute'])) {
            if (empty($limits['startMinute'])) {
                $Start = date_create();
            } else {
                $Start = date_create($limits['startMinute']);
            }

            $End = clone $Start;
            $End->add(date_interval_create_from_date_string('1 minutes'));

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
                $Start = date_create();
            } else {
                $Start = date_create($limits['startHour']);
            }

            $End = clone $Start;
            $End->add(date_interval_create_from_date_string('1 hours'));

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
                $Start = date_create();
            } else {
                $Start = date_create($limits['start24h']);
            }

            $End = clone $Start;
            $End->add(date_interval_create_from_date_string('24 hours'));

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
    protected function sendMails(string $testMailAddress = null): void
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
            $userId = $recipient['id'];

            $this->writeLn("### User $userId ###");

            // Check if user already got an e-mail
            if (!$testMailAddress) {
                $userInfo = $this->getUserInfo($userId);

                if (!empty($userInfo['sent'])) {
                    $this->writeLn("Mail already sent at " . $userInfo['sent_date'] . " -> Skipping user.");
                    continue;
                }

                // Check if mail limit(s) apply
                do {
                    $mailAllowed = $this->isMailAllowed();

                    if ($mailAllowed) {
                        break;
                    }

                    $this->writeLn(
                        "[" . date('Y-m-d H:i:s') . "] Current mail limit reached. Waiting 60s and then retry..."
                    );

                    sleep(60);
                } while (!$mailAllowed);
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
     * @return void
     */
    #[NoReturn] protected function exitSuccess(): void
    {
        $this->writeLn("\n\nMails have been successfully queued and will be sent via cron.");
        $this->writeLn();

        exit(0);
    }

    /**
     * Exits the console tool with an error msg and status 1
     *
     * @param $msg
     * @return void
     */
    #[NoReturn] protected function exitFail($msg): void
    {
        $this->writeLn("Script aborted due to an error:");
        $this->writeLn();
        $this->writeLn($msg);
        $this->writeLn();
        $this->writeLn();

        exit(1);
    }
}
