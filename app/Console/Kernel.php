<?php

namespace Crater\Console;

use Crater\Models\CompanySetting;
use Crater\Models\RecurringInvoice;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        Commands\ResetApp::class,
        Commands\UpdateCommand::class,
        Commands\CreateTemplateCommand::class,
        Commands\InstallModuleCommand::class,
        Commands\ProcessPendingVerifactuSubmissions::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        if (\Storage::disk('local')->has('database_created')) {
            $schedule->command('check:invoices:status')
            ->daily();

            $schedule->command('check:estimates:status')
            ->daily();

            $schedule->command('verifactu:process-pending --limit=25')
                ->everyFiveMinutes();

            $recurringInvoices = RecurringInvoice::where('status', 'ACTIVE')->get();
            foreach ($recurringInvoices as $recurringInvoice) {
                $timeZone = CompanySetting::getSetting('time_zone', $recurringInvoice->company_id);

                $schedule->call(function () use ($recurringInvoice) {
                    $recurringInvoice->generateInvoice();
                })->cron($recurringInvoice->frequency)->timezone($timeZone);
            }
        }
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
