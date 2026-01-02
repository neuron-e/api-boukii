<?php

namespace App\Console\Commands;

use App\Models\GiftVoucher;
use App\Models\School;
use App\Mail\GiftVoucherDeliveredMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendGiftVoucherEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gift-voucher:send-email {code : The gift voucher code}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send email for a gift voucher by code';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $code = $this->argument('code');

        // Buscar el gift voucher por código
        $voucher = GiftVoucher::where('code', $code)->first();

        if (!$voucher) {
            $this->error("Gift voucher with code '{$code}' not found.");
            return 1;
        }

        // Cargar la escuela
        $school = School::find($voucher->school_id);

        if (!$school) {
            $this->error("School not found for gift voucher ID {$voucher->id}.");
            return 1;
        }

        // Cargar el voucher relacionado
        $voucher->loadMissing(['voucher']);

        $this->info("Gift Voucher found:");
        $this->table(
            ['Field', 'Value'],
            [
                ['ID', $voucher->id],
                ['Code', $voucher->code],
                ['Amount', $voucher->amount . ' ' . $voucher->currency],
                ['Recipient Email', $voucher->recipient_email],
                ['Recipient Name', $voucher->recipient_name],
                ['Buyer Email', $voucher->buyer_email],
                ['Status', $voucher->status],
                ['Is Paid', $voucher->is_paid ? 'Yes' : 'No'],
                ['Is Delivered', $voucher->is_delivered ? 'Yes' : 'No'],
            ]
        );

        if (!$this->confirm('Do you want to send the email to this gift voucher?')) {
            $this->info('Email sending cancelled.');
            return 0;
        }

        try {
            // Determinar locale del destinatario
            $recipientLocale = $voucher->recipient_locale ?? $voucher->buyer_locale ?? config('app.locale', 'en');

            $this->info("Sending email to recipient: {$voucher->recipient_email} (locale: {$recipientLocale})...");
            $this->info("BCC copy will be sent to: andf1992@gmail.com");

            // Enviar email al destinatario con BCC a Andres
            Mail::to($voucher->recipient_email)
                ->bcc('andf1992@gmail.com')
                ->send(new GiftVoucherDeliveredMail($voucher, $school, $recipientLocale));

            $this->info("✓ Email sent to recipient successfully (BCC sent to andf1992@gmail.com)!");

            // Si hay comprador con email diferente, enviar copia
            if ($voucher->buyer_email && $voucher->buyer_email !== $voucher->recipient_email) {
                $buyerLocale = $voucher->buyer_locale ?? $recipientLocale;

                $this->info("Sending copy to buyer: {$voucher->buyer_email} (locale: {$buyerLocale})...");

                Mail::to($voucher->buyer_email)
                    ->bcc('andf1992@gmail.com')
                    ->send(new GiftVoucherDeliveredMail($voucher, $school, $buyerLocale));

                $this->info("✓ Email sent to buyer successfully (BCC sent to andf1992@gmail.com)!");
            }

            // Marcar como entregado
            $voucher->update(['is_delivered' => true]);

            $this->info('✓ Gift voucher marked as delivered.');

            Log::channel('vouchers')->info('Gift voucher email sent manually via command', [
                'voucher_id' => $voucher->id,
                'code' => $voucher->code,
                'recipient_email' => $voucher->recipient_email,
                'buyer_email' => $voucher->buyer_email,
                'bcc_email' => 'andf1992@gmail.com',
            ]);

            $this->info('All done! The gift voucher email has been sent successfully.');

            return 0;

        } catch (\Exception $e) {
            $this->error('Error sending gift voucher email: ' . $e->getMessage());
            Log::channel('vouchers')->error('Error sending gift voucher email via command', [
                'voucher_id' => $voucher->id,
                'code' => $code,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }
}
