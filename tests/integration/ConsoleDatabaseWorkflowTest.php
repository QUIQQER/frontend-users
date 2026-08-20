<?php

namespace QUI\FrontendUsers\Tests\Integration;

use QUI;
use QUI\FrontendUsers\Console\AnonymiseUsers;
use QUI\FrontendUsers\Console\SendUserMails;
use QUI\FrontendUsers\Console\SetUserGroups;
use QUI\FrontendUsers\Tests\Support\DatabaseTestCase;
use ReflectionMethod;
use RuntimeException;

class ConsoleDatabaseWorkflowTest extends DatabaseTestCase
{
    public function testAnonymiseUsersUpdatesOnlySelectedGroupAndAddresses(): void
    {
        $User = $this->createUser(false, [
            'firstname' => 'Alice Example',
            'lastname' => 'Tester Person'
        ]);
        $Group = $this->createGroup();
        $User->addToGroup($Group->getUUID());
        $User->save(QUI::getUsers()->getSystemUser());
        $Address = $User->getStandardAddress();
        $Address->setAttributes([
            'firstname' => 'Alice',
            'lastname' => 'Example',
            'street_no' => 'Main Street 1',
            'zip' => '12345',
            'city' => 'Example City'
        ]);
        $Address->editMail(0, $User->getAttribute('email'));
        $Address->save(QUI::getUsers()->getSystemUser());

        $Tool = new AnonymiseUsers();
        $Method = new ReflectionMethod($Tool, 'anonymiseUsers');
        $Method->invoke($Tool, [
            'groupIds' => [$Group->getUUID()],
            'emailHandle' => '@anonymised.invalid'
        ]);

        $row = self::getConnection()->createQueryBuilder()
            ->select('username', 'email', 'firstname', 'lastname', 'birthday', 'user_agent')
            ->from(QUI\Utils\Doctrine::quoteIdentifier(QUI\Users\Manager::table()))
            ->where('uuid = :uuid')
            ->setParameter('uuid', $User->getUUID())
            ->executeQuery()
            ->fetchAssociative();

        self::assertSame('user_' . $User->getId(), $row['username']);
        self::assertSame($User->getId() . '@anonymised.invalid', $row['email']);
        self::assertSame('A* E*', $row['firstname']);
        self::assertSame('T* P*', $row['lastname']);
        self::assertSame('1970-01-01', $row['birthday']);
        self::assertSame('', $row['user_agent']);

        $addressRow = self::getConnection()->createQueryBuilder()
            ->select('firstname', 'lastname', 'street_no', 'zip', 'city', 'phone', 'mail')
            ->from(QUI\Utils\Doctrine::quoteIdentifier(QUI\Users\Manager::tableAddress()))
            ->where('id = :id')
            ->setParameter('id', $Address->getId())
            ->executeQuery()
            ->fetchAssociative();

        self::assertSame('A*', $addressRow['firstname']);
        self::assertSame('E*', $addressRow['lastname']);
        self::assertSame('M* S* 1*', $addressRow['street_no']);
        self::assertSame('[]', $addressRow['phone']);
        self::assertSame('["' . $User->getId() . '@anonymised.invalid"]', $addressRow['mail']);
    }

    public function testAnonymiseUsersExecuteSupportsEmailOnlyMode(): void
    {
        $User = $this->createUser(false, ['firstname' => 'Keep', 'lastname' => 'Name']);
        $Group = $this->createGroup();
        $User->addToGroup($Group->getUUID());
        $User->save(QUI::getUsers()->getSystemUser());
        $Tool = new class ([$Group->getUUID(), '@email-only.invalid', 'y']) extends AnonymiseUsers {
            private array $inputs;

            public function __construct(array $inputs)
            {
                parent::__construct();
                $this->inputs = $inputs;
                $this->setArgument('email_only', '1');
            }

            public function readInput(): string
            {
                return (string)array_shift($this->inputs);
            }

            public function writeLn(string $msg = '', bool|string $color = false, bool|string $bg = false): void
            {
            }

            public function write(string $msg, bool|string $color = false, bool|string $bg = false): void
            {
            }

            protected function exitSuccess(): never
            {
                throw new RuntimeException('phpunit-success');
            }
        };

        try {
            $Tool->execute();
            self::fail('The anonymisation tool did not reach its success exit.');
        } catch (RuntimeException $Exception) {
            self::assertSame('phpunit-success', $Exception->getMessage());
        }

        $row = self::getConnection()->createQueryBuilder()
            ->select('username', 'email', 'firstname', 'lastname')
            ->from(QUI\Utils\Doctrine::quoteIdentifier(QUI\Users\Manager::table()))
            ->where('uuid = :uuid')
            ->setParameter('uuid', $User->getUUID())
            ->executeQuery()
            ->fetchAssociative();
        self::assertSame($User->getId() . '@email-only.invalid', $row['email']);
        self::assertSame($User->getUsername(), $row['username']);
        self::assertSame('Keep', $row['firstname']);
        self::assertSame('Name', $row['lastname']);
    }

    public function testSetUserGroupsUsesAllFiltersAndAddsTargetGroup(): void
    {
        $User = $this->createUser(true, ['lang' => 'de']);
        $SourceGroup = $this->createGroup();
        $TargetGroup = $this->createGroup();
        $User->addToGroup($SourceGroup->getUUID());
        $User->save(QUI::getUsers()->getSystemUser());

        $Tool = new class ([
            $TargetGroup->getUUID(),
            'n',
            'de',
            $SourceGroup->getUUID(),
            'y'
        ]) extends SetUserGroups {
            private array $inputs;

            public function __construct(array $inputs)
            {
                parent::__construct();
                $this->inputs = $inputs;
            }

            public function readInput(): string
            {
                return (string)array_shift($this->inputs);
            }

            public function writeLn(string $msg = '', bool|string $color = false, bool|string $bg = false): void
            {
            }

            public function write(string $msg, bool|string $color = false, bool|string $bg = false): void
            {
            }

            protected function exitSuccess(): never
            {
                throw new RuntimeException('phpunit-success');
            }
        };

        try {
            $Tool->execute();
            self::fail('The test console tool did not reach its success exit.');
        } catch (RuntimeException $Exception) {
            self::assertSame('phpunit-success', $Exception->getMessage());
        }

        $Reloaded = QUI::getUsers()->get($User->getUUID());
        self::assertTrue($Reloaded->isInGroup($TargetGroup->getUUID()));
    }

    public function testSendUserMailStateFilesAndInvalidRecipientFlow(): void
    {
        $Tool = new class extends SendUserMails {
            public function call(string $method, mixed ...$arguments): mixed
            {
                return (new ReflectionMethod($this, $method))->invoke($this, ...$arguments);
            }

            public function setRecipients(array $recipients): void
            {
                $this->recipients = $recipients;
            }

            public function setMailData(array $mail): void
            {
                $this->mail = $mail;
            }

            public function writeLn(string $msg = '', bool|string $color = false, bool|string $bg = false): void
            {
            }

            public function write(string $msg, bool|string $color = false, bool|string $bg = false): void
            {
            }
        };

        $varDir = QUI::getPackage('quiqqer/frontend-users')->getVarDir();
        $infoFile = $varDir . 'send_user_mails';
        $limitsFile = $varDir . 'send_user_mails_limits';
        @unlink($infoFile);
        @unlink($limitsFile);

        try {
            self::assertSame(['sent' => false, 'sent_date' => false], $Tool->call('getUserInfo', 991001));
            $Tool->call('writeUserInfo', 991001, ['sent' => true, 'sent_date' => 'now']);
            self::assertTrue($Tool->call('getUserInfo', 991001)['sent']);

            $limits = [
                'per24h' => 10,
                'perHour' => 5,
                'perMinute' => 2,
                'start24h' => false,
                'startHour' => false,
                'startMinute' => false,
                'current24h' => 0,
                'currentHour' => 0,
                'currentMinute' => 0
            ];
            $Tool->call('setLimits', $limits);
            self::assertSame($limits, $Tool->call('getLimits'));
            self::assertTrue($Tool->call('isMailAllowed'));
            $Tool->call('updateLimits');
            $updated = $Tool->call('getLimits');
            self::assertSame(1, $updated['currentMinute']);
            self::assertSame(1, $updated['currentHour']);
            self::assertSame(1, $updated['current24h']);

            foreach (['perMinute', 'perHour', 'per24h'] as $limitedPeriod) {
                $limited = $limits;
                $limited['perMinute'] = false;
                $limited['perHour'] = false;
                $limited['per24h'] = false;
                $limited['currentMinute'] = 0;
                $limited['currentHour'] = 0;
                $limited['current24h'] = 0;
                $limited[$limitedPeriod] = 1;
                $currentKey = match ($limitedPeriod) {
                    'perMinute' => 'currentMinute',
                    'perHour' => 'currentHour',
                    default => 'current24h'
                };
                $startKey = match ($limitedPeriod) {
                    'perMinute' => 'startMinute',
                    'perHour' => 'startHour',
                    default => 'start24h'
                };
                $limited[$currentKey] = 1;
                $limited[$startKey] = date('Y-m-d H:i:s');
                $Tool->call('setLimits', $limited);
                self::assertFalse($Tool->call('isMailAllowed'));
            }

            $expired = $limits;
            $expired['startMinute'] = '2000-01-01 00:00:00';
            $expired['startHour'] = '2000-01-01 00:00:00';
            $expired['start24h'] = '2000-01-01 00:00:00';
            $expired['currentMinute'] = 99;
            $expired['currentHour'] = 99;
            $expired['current24h'] = 99;
            $Tool->call('setLimits', $expired);
            $Tool->call('updateLimits');
            $reset = $Tool->call('getLimits');
            self::assertSame(1, $reset['currentMinute']);
            self::assertSame(1, $reset['currentHour']);
            self::assertSame(1, $reset['current24h']);

            $Tool->setMailData([
                'body' => 'Hello [name] [email] [password]',
                'senderMail' => 'sender@example.invalid',
                'senderName' => 'Sender',
                'subject' => 'Subject'
            ]);
            $Tool->setRecipients([[
                'id' => 991002,
                'username' => 'Invalid Recipient',
                'email' => 'not-an-email',
                'firstname' => '',
                'lastname' => ''
            ]]);
            $Tool->call('sendMails');
            self::assertTrue($Tool->call('getUserInfo', 991002)['sent']);
        } finally {
            @unlink($infoFile);
            @unlink($limitsFile);
        }
    }

    public function testSendUserMailsReportsCorruptStateFilesAndLimitDates(): void
    {
        $Tool = new class extends SendUserMails {
            /** @var list<string> */
            private array $messages = [];

            public function call(string $method, mixed ...$arguments): mixed
            {
                return (new ReflectionMethod($this, $method))->invoke($this, ...$arguments);
            }

            public function writeLn(string $msg = '', bool|string $color = false, bool|string $bg = false): void
            {
                $this->messages[] = $msg;
            }

            /** @return list<string> */
            public function getMessages(): array
            {
                return $this->messages;
            }
        };

        $varDir = QUI::getPackage('quiqqer/frontend-users')->getVarDir();
        $infoFile = $varDir . 'send_user_mails';
        $limitsFile = $varDir . 'send_user_mails_limits';
        @unlink($infoFile);
        @unlink($limitsFile);

        try {
            $Tool->call(
                'writeUserInfo',
                991003,
                ['sent' => true, 'sent_date' => 'now', 'error' => "\xB1"]
            );
            self::assertContains('ERROR: Mail status data could not be encoded.', $Tool->getMessages());
            self::assertFileDoesNotExist($infoFile);

            $unencodableLimits = [
                'per24h' => false,
                'perHour' => false,
                'perMinute' => 1,
                'start24h' => false,
                'startHour' => false,
                'startMinute' => "\xB1",
                'current24h' => 0,
                'currentHour' => 0,
                'currentMinute' => 0
            ];
            $Tool->call('setLimits', $unencodableLimits);
            self::assertContains('ERROR: Mail limits could not be encoded.', $Tool->getMessages());
            self::assertFileDoesNotExist($limitsFile);

            file_put_contents($infoFile, '{invalid-json');
            self::assertSame([], $Tool->call('getUserInfo', 991003));
            self::assertContains(
                "ERROR: User info file $infoFile contains invalid JSON.",
                $Tool->getMessages()
            );
            $Tool->call(
                'writeUserInfo',
                991003,
                ['sent' => true, 'sent_date' => 'now']
            );
            self::assertSame('{invalid-json', file_get_contents($infoFile));

            file_put_contents($limitsFile, '{"perMinute":"invalid"}');
            self::assertFalse($Tool->call('getLimits'));
            self::assertContains(
                "ERROR: Limits file $limitsFile has an invalid structure.",
                $Tool->getMessages()
            );

            $limits = [
                'per24h' => false,
                'perHour' => false,
                'perMinute' => 1,
                'start24h' => false,
                'startHour' => false,
                'startMinute' => 'invalid-date',
                'current24h' => 0,
                'currentHour' => 0,
                'currentMinute' => 0
            ];
            file_put_contents($limitsFile, json_encode($limits));
            self::assertFalse($Tool->call('isMailAllowed'));
            $Tool->call('updateLimits');
            self::assertContains(
                'ERROR: Mail limits contain an invalid minute start date.',
                $Tool->getMessages()
            );
        } finally {
            @unlink($infoFile);
            @unlink($limitsFile);
        }
    }

    public function testSendUserMailsExecuteSelectsScopedRecipientsWithoutSendingInvalidMail(): void
    {
        $User = $this->createUser(true, ['lang' => 'de']);
        $Group = $this->createGroup();
        $User->addToGroup($Group->getUUID());
        $User->save(QUI::getUsers()->getSystemUser());
        self::getConnection()->update(
            QUI\Utils\Doctrine::quoteIdentifier(QUI\Users\Manager::table()),
            ['email' => 'not-an-email'],
            ['uuid' => $User->getUUID()]
        );

        $bodyFile = tempnam(sys_get_temp_dir(), 'frontend-users-mail-');
        self::assertNotFalse($bodyFile);
        file_put_contents($bodyFile, 'Hello [name] [email] [password]');
        $varDir = QUI::getPackage('quiqqer/frontend-users')->getVarDir();
        $infoFile = $varDir . 'send_user_mails';
        $limitsFile = $varDir . 'send_user_mails_limits';
        @unlink($infoFile);
        @unlink($limitsFile);

        $Tool = new class ([
            'de',
            'n',
            'de',
            $Group->getUUID(),
            'n',
            'id DESC',
            'PHPUnit subject',
            '',
            '',
            '',
            '',
            '',
            'n',
            'y'
        ]) extends SendUserMails {
            private array $inputs;

            public function __construct(array $inputs)
            {
                parent::__construct();
                $this->inputs = $inputs;
            }

            public function readInput(): string
            {
                return (string)array_shift($this->inputs);
            }

            public function writeLn(string $msg = '', bool|string $color = false, bool|string $bg = false): void
            {
            }

            public function write(string $msg, bool|string $color = false, bool|string $bg = false): void
            {
            }

            protected function exitSuccess(): never
            {
                throw new RuntimeException('phpunit-success');
            }
        };
        $Tool->setArgument('bodyfile', $bodyFile);

        try {
            try {
                $Tool->execute();
                self::fail('The mail tool did not reach its success exit.');
            } catch (RuntimeException $Exception) {
                self::assertSame('phpunit-success', $Exception->getMessage());
            }

            $info = json_decode((string)file_get_contents($infoFile), true);
            self::assertTrue($info[$User->getId()]['sent']);
            self::assertStringContainsString('no valid email syntax', $info[$User->getId()]['error']);
        } finally {
            @unlink($bodyFile);
            @unlink($infoFile);
            @unlink($limitsFile);
        }
    }

    public function testSendUserMailsExecuteConfiguresLimitsAndValidatesTestRecipient(): void
    {
        $bodyFile = tempnam(sys_get_temp_dir(), 'frontend-users-mail-options-');
        self::assertNotFalse($bodyFile);
        file_put_contents($bodyFile, 'Hello [name] [email] [password]');
        $varDir = QUI::getPackage('quiqqer/frontend-users')->getVarDir();
        $infoFile = $varDir . 'send_user_mails';
        $limitsFile = $varDir . 'send_user_mails_limits';
        file_put_contents($infoFile, '{}');
        file_put_contents($limitsFile, json_encode([
            'per24h' => 20,
            'perHour' => 10,
            'perMinute' => 5,
            'start24h' => false,
            'startHour' => false,
            'startMinute' => false,
            'current24h' => 0,
            'currentHour' => 0,
            'currentMinute' => 0
        ]));

        $Tool = new class ([
            'en',
            'y',
            'zz',
            '',
            'y',
            'n',
            'username DESC',
            'y',
            'PHPUnit options subject',
            '',
            '',
            'y',
            '100',
            '50',
            '10',
            '',
            'not-an-email',
            ''
        ]) extends SendUserMails {
            private array $inputs;

            /** @var list<string> */
            private array $messages = [];

            public function __construct(array $inputs)
            {
                parent::__construct();
                $this->inputs = $inputs;
            }

            public function readInput(): string
            {
                return (string)array_shift($this->inputs);
            }

            public function writeLn(string $msg = '', bool|string $color = false, bool|string $bg = false): void
            {
                $this->messages[] = $msg;
            }

            public function write(string $msg, bool|string $color = false, bool|string $bg = false): void
            {
                $this->messages[] = $msg;
            }

            /** @return list<string> */
            public function getMessages(): array
            {
                return $this->messages;
            }

            protected function exitSuccess(): never
            {
                throw new RuntimeException('phpunit-success');
            }
        };
        $Tool->setArgument('bodyfile', $bodyFile);

        try {
            try {
                $Tool->execute();
                self::fail('The mail tool did not reach its success exit.');
            } catch (RuntimeException $Exception) {
                self::assertSame('phpunit-success', $Exception->getMessage());
            }

            $output = implode("\n", $Tool->getMessages());
            self::assertStringContainsString('Generate new password: YES', $output);
            self::assertStringContainsString('Statistics file deleted.', $output);
            self::assertStringContainsString('not-an-email', $output);
            $limits = json_decode((string)file_get_contents($limitsFile), true);
            self::assertSame(100, $limits['per24h']);
            self::assertSame(50, $limits['perHour']);
            self::assertSame(10, $limits['perMinute']);
        } finally {
            @unlink($bodyFile);
            @unlink($infoFile);
            @unlink($limitsFile);
        }
    }
}
