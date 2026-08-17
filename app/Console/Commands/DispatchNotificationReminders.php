<?php

namespace App\Console\Commands;

use App\Services\Notifier;
use Illuminate\Console\Command;

class DispatchNotificationReminders extends Command
{
    protected $signature = 'notifications:reminders';

    protected $description = 'Create inbox reminders for upcoming hearings and due tasks';

    public function handle(): int
    {
        Notifier::dispatchDueReminders();
        $this->info('Notification reminders dispatched.');

        return self::SUCCESS;
    }
}
