<?php

namespace App\Services;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;     // ✅ v5
use Endroid\QrCode\RoundBlockSizeMode;       // ✅ v5

class QrCodeService
{
    public function makeForPublicToken(string $url, string $token): string
    {
        $qr = QrCode::create($url)
            ->setEncoding(new Encoding('UTF-8'))
            ->setErrorCorrectionLevel(ErrorCorrectionLevel::High)   // ✅
            ->setSize(360)
            ->setMargin(16)
            ->setRoundBlockSizeMode(RoundBlockSizeMode::Margin);    // ✅

        $writer = new SvgWriter();
        $svg    = $writer->write($qr)->getString();

        $dir = storage_path('app/public/qrcodes');
        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        file_put_contents($path = $dir.'/'.$token.'.svg', $svg);

        return 'storage/qrcodes/'.$token.'.svg';
    }
}
