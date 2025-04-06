<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager as Image;

class GitHub extends Controller
{
    protected function makeChart($data1, $barcolor, $title, $fetchNames = true, $ySpan = 1): array
    {
        $data = $data1;
        $new = [];
        if ($fetchNames){
            foreach ($data as $user => $value) {
                $r = Http::withToken(env('GITHUB'))->get("https://api.github.com/users/$user");
                $user = $r['name'] ? $r['name'] : $user;
                $new[$user] = $value;
                arsort($new);
                $data = $new;
            }
        }

        if ($data == []) {

            // Set the image dimensions
            $width = 1000;
            $height = 400;

            // Create a new image instance
            $img = Image::canvas($width, $height, '#FFFFFF');

            // Set the text to be written on the image
            $text = 'REVIEWS DATA MISSING!';

            // Set the font size and color
            $fontSize = 50;
            $color = '#FF0000';

            // Get the text bounds to center it on the image
            $textWidth = Image::make($img)->text($text, 0, 0, function ($font) use ($fontSize) {
                $font->file(env('FONT'));
                $font->size($fontSize);
            })->width();

            $x = ($width - $textWidth) / 2;
            $y = ($height - $fontSize) / 2;

            // Add the text to the image
            $img->text($text, $x, $y, function ($font) use ($fontSize, $color) {
                $font->file(env('FONT'));
                $font->size($fontSize);
                $font->color($color);
            });

            // Save the image to a file
            $img->save(Storage::path('images/')."reviews_$owner-$repo.png");
            Cache::put($cacheKey, ['image' => Storage::get("images/reviews_$owner-$repo.png")], now()->addHours(2));

            return response()->make($img, 200, [
                'Content-Type' => 'image/png',
                'Content-Disposition' => 'inline; filename="reviews.png"',
            ]);
        }

        /*
         * Chart settings and create image
         */

        // Image dimensions
        $imageWidth = 1000;
        $imageHeight = 400;

        // Grid dimensions and placement within image
        $gridTop = 40;
        $gridLeft = 50;
        $gridBottom = 340;
        $gridRight = 850;
        $gridHeight = $gridBottom - $gridTop;
        $gridWidth = $gridRight - $gridLeft;

        // Bar and line width
        $lineWidth = 1;
        $barWidth = 20;

        // Font settings
        $font = env('FONT');
        $fontSize = 10;

        // Margin between label and axis
        $labelMargin = 8;

        // Max value on y-axis
        $yMaxValue = max(array_values($data1));

        // Distance between grid lines on y-axis
        $yLabelSpan = $ySpan;

        // Init image
        $chart = imagecreate($imageWidth, $imageHeight);

        // Setup colors
        $backgroundColor = imagecolorallocate($chart, 255, 255, 255);
        $axisColor = imagecolorallocate($chart, 85, 85, 85);
        $labelColor = $axisColor;
        $gridColor = imagecolorallocate($chart, 212, 212, 212);
        $barColor = imagecolorallocate($chart, $barcolor[0], $barcolor[1], $barcolor[2]);

        imagefill($chart, 0, 0, $backgroundColor);

        imagesetthickness($chart, $lineWidth);

        /*
         * Print grid lines bottom up
         */

        for ($i = 0; $i <= $yMaxValue; $i += $yLabelSpan) {
            $y = $gridBottom - $i * $gridHeight / $yMaxValue;

            // draw the line
            imageline($chart, $gridLeft, $y, $gridRight, $y, $gridColor);

            // draw right aligned label
            $labelBox = imagettfbbox($fontSize, 0, $font, strval($i));
            $labelWidth = $labelBox[4] - $labelBox[0];

            $labelX = $gridLeft - $labelWidth - $labelMargin;
            $labelY = $y + $fontSize / 2;

            imagettftext($chart, $fontSize, 0, $labelX, $labelY, $labelColor, $font, strval($i));
        }

        /*
         * Draw x- and y-axis
         */

        imageline($chart, $gridLeft, $gridTop, $gridLeft, $gridBottom, $axisColor);
        imageline($chart, $gridLeft, $gridBottom, $gridRight, $gridBottom, $axisColor);

        /*
         * Draw the bars with labels
         */

        $barSpacing = $gridWidth / count($data);
        $itemX = $gridLeft + $barSpacing / 2;

        foreach ($data as $key => $value) {
            // Draw the bar
            $x1 = $itemX - $barWidth / 2;
            $y1 = $gridBottom - $value / $yMaxValue * $gridHeight;
            $x2 = $itemX + $barWidth / 2;
            $y2 = $gridBottom - 1;

            imagefilledrectangle($chart, $x1, $y1, $x2, $y2, $barColor);

            // Draw the label
            $labelBox = imagettfbbox($fontSize, 0, $font, $key);
            $labelWidth = $labelBox[4] - $labelBox[0];

            $labelX = $itemX - $labelWidth / 2;
            $labelY = $gridBottom + $labelMargin + $fontSize;

            imagettftext($chart, $fontSize, 0, $labelX, $labelY, $labelColor, $font, $key);

            $itemX += $barSpacing;
        }

        imagettftext($chart, $fontSize, 0, $gridRight - $gridWidth + $gridWidth / 4, $gridTop - 10, $labelColor, $font, $title.Date::now()->toDateTimeString());

        return [$new, $chart];
    }

    protected function saveChart(mixed $chart, $title, $owner, $repo, string $cacheKey): void
    {

        imagepng($chart, Storage::path('images/')."{$title}$owner-$repo.png");

        Cache::put($cacheKey, ['image' => Storage::get("images/pull_requests_$owner-$repo.png")], now()->addHours(2));
    }

    protected function getTotalPagesFromHeaderLinks(string $headerLinks): int
    {
        $links = \GuzzleHttp\Psr7\Header::parse($headerLinks);

        foreach ($links as $link) {
            if (isset($link['rel']) && $link['rel'] === 'last') {
                $urlParts = parse_url($link[0]);
                parse_str($urlParts['query'], $queryParams);
                if (isset($queryParams['page'])) {
                    return (int) $queryParams['page'];
                }
            }
        }

        return 1;
    }
}
