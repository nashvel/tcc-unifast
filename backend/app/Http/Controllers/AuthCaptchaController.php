<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class AuthCaptchaController extends Controller
{
    /**
     * Generate a CAPTCHA image with a random code.
     *
     * Returns a base64-encoded PNG image with session-stored verification code.
     */
    public function __invoke(Request $request): JsonResponse
    {
        // Generate random 6-character alphanumeric code (excluding confusing chars)
        $code = $this->generateCode();
        
        // Store in session for verification (matching AuthController's expected format)
        Session::put('auth_captcha', [
            'code' => $code,
            'expires_at' => now()->addMinutes(5)->timestamp,
        ]);
        
        // Generate image
        $imageData = $this->generateImage($code);
        
        return response()->json([
            'image' => 'data:image/png;base64,' . base64_encode($imageData),
        ]);
    }
    
    /**
     * Generate a random verification code.
     */
    protected function generateCode(): string
    {
        // Use characters that are easy to distinguish
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        
        for ($i = 0; $i < 6; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        return $code;
    }
    
    /**
     * Generate CAPTCHA image using GD library.
     */
    protected function generateImage(string $code): string
    {
        // Image dimensions
        $width = 180;
        $height = 50;
        
        // Create image
        $image = imagecreatetruecolor($width, $height);
        
        // Dark slate background (#1e293b to #334155 gradient)
        $bg1 = imagecolorallocate($image, 30, 41, 59);
        $bg2 = imagecolorallocate($image, 51, 65, 85);
        
        // Fill with gradient
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            $r = (int)(30 + ($ratio * 21));
            $g = (int)(41 + ($ratio * 24));
            $b = (int)(59 + ($ratio * 26));
            $color = imagecolorallocate($image, $r, $g, $b);
            imageline($image, 0, $y, $width, $y, $color);
        }
        
        // Add subtle noise
        for ($i = 0; $i < 80; $i++) {
            $noiseColor = imagecolorallocate($image, rand(50, 80), rand(60, 90), rand(80, 110));
            imagesetpixel($image, rand(0, $width), rand(0, $height), $noiseColor);
        }
        
        // Text colors (light slate)
        $textColors = [
            imagecolorallocate($image, 226, 232, 240), // slate-200
            imagecolorallocate($image, 203, 213, 225), // slate-300
            imagecolorallocate($image, 241, 189, 76),  // accent yellow
        ];
        
        // Draw each character with slight rotation and offset
        $charSpacing = $width / (strlen($code) + 1);
        
        for ($i = 0; $i < strlen($code); $i++) {
            $char = $code[$i];
            $x = $charSpacing * ($i + 1);
            $y = ($height / 2) + rand(-5, 5);
            $angle = rand(-15, 15);
            $color = $textColors[array_rand($textColors)];
            
            // Use built-in font (5 is largest built-in)
            imagettftext(
                $image,
                20, // font size
                $angle,
                (int)$x,
                (int)$y,
                $color,
                $this->getFontPath(),
                $char
            );
        }
        
        // Add distortion lines
        for ($i = 0; $i < 3; $i++) {
            $lineColor = imagecolorallocate($image, rand(80, 120), rand(90, 130), rand(110, 150));
            imageline(
                $image,
                rand(0, $width / 2),
                rand(0, $height),
                rand($width / 2, $width),
                rand(0, $height),
                $lineColor
            );
        }
        
        // Output to string
        ob_start();
        imagepng($image, null, 6); // Compression level 6
        $imageData = ob_get_clean();
        imagedestroy($image);
        
        return $imageData;
    }
    
    /**
     * Get system font path for TTF rendering.
     * Falls back to simple font if TTF not available.
     */
    protected function getFontPath(): string
    {
        // Try common system font paths
        $fontPaths = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/TTF/DejaVuSans-Bold.ttf',
            'C:/Windows/Fonts/arial.ttf',
            'C:/Windows/Fonts/arialbd.ttf',
            storage_path('fonts/DejaVuSans-Bold.ttf'),
        ];
        
        foreach ($fontPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        // Fallback: use GD built-in font (will trigger warning but won't crash)
        return '';
    }
}
