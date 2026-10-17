<?php

namespace QUI\FrontendUsers\Tests\Unit;

use PHPUnit\Framework\TestCase;

class RegistrationTemplateEscapingTest extends TestCase
{
    public function testRegistrationTemplatesEscapeEveryPostedFieldOutput(): void
    {
        $directory = dirname(__DIR__, 2) . '/src/QUI/FrontendUsers/Registrars/Email/';
        foreach (['Control.html', 'Registration.Address.html'] as $template) {
            $source = file_get_contents($directory . $template);
            self::assertIsString($source);
            preg_match_all('/\{\$fields\[[^}]+\}/', $source, $outputs);
            self::assertNotEmpty($outputs[0], $template);

            foreach ($outputs[0] as $output) {
                self::assertStringContainsString("|escape:'html'", $output, $template . ': ' . $output);
                self::assertStringNotContainsString("['password']", $output, $template);
            }
        }
    }
}
