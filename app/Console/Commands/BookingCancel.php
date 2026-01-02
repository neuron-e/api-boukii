<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BookingCancel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Bookings:bookingUnpaidCancel';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'We cancel unpaid bookings 48 hours before the start of the course';

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
       // \App\Models\Booking::cancelUnpaids48h();
    }
}
