<?php

namespace App\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;

class QrCodeRenderer
{
    /**
     * Render a QR code payload as a PNG binary string.
     */
    public function png(string $data, int $size = 300): string
    {
        $result = (new Builder(
            writer: new PngWriter,
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $size,
            margin: 10,
        ))->build();

        /** @var PngWriter $writer */
        return $result->getString();
    }
}
