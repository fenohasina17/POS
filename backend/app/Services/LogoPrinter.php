<?php

namespace App\Services;

class LogoPrinter
{
    /**
     * Convert an image file to ESC/POS raster bitmap commands for printing.
     *
     * @param string $imagePath Path to the image file (PNG, JPG)
     * @return string ESC/POS commands to print the image
     */
    public static function imageToEscPos(string $imagePath): string
    {
        if (!extension_loaded('gd')) {
            throw new \Exception('GD extension is required for image processing.');
        }

        if (!file_exists($imagePath)) {
            throw new \Exception("Image file not found: $imagePath");
        }

        // Load image
        $img = @imagecreatefromstring(file_get_contents($imagePath));
        if (!$img) {
            throw new \Exception("Failed to load image: $imagePath");
        }

        // Convert to grayscale and then to 1-bit bitmap
        $width = imagesx($img);
        $height = imagesy($img);

        // Define max width for the printer (e.g., 384 pixels)
        $maxWidth = 200;

        // Calculate new width and height proportionally if width exceeds maxWidth
        if ($width > $maxWidth) {
            $ratio = $maxWidth / $width;
            $newWidth = $maxWidth;
            $newHeight = (int)($height * $ratio);
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }

        // Adjust newWidth to be multiple of 8 for ESC/POS
        if ($newWidth % 8 !== 0) {
            $newWidth += 8 - ($newWidth % 8);
        }

        // Create a new true color image with adjusted width and height
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Convert to black and white
        imagefilter($resized, IMG_FILTER_GRAYSCALE);
        imagefilter($resized, IMG_FILTER_CONTRAST, -50);

        // Threshold to convert to 1-bit
        for ($y = 0; $y < $newHeight; $y++) {
            for ($x = 0; $x < $newWidth; $x++) {
                $rgb = imagecolorat($resized, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                // Simple threshold
                if ($r > 128) {
                    imagesetpixel($resized, $x, $y, 0xFFFFFF);
                } else {
                    imagesetpixel($resized, $x, $y, 0x000000);
                }
            }
        }

        // ESC/POS raster bitmap command: GS v 0
        // Format: [GS] [v] [0] [m] [xL] [xH] [yL] [yH] [d]...
        // m = 0 (normal)
        // xL, xH = width in bytes (width/8)
        // yL, yH = height in dots

        $widthBytes = $newWidth / 8;
        $xL = $widthBytes & 0xFF;
        $xH = ($widthBytes >> 8) & 0xFF;
        $yL = $newHeight & 0xFF;
        $yH = ($newHeight >> 8) & 0xFF;

        $rasterData = "";

        for ($y = 0; $y < $newHeight; $y++) {
            for ($xByte = 0; $xByte < $widthBytes; $xByte++) {
                $byte = 0;
                for ($bit = 0; $bit < 8; $bit++) {
                    $x = $xByte * 8 + $bit;
                    $color = imagecolorat($resized, $x, $y);
                    // Black pixel = 1, white pixel = 0
                    $bitVal = ($color == 0x000000) ? 1 : 0;
                    $byte |= ($bitVal << (7 - $bit));
                }
                $rasterData .= chr($byte);
            }
        }

        // Build ESC/POS command
        $cmd = "\x1D\x76\x30\x00" . chr($xL) . chr($xH) . chr($yL) . chr($yH) . $rasterData;

        // Free images
        imagedestroy($img);
        imagedestroy($resized);

        return $cmd;
    }
}
