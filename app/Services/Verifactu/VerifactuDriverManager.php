<?php

namespace Crater\Services\Verifactu;

use Crater\Services\Verifactu\Drivers\AeatProductionDriver;
use Crater\Services\Verifactu\Drivers\AeatSandboxDriver;
use Crater\Services\Verifactu\Drivers\Contracts\VerifactuDriverInterface;
use Crater\Services\Verifactu\Drivers\ShadowDriver;
use Crater\Services\Verifactu\Drivers\StubDriver;
use InvalidArgumentException;

class VerifactuDriverManager
{
    /**
     * Modes that queue submissions (shadow mode queues but logs only).
     */
    public const SUBMITTING_MODES = ['stub', 'shadow', 'aeat_sandbox', 'aeat_production'];

    /**
     * Resolve the driver for the current configured mode.
     */
    public function forCurrentMode(): VerifactuDriverInterface
    {
        return $this->forMode(config('verifactu.mode', 'shadow'));
    }

    /**
     * Resolve the driver for a given mode name.
     */
    public function forMode(string $mode): VerifactuDriverInterface
    {
        return match ($mode) {
            'shadow'          => app(ShadowDriver::class),
            'stub'            => app(StubDriver::class),
            'aeat_sandbox'    => app(AeatSandboxDriver::class),
            'aeat_production' => app(AeatProductionDriver::class),
            default           => throw new InvalidArgumentException("Unknown VERI*FACTU mode [{$mode}]."),
        };
    }

    /**
     * Resolve a driver by its stored name (as saved in verifactu_submissions.driver).
     */
    public function forDriver(string $driver): VerifactuDriverInterface
    {
        return $this->forMode($driver);
    }

    /**
     * Whether the current mode should queue submissions.
     */
    public function shouldSubmit(): bool
    {
        $mode = config('verifactu.mode', 'shadow');

        return in_array($mode, self::SUBMITTING_MODES, true);
    }

    /**
     * Whether Verifactu is fully disabled.
     */
    public function isOff(): bool
    {
        return config('verifactu.mode', 'shadow') === 'off';
    }
}
