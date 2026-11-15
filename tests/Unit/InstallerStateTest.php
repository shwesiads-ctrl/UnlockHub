<?php

namespace Tests\Unit;

use App\Support\InstallerState;
use PHPUnit\Framework\TestCase;

class InstallerStateTest extends TestCase
{
    private string $env;
    private string $lock;

    protected function setUp(): void
    {
        $this->env = tempnam(sys_get_temp_dir(), 'uh-env-');
        $this->lock = $this->env.'.installed';
    }

    protected function tearDown(): void
    {
        @unlink($this->env);
        @unlink($this->lock);
    }

    public function test_configured_site_stays_locked_without_a_database_or_marker(): void
    {
        file_put_contents($this->env, "APP_KEY=base64:qa-test-key\nDB_CONNECTION=mysql\nDB_HOST=unreachable.invalid\n");
        $this->assertTrue(InstallerState::isLocked($this->lock, $this->env));
        // SQLite and other drivers must also be protected, not just MySQL.
        file_put_contents($this->env, "APP_KEY=\"base64:qa-test-key\"\nDB_CONNECTION=sqlite\n");
        $this->assertTrue(InstallerState::isLocked($this->lock, $this->env));
    }

    public function test_fresh_install_is_available_but_claim_is_exclusive_and_permanent(): void
    {
        file_put_contents($this->env, "APP_KEY=\"\"\n");
        $this->assertFalse(InstallerState::isLocked($this->lock, $this->env));
        InstallerState::claim($this->lock);
        $this->assertTrue(InstallerState::isLocked($this->lock, $this->env));
        $original = file_get_contents($this->lock);
        try {
            InstallerState::claim($this->lock);
            $this->fail('Concurrent installer was allowed');
        } catch (\RuntimeException) {
            $this->assertSame($original, file_get_contents($this->lock));
        }
    }
}
