<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick;
use Intervention\Image\Geometry\Factories\BezierFactory;

class GitHub extends Controller
{
    protected function makeChart($data1, $barcolor, $title, $fetchNames = true, $ySpan = 1): array
    {
        $data = $data1;
        $new = [];
        $manager = new ImageManager(Imagick\Driver::class);
        // Canvas dimensions
        $width = 1000;
        $height = 400;

        if ($fetchNames) {
            foreach ($data as $user => $value) {
                $r = Http::withToken(env('GITHUB'))->get("https://api.github.com/users/$user");
                $user = $r['name'] ?? $user;
                $new[$user] = $value;
            }
            arsort($new);
            $data = $new;
        }

        // If no data, return an error image
        if (empty($data)) {
            $img = $manager->create($width, $height)->fill('#ffffff');
            $img->text('REVIEWS DATA MISSING!', 500, 200, function ($font) {
                $font->file(base_path(env('FONT')));
                $font->size(48);
                $font->color('#ff0000');
                $font->align('center');
                $font->valign('center');
            });
            return [$new, $img];
        }

        // Create the main chart canvas and fill with white background
        $img = $manager->create($width, $height)->fill('#ffffff');

        // Chart bounds
        $gridLeft = 50;
        $gridRight = 850;
        $gridTop = 40;
        $gridBottom = 340;

        $gridWidth = $gridRight - $gridLeft;
        $gridHeight = $gridBottom - $gridTop;

        $yMaxValue = max($data);
        $barWidth = 20;
        $font = base_path(env('FONT'));
        $fontSize = 14;
        $barSpacing = $gridWidth / max(1, count($data));
        $labelMargin = 8;

        // Draw horizontal grid lines and left-side Y-axis labels using bezier curves
        for ($i = 0; $i <= $yMaxValue; $i += $ySpan) {
            $y = $gridBottom - ($i / $yMaxValue) * $gridHeight;

            // Draw a straight horizontal line using three collinear bezier points
            $img->drawBezier(function (BezierFactory $bezier) use ($gridLeft, $gridRight, $y) {
                $bezier->point($gridLeft, $y);
                $bezier->point(($gridLeft + $gridRight) / 2, $y);
                $bezier->point($gridRight, $y);
                $bezier->border('#d4d4d4', 1);
            });

            // Draw left-side y-axis label
            $img->text((string) $i, $gridLeft - 10, $y, function ($fontObj) use ($font, $fontSize) {
                $fontObj->file($font);
                $fontObj->size($fontSize);
                $fontObj->color('#555555');
                $fontObj->align('right');
                $fontObj->valign('middle');
            });
        }

        // Draw the Y-axis as a vertical bezier (from gridTop to gridBottom at gridLeft)
        $img->drawBezier(function (BezierFactory $bezier) use ($gridLeft, $gridTop, $gridBottom) {
            $bezier->point($gridLeft, $gridTop);
            $bezier->point($gridLeft, ($gridTop + $gridBottom) / 2);
            $bezier->point($gridLeft, $gridBottom);
            $bezier->border('#555555', 1);
        });

        // Draw the X-axis as a horizontal bezier (from gridLeft to gridRight at gridBottom)
        $img->drawBezier(function (BezierFactory $bezier) use ($gridLeft, $gridRight, $gridBottom) {
            $bezier->point($gridLeft, $gridBottom);
            $bezier->point(($gridLeft + $gridRight) / 2, $gridBottom);
            $bezier->point($gridRight, $gridBottom);
            $bezier->border('#555555', 1);
        });

        // Draw bars and X-axis labels
        $itemX = $gridLeft + $barSpacing / 2;
        foreach ($data as $label => $value) {
            $barHeight = ($value / $yMaxValue) * $gridHeight;
            $x1 = $itemX - $barWidth / 2;
            $x2 = $itemX + $barWidth / 2;
            $y1 = $gridBottom - $barHeight;
            $y2 = $gridBottom;

            // Draw the bar as a rectangle
            $img->drawRectangle($x1, $y1, function ($rect) use ($x1, $x2, $y2, $y1, $barcolor) {
                $rect->size($x2 - $x1, $y2 - $y1);
                $rect->background("rgb({$barcolor[0]}, {$barcolor[1]}, {$barcolor[2]})");
            });

            // Draw label below the bar
            $img->text($label, $itemX, $gridBottom + $fontSize + $labelMargin, function ($fontObj) use ($font, $fontSize) {
                $fontObj->file($font);
                $fontObj->size($fontSize);
                $fontObj->color('#555555');
                $fontObj->align('center');
            });

            $itemX += $barSpacing;
        }

        // Add chart title and timestamp
        $img->text($title . ' — ' . Date::now()->toDateTimeString(), $width / 2, 20, function ($fontObj) use ($font) {
            $fontObj->file($font);
            $fontObj->size(16);
            $fontObj->color('#555555');
            $fontObj->align('center');
        });

        return [$new, $img];
    }

    protected function saveChart(mixed $chart, $title, $owner, $repo, string $cacheKey): void
    {
        $path = "images/{$title}{$owner}-{$repo}.png";
        $chart->save(storage_path("app/public/{$path}"));
        Cache::put($cacheKey, ['url' => asset("storage/{$path}")], now()->addHours(2));
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
