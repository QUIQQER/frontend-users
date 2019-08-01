<?php

namespace QUI\FrontendUsers\Console;

use QUI;

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
    protected $mail = [
        'body'       => '',
        'senderMail' => '',
        'senderName' => '',
        'subject'    => ''
    ];

    /**
     * @var array
     */
    protected $recipients = [];

    /**
     * General settings
     *
     * @var array
     */
    protected $settings = [
        'setNewPassword'     => false,
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
     */
    public function execute()
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
            ." leave empty to ignore groups): "
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
            ." [password] placeholder in the e-mail body. (y/N): "
        );

        $generatePassword   = mb_strtolower($this->readInput()) === 'y';
        $forcePasswordReset = false;

        if ($generatePassword) {
            $this->writeLn(
                "Shall users be forced to set a new password immediately after logging in with their"
                ." generated password? (Y/n): "
            );

            $input              = $this->readInput();
            $forcePasswordReset = empty($input) || mb_strtolower($input) !== 'n';
        }

        // ORDER BY
        $this->writeLn("ORDER BY clause for the `users` table (leave empty to use default order): ");
        $orderBy = $this->readInput();

        // Get all users
        $sql     = "SELECT `id`, `username`, `email`, `firstname`, `lastname` FROM ".QUI::getUsers()::table();
        $where[] = "`lang` IN ('".implode("','", $languages)."')";

        if (!$inactiveUsers) {
            $where[] = "`active` = 1";
        }

        if (!empty($groupIds)) {
            $whereOR = [];

            foreach ($groupIds as $groupId) {
                $whereOR[] = "`usergroup` LIKE '%,$groupId,%'";
            }

            $where[] = "(".implode(" OR ", $whereOR).")";
        }

        $sql .= " WHERE ".implode(" AND ", $where);

        if (!empty($orderBy)) {
            $sql .= " ORDER BY $orderBy";
        }

        $result     = QUI::getDataBase()->fetchSQL($sql);
        $recipients = [];

        foreach ($result as $row) {
            if (empty($row['email'])) {
                continue;
            }

            $recipients[] = $row;
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

        // SUMMARY
        $this->writeLn("\nSUMMARY\n===============================================\n");

        $this->writeLn("LOCALE language: ".$lang);
        $this->writeLn("Include INACTIVE users: ".($inactiveUsers ? "YES" : "NO"));
        $this->writeLn("User languages: ".implode(', ', $languages));
        $this->writeLn("User groups: ".(empty($groupIds) ? "ALL" : implode(', ', $groupIds)));
        $this->writeLn("Generate new password: ".($generatePassword ? "YES" : "NO"));
        $this->writeLn("Force password reset: ".($forcePasswordReset ? "YES" : "NO"));
        $this->writeLn("ORDER BY: ".(empty($orderBy) ? "DEFAULT" : $orderBy));
        $this->writeLn("\nE-Mail subject: ".$subject);
        $this->writeLn("\nE-Mail sender mail: ".$senderMail);
        $this->writeLn("\nE-Mail sender name: ".$senderName);
        $this->writeLn(
            "\nE-Mail will be sent to ".count($recipients)." out of ".count($result)." selected users."
            ." ".(count($result) - count($recipients))." users have no e-mail address and are ignored."
        );

        $this->mail['body']       = $body;
        $this->mail['senderMail'] = $senderMail;
        $this->mail['senderName'] = $senderName;
        $this->mail['subject']    = $subject;

        $this->recipients                     = $recipients;
        $this->settings['forcePasswordReset'] = $forcePasswordReset;
        $this->settings['setNewPassword']     = $generatePassword;

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
     * @param string $testMailAddress (optional) - If set, a single test mail will be sent to this address
     * @return void
     */
    protected function sendMails($testMailAddress = null)
    {
        $Users      = QUI::getUsers();
        $SystemUser = $Users->getSystemUser();

        if ($testMailAddress === null) {
            $recipients = $this->recipients;
        } else {
            $recipients = [
                0 => [
                    'username' => 'Test-User',
                    'email'    => $testMailAddress
                ]
            ];
        }

        // Queue mails
        foreach ($recipients as $recipient) {
            if (!empty($recipient['firstname']) && !empty($recipient['lastname'])) {
                $name = $recipient['firstname'].' '.$recipient['lastname'];
            } else {
                $name = $recipient['username'];
            }

            $email       = $recipient['email'];
            $newPassword = '';

            if (!$testMailAddress && $this->settings['setNewPassword']) {
                $this->writeLn("Generating new password for $email...");

                try {
                    $User        = $Users->get($recipient['id']);
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
                    $this->write(" Error: ".$Exception->getMessage());
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
                $this->write(" Error: ".$Exception->getMessage());
                continue;
            }

            $this->write(" OK!");
        }
    }

    /**
     * Exits the console tool with a success msg and status 0
     *
     * @return void
     */
    protected function exitSuccess()
    {
        $this->writeLn("\n\nMails have been successfully queued and will be sent via cron.");
        $this->writeLn("");

        exit(0);
    }

    /**
     * Exits the console tool with an error msg and status 1
     *
     * @param $msg
     * @return void
     */
    protected function exitFail($msg)
    {
        $this->writeLn("Script aborted due to an error:");
        $this->writeLn("");
        $this->writeLn($msg);
        $this->writeLn("");
        $this->writeLn("");

        exit(1);
    }
}
