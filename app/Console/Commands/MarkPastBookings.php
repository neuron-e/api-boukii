<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MarkPastBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Bookings:markPastBookings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'We iterate over bookings to mark as finished the ones that already happened';

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
        //TODO: Check if needed.
    }

}
