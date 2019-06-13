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
        $this->writeLn("ORDER BY: ".(empty($orderBy) ? "DEFAULT" : $orderBy));
        $this->writeLn("\nE-Mail subject: ".$subject);
        $this->writeLn("\nE-Mail sender mail: ".$senderMail);
        $this->writeLn("\nE-Mail sender name: ".$senderName);
        $this->writeLn(
            "\nE-Mail will be sent to ".count($recipients)." out of ".count($result)." selected users."
            ." ".(count($result) - count($recipients))." users have no e-mail address and are ignored."
        );

        // TEST MAIL
        $this->writeLn("\n\nSend test mail? (Y/n): ");
        $testMail = mb_strtolower($this->readInput()) !== 'n';

        if ($testMail) {
            $this->writeLn("Test e-mail address: ");
            $testEmailAddress = $this->readInput();

            if (!empty($testEmailAddress)) {
                $this->writeLn("\nSend test mail...");
                $this->sendMails(
                    $body,
                    $senderMail,
                    $senderName,
                    $subject,
                    [
                        0 => [
                            'username' => 'Test-User',
                            'email'    => $testEmailAddress
                        ]
                    ]
                );
                $this->write(" SENT!");
            }
        }

        // CONFIRM AND SEND E-MAILS
        $this->writeLn("\n\nIs everything correct? Send e-mails NOW? (Y/n): ");
        $confirm = mb_strtolower($this->readInput()) !== 'n';

        if (!$confirm) {
            $this->execute();
            return;
        }

        $this->sendMails($body, $senderMail, $senderName, $subject, $recipients);

        $this->exitSuccess();
    }

    /**
     * @param string $body
     * @param string $senderMail
     * @param string $senderName
     * @param string $subject
     * @param array $recipients
     * @return void
     */
    protected function sendMails($body, $senderMail, $senderName, $subject, $recipients)
    {
        // Queue mails
        foreach ($recipients as $recipient) {
            if (!empty($recipient['firstname']) && !empty($recipient['lastname'])) {
                $name = $recipient['firstname'].' '.$recipient['lastname'];
            } else {
                $name = $recipient['username'];
            }

            $email = $recipient['email'];

            $body = str_replace(
                ['[name]', '[email]'],
                [$name, $email],
                $body
            );

            $Mailer = QUI::getMailManager()->getMailer();
            $Mailer->setFrom($senderMail);
            $Mailer->setFromName($senderName);
            $Mailer->setSubject($subject);
            $Mailer->setHTML(true);

            $Mailer->setBody($body);
            $Mailer->addRecipient($email);

            $Mailer->send();
        }
    }

    /**
     * Exits the console tool with a success msg and status 0
     *
     * @return void
     */
    protected function exitSuccess()
    {
        $this->writeLn("Mails have been successfully queued and will be sent via cron.");
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
