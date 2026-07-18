<?php

namespace QUI\FrontendUsers\Console;

use Exception;
use QUI;

use function explode;
use function implode;
use function mb_substr;

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
     * @throws QUI\Database\Exception
     */
    public function execute(): void
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
            "Use the following host handle for email-addresses [@foobar.local]: "
        );

        $emailHandle = $this->readInput();

        if (empty($emailHandle)) {
            $emailHandle = '@foobar.local';
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
            'groupIds' => $groupIds,
            'emailHandle' => $emailHandle
        ]);

        $this->exitSuccess();
    }

    /**
     * @param array $settings
     * @return void
     * @throws QUI\Database\Exception
     */
    protected function anonymiseUsers(array $settings): void
    {
        $groupIds = $settings['groupIds'];
        $Connection = QUI::getDataBaseConnection();
        $tbl = QUI\Utils\Doctrine::quoteIdentifier(QUI::getDBTableName('users'));
        $tblAddresses = QUI\Utils\Doctrine::quoteIdentifier(QUI::getDBTableName('users_address'));

        // Get all users
        $QueryBuilder = $Connection->createQueryBuilder()
            ->select('id', 'username', 'email', 'firstname', 'lastname')
            ->from($tbl)
            ->where(QUI\Utils\Doctrine::quoteIdentifier('su') . ' = :isSuperUser')
            ->setParameter('isSuperUser', 0);

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

        try {
            $result = $QueryBuilder->executeQuery()->fetchAllAssociative();
        } catch (\Doctrine\DBAL\Exception $Exception) {
            throw new QUI\Database\Exception(
                $Exception->getMessage(),
                (int)$Exception->getCode()
            );
        }

        $anonymiseEmailOnly = !empty($this->getArgument('email_only'));

        foreach ($result as $row) {
            $user = $row;
            $userId = (int)$row['id'];

            $this->writeLn("Anonymise user #" . $userId . "...");

            try {
                $userData = [
                    'email' => $userId . $settings['emailHandle']
                ];

                if (!$anonymiseEmailOnly) {
                    $userData['username'] = 'user_' . $userId;
                    $userData['firstname'] = $this->anonymiseString($user['firstname']);
                    $userData['lastname'] = $this->anonymiseString($user['lastname']);
                    $userData['user_agent'] = '';
                    $userData['birthday'] = '1970-01-01';
                }

                $Connection->transactional(function () use (
                    $Connection,
                    $tbl,
                    $tblAddresses,
                    $userData,
                    $userId,
                    $settings,
                    $anonymiseEmailOnly
                ): void {
                    $Connection->update($tbl, $userData, ['id' => $userId]);

                    if ($anonymiseEmailOnly) {
                        return;
                    }

                    $this->writeLn("Anonymise user address(es)...");
                    $addressResult = $Connection->createQueryBuilder()
                        ->select('*')
                        ->from($tblAddresses)
                        ->where(QUI\Utils\Doctrine::quoteIdentifier('uid') . ' = :userId')
                        ->setParameter('userId', $userId)
                        ->executeQuery()
                        ->fetchAllAssociative();

                    foreach ($addressResult as $address) {
                        $Connection->update(
                            $tblAddresses,
                            [
                                'salutation' => $this->anonymiseString($address['salutation']),
                                'firstname' => $this->anonymiseString($address['firstname']),
                                'lastname' => $this->anonymiseString($address['lastname']),
                                'company' => $this->anonymiseString($address['company']),
                                'street_no' => $this->anonymiseString($address['street_no']),
                                'zip' => $this->anonymiseString($address['zip']),
                                'city' => $this->anonymiseString($address['city']),
                                'phone' => '[]',
                                'mail' => '["' . $userId . $settings['emailHandle'] . '"]'
                            ],
                            ['id' => (int)$address['id']]
                        );
                    }
                });

                $this->write(" OK!");
            } catch (\Doctrine\DBAL\Exception $Exception) {
                throw new QUI\Database\Exception(
                    $Exception->getMessage(),
                    (int)$Exception->getCode()
                );
            } catch (Exception $Exception) {
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
    protected function anonymiseString(string $str): string
    {
        $parts = explode(' ', $str);
        $anonStrParts = [];

        foreach ($parts as $part) {
            $anonStrParts[] = mb_substr($part, 0, 1) . '*';
        }

        return implode(' ', $anonStrParts);
    }

    /**
     * Exits the console tool with a success msg and status 0
     *
     * @return never
     */
    protected function exitSuccess(): never
    {
        $this->writeLn("\n\nUsers have been successfully anonymised.");
        $this->writeLn();

        exit(0);
    }

    /**
     * Exits the console tool with an error msg and status 1
     *
     * @param $msg
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
