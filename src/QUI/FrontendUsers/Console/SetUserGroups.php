<?php

namespace QUI\FrontendUsers\Console;

use QUI;

/**
 * Console tool to add users to groups
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class SetUserGroups extends QUI\System\Console\Tool
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setName('frontend-users:setUserGroups')
            ->setDescription(
                "Add users to groups"
            );
    }

    /**
     * Execute the console tool
     */
    public function execute()
    {
        QUI\Permissions\Permission::isAdmin();

        // Determine groups
        $this->writeLn(
            "Add users to the following groups (comma separated list of group ids): "
        );

        $groupIds = $this->readInput();

        if (empty($groupIds)) {
            $this->exitSuccess();
        }

        $groupIds = explode(',', $groupIds);

        // Determine users

        // INCLUDE INACTIVE USERS?
        $this->writeLn("Include INACTIVE users? (y/N): ");
        $inactiveUsers = mb_strtolower($this->readInput()) === 'y';

        // USER LANGUAGE
        $this->writeLn("Languages of the users? (comma separated language abbreviations) [all]: ");
        $languages = $this->readInput();

        if (!empty($languages) && mb_strtolower($languages) !== 'all') {
            $languages = explode(',', $languages);
        } else {
            $languages = false;
        }

        // RESTRICT TO GROUPS
        $this->writeLn(
            "Select users in the following GROUPS only (comma separated list of group ids;"
            . " leave empty to ignore groups): "
        );

        $userGroupIds = $this->readInput();

        if (empty($userGroupIds)) {
            $userGroupIds = [];
        } else {
            $userGroupIds = explode(',', $userGroupIds);
        }

        // Get all users
        $sql = "SELECT `id` FROM " . QUI::getUsers()::table();
        $where = [];

        if (!empty($languages)) {
            $where[] = "`lang` IN ('" . implode("','", $languages) . "')";
        }

        if (!$inactiveUsers) {
            $where[] = "`active` = 1";
        }

        if (!empty($userGroupIds)) {
            $whereOR = [];

            foreach ($userGroupIds as $groupId) {
                $whereOR[] = "`usergroup` LIKE '%,$groupId,%'";
            }

            $where[] = "(" . implode(" OR ", $whereOR) . ")";
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $result = QUI::getDataBase()->fetchSQL($sql);

        // SUMMARY
        $this->writeLn("\nSUMMARY\n===============================================\n");

        $this->writeLn("Add users to groups: " . implode(', ', $groupIds));
        $this->writeLn("Include INACTIVE users: " . ($inactiveUsers ? "YES" : "NO"));
        $this->writeLn("User languages: " . (empty($languages) ? 'ALL' : implode(', ', $languages)));
        $this->writeLn(
            "Select users in this groups only: " . (empty($userGroupIds) ? "ALL" : implode(', ', $userGroupIds))
        );

        $this->writeLn("\n\nIs everything correct? (Y/n): ");
        $confirm = mb_strtolower($this->readInput()) !== 'n';

        if (!$confirm) {
            $this->execute();
            return;
        }

        // Set groups
        $this->writeLn("\n\nSTART SETTING GROUPS\n");

        $Users = QUI::getUsers();
        $SystemUser = QUI::getUsers()->getSystemUser();

        foreach ($result as $row) {
            $User = $Users->get($row['id']);

            $this->writeLn("Add groups for User #" . $User->getId() . " (" . $User->getUsername() . ")...");

            foreach ($groupIds as $groupId) {
                try {
                    $this->writeLn("\tGroup #$groupId...");
                    $User->addToGroup($groupId);
                    $User->save($SystemUser);
                    $this->write(" OK!");
                } catch (\Exception $Exception) {
                    QUI\System\Log::writeException($Exception);
                    $this->write(" ERROR: " . $Exception->getMessage());
                }
            }
        }

        $this->exitSuccess();
    }

    /**
     * Exits the console tool with a success msg and status 0
     *
     * @return void
     */
    protected function exitSuccess()
    {
        $this->writeLn("User groups have been successfully set.");
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
