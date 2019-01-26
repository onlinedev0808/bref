<?php declare(strict_types=1);

namespace Bref\Test\Runtime;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class PhpRuntimeTest extends TestCase
{
    public function test simple invocation()
    {
        $process = new Process(['sam', 'local', 'invoke', 'PhpFunction', '--no-event', '--region', 'us-east-1']);
        $process->setWorkingDirectory(__DIR__);
        $process->mustRun();
        $result = json_decode(trim($process->getOutput()), true);
        self::assertSame('Hello world', $result, $process->getErrorOutput());
    }
}
