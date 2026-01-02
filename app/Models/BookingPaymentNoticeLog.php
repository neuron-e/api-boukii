<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;


class BookingPaymentNoticeLog extends Model
{

    protected $table = 'booking_payment_notice_log';

    protected $fillable = [
        'booking_id',
        'booking_user_id',
        'date'
    ];
    public $timestamps = false;

    public static function checkToNotify($data)
    {
        $bookingId = $data->booking_id
            ?? $data->booking?->id;

        if (!$bookingId) {
            return true;
        }

        $logs = self::where('booking_id', $bookingId)->get();
        $fecha_actual = Carbon::now();

        // Recorremos los logs para ver si se ha enviado anteriormente el email.
        foreach ($logs as $log) {
            /*
                Si han pasado + de 72 horas volvemos a enviar el aviso ya que sera un
                aviso de otro curso/clase diferente dentro de la misma reserva.
            */
            $fecha_log = Carbon::createFromFormat('Y-m-d H:i:s', $log->date);
            $diff_in_hours = $fecha_log->diffInHours($fecha_actual);
            if ($diff_in_hours <= 72) {
                return false;
            }
        }

        return true;
    }
}

