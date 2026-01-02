<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BookingCancelUnpaid extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Bookings:bookingUnpaidCancel15m';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'We cancel unpaid bookings 15 minutes after the creation if they have not been paid';

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
        \App\Models\Booking::cancelUnpaids15m();
    }
}
