<?php

namespace Crater\Services\Verifactu;

use Crater\Models\VerifactuInstallation;
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
     * Resolve the driver for the global configured mode (fallback / system-wide use).
     */
    public function forCurrentMode(): VerifactuDriverInterface
    {
        return $this->forMode(config('verifactu.mode', 'shadow'));
    }

    /**
     * Resolve the driver for a specific company installation.
     * The global 'off' kill-switch still takes precedence.
     */
    public function forInstallation(VerifactuInstallation $installation): VerifactuDriverInterface
    {
        $mode = $installation->mode ?? config('verifactu.mode', 'shadow');

        return $this->forMode($mode);
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
     * Whether submissions should be queued for a given installation.
     * Falls back to the global config mode if installation has no mode set.
     */
    public function shouldSubmitForInstallation(VerifactuInstallation $installation): bool
    {
        $mode = $installation->mode ?? config('verifactu.mode', 'shadow');

        return $installation->enabled
            && $installation->submission_enabled
            && in_array($mode, self::SUBMITTING_MODES, true);
    }

    /**
     * Whether the current global mode should queue submissions.
     */
    public function shouldSubmit(): bool
    {
        $mode = config('verifactu.mode', 'shadow');

        return in_array($mode, self::SUBMITTING_MODES, true);
    }

    /**
     * Whether Verifactu is fully disabled at the system level (global kill-switch).
     */
    public function isOff(): bool
    {
        return config('verifactu.mode', 'shadow') === 'off';
    }

    /**
     * Whether Verifactu is disabled for a specific installation
     * (either by global kill-switch or by the company's own mode/enabled flag).
     */
    public function isOffForInstallation(VerifactuInstallation $installation): bool
    {
        return $this->isOff()
            || ! $installation->enabled
            || ($installation->mode ?? '') === 'off';
    }
}
