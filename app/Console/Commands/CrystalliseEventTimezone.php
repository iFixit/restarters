<?php

namespace App\Console\Commands;

use App\Party;
use Illuminate\Console\Command;

class CrystalliseEventTimezone extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'event:timezones';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set timezone on past events';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $events = Party::past()->where('timezone', null)->get();

        foreach ($events as $event) {
            $event->timezone = $event->theGroup->timezone;
            $event->save();
        }
    }
}
