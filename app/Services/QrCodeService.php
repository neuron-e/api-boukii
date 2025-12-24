<?php

namespace App\Services;

use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeService
{
    public function png(string $data, int $size = 110): string
    {
        return QrCode::format('png')
            ->size($size)
            ->margin(1)
            ->errorCorrection('M')
            ->generate($data);
    }
}
