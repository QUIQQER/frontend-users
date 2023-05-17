<?php

namespace QUI\FrontendUsers\Console;

use QUI;
use function implode;

/**
 * Console tool to anonymise users
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class AnonymiseUsers extends QUI\System\Console\Tool
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setName('frontend-users:anonymiseUsers')
            ->setDescription(
                "Anonymise users in the system"
            );

        $this->addArgument(
            'email_only',
            'Anonymise email addresses only and leave all other user data as is.',
            false,
            true
        );
    }

    /**
     * Execute the console tool
     */
    public function execute()
    {
        QUI\Permissions\Permission::isAdmin();

        // RESTRICT TO GROUPS
        $this->writeLn(
            "Anonymise users in the following GROUPS only (comma separated list of group ids;"
            . " leave empty to anonymise all users): "
        );

        $groupIds = $this->readInput();

        if (empty($groupIds)) {
            $groupIds = [];
        } else {
            $groupIds = explode(',', $groupIds);
        }

        // EMAIL

        $this->writeLn(
            "Use the following host handle for email-addresses [@foo.bar]: "
        );

        $emailHandle = $this->readInput();

        if (empty($emailHandle)) {
            $emailHandle = '@foo.bar';
        }

        // SUMMARY
        $this->writeLn("\nSUMMARY\n===============================================\n");

        $this->writeLn("User groups: " . (empty($groupIds) ? "ALL" : implode(', ', $groupIds)));
        $this->writeLn("E-Mail handle: " . $emailHandle);

        // CONFIRM
        $this->writeLn("\n\nIs everything correct? Anonymise NOW? (Y/n): ");
        $confirm = mb_strtolower($this->readInput()) !== 'n';

        if (!$confirm) {
            $this->execute();
            return;
        }

        $this->anonymiseUsers([
            'groupIds'    => $groupIds,
            'emailHandle' => $emailHandle
        ]);

        $this->exitSuccess();
    }

    /**
     * @param array $settings
     * @return void
     */
    protected function anonymiseUsers($settings)
    {
        $groupIds     = $settings['groupIds'];
        $tbl          = QUI::getDBTableName('users');
        $tblAddresses = QUI::getDBTableName('users_address');

        // Get all users
        $sql   = "SELECT `id`, `username`, `email`, `firstname`, `lastname` FROM " . $tbl;
        $where = [];

        if (!empty($groupIds)) {
            $whereOR = [];

            foreach ($groupIds as $groupId) {
                $whereOR[] = "`usergroup` LIKE '%,$groupId,%'";
            }

            $where[] = "(" . implode(" OR ", $whereOR) . ")";
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        if (!empty($orderBy)) {
            $sql .= " ORDER BY $orderBy";
        }

        $result = QUI::getDataBase()->fetchSQL($sql);

        $anonymiseEmailOnly = !empty($this->getArgument('email_only'));

        foreach ($result as $row) {
            $user   = $row;
            $userId = $row['id'];

            $this->write("Anonymise user #" . $userId . "...");

            try {
                $userData = [
                    'email' => $userId . $settings['emailHandle']
                ];

                if (!$anonymiseEmailOnly) {
                    $userData['username']   = 'user_' . $userId;
                    $userData['firstname']  = $this->anonymiseString($user['firstname']);
                    $userData['lastname']   = $this->anonymiseString($user['lastname']);
                    $userData['user_agent'] = '';
                    $userData['birthday']   = '1970-01-01';
                }

                QUI::getDataBase()->update(
                    $tbl,
                    $userData,
                    [
                        'id' => $userId
                    ]
                );

                $this->write(" OK!");

                if (!$anonymiseEmailOnly) {
                    $this->writeLn("Anonymise user address(es)...");

                    $addressResult = QUI::getDataBase()->fetch([
                        'from'  => $tblAddresses,
                        'where' => [
                            'uid' => $userId
                        ]
                    ]);

                    foreach ($addressResult as $address) {
                        QUI::getDataBase()->update(
                            $tblAddresses,
                            [
                                'salutation' => $this->anonymiseString($address['salutation']),
                                'firstname'  => $this->anonymiseString($address['firstname']),
                                'lastname'   => $this->anonymiseString($address['lastname']),
                                'company'    => $this->anonymiseString($address['company']),
                                'street_no'  => $this->anonymiseString($address['street_no']),
                                'zip'        => $this->anonymiseString($address['zip']),
                                'city'       => $this->anonymiseString($address['city']),
                                'phone'      => '[]',
                                'mail'       => '["' . $userId . $settings['emailHandle'] . '"]'
                            ],
                            [
                                'id' => $address['id']
                            ]
                        );
                    }
                }

                $this->write(" OK!");
            } catch (\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
                $this->write("ERROR: " . $Exception->getMessage());
            }
        }
    }

    /**
     * Anonymise a string
     *
     * @param string $str
     * @return string - Anonymised string
     */
    protected function anonymiseString(string $str)
    {
        $parts        = \explode(' ', $str);
        $anonStrParts = [];

        foreach ($parts as $part) {
            $anonStrParts[] = \mb_substr($part, 0, 1) . '*';
        }

        return \implode(' ', $anonStrParts);
    }

    /**
     * Exits the console tool with a success msg and status 0
     *
     * @return void
     */
    protected function exitSuccess()
    {
        $this->writeLn("\n\nUsers have been successfully anonymised.");
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
