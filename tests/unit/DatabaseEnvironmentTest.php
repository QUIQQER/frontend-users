<?php

namespace QUI\FrontendUsers\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QUI\FrontendUsers\Tests\Support\DatabaseEnvironment;

class DatabaseEnvironmentTest extends TestCase
{
    public function testUsesSqliteOutsideGitLabCi(): void
    {
        self::assertSame(
            DatabaseEnvironment::MODE_SQLITE,
            DatabaseEnvironment::determineMode([])
        );
        self::assertSame(
            DatabaseEnvironment::MODE_SQLITE,
            DatabaseEnvironment::determineMode(['GITLAB_CI' => 'false'])
        );
    }

    public function testUsesConfiguredDatabaseInGitLabCi(): void
    {
        self::assertSame(
            DatabaseEnvironment::MODE_CI_DATABASE,
            DatabaseEnvironment::determineMode(['GITLAB_CI' => 'true'])
        );
    }
}
