<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Intervention\Image\Drivers\Imagick;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Geometry\Factories\BezierFactory;
use Intervention\Image\ImageManager;

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
        $img->text($title.' — '.Date::now()->toDateTimeString(), $width / 2, 20, function ($fontObj) use ($font) {
            $fontObj->file($font);
            $fontObj->size(16);
            $fontObj->color('#555555');
            $fontObj->align('center');
        });

        return [$new, $img];
    }

    protected function saveChart(mixed $chart, $title, $owner, $repo, string $cacheKey): void
    {
        $directory = storage_path('app/public/images');

        if (! file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = "{$title}{$owner}-{$repo}.png";
        $fullPath = "{$directory}/{$filename}";

        // Save locally (optional)
        $chart->save($fullPath);

        // Encode and cache as raw binary string
        $encoded = (string) $chart->encode(new PngEncoder);

        Cache::put($cacheKey, ['image' => $encoded], now()->addHours(2));
    }

    protected static function getTotalPagesFromHeaderLinks(string $headerLinks): int
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

    protected function respondWithCachedChart(string $cacheKey): ?Response
    {
        $cachedData = Cache::get($cacheKey);
        if ($cachedData && isset($cachedData['image'])) {
            return response($cachedData['image'])
                ->header('Content-Type', 'image/png')
                ->header('Content-Disposition', 'inline; filename="'."$cacheKey.png".'"');
        }

        return null;
    }

    public function serializeMermaidState(string $mermaid, $themeVars): string
    {
        $state = [
            'code' => $mermaid,
            'mermaid' => ['theme' => 'default',
                'themeVariables' => ['xyChart' => $themeVars,
                ],
            ],
        ];
        $json = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        /* Returns
         * "{"code":"xychart-beta\ntitle \"Commits — Example\"\nx-axis [\"ApplebaumIan\"]\ny-axis \"Commits\" 0 --> 1\nbar [1]","mermaid":{"theme":"default"}}"
         */

        //        $json = mb_convert_encoding($json, 'UTF-16', 'UTF-8'); // I believe mermaid.ink requires utf8 encoding and I read somewhere PHP uses utf16 for some reason so I added this as a precaution.

        return $json;
    }

    public function encodeMermaid(string $mermaid): string
    {
        //        $compressed = gzcompress($mermaid, 9);
        $base64 = base64_encode($mermaid);
        $urlSafe = strtr(rtrim($base64, '='), '+/', '-_');

        return $urlSafe;
    }

    public function mermaidUrl(string $mermaid, $barColor): string
    {
        $json = $this->serializeMermaidState($mermaid, [
            'plotColorPalette' => ''.$barColor.'',
        ]);
        $encoded = $this->encodeMermaid($json);
        $url = env('MERMAID', 'https://mermaid.ink')."/img/{$encoded}";

        return $url;
    }

    public function mermaid(array $chartData, $repo, $metric, $showDate = false): string
    {
        $labels = array_keys($chartData); // $chartData['labels'];
        $values = array_values($chartData);
        //        dd($values);
        $labelString = implode(', ', array_map(fn ($l) => '"'.$l.'"', $labels));
        $valueString = implode(', ', $values);
        $maxY = max($values);
        $date = '';
        if ($showDate) {
            $date = Carbon::now()->setTimezone('EST');
            $date = $date->setTimezone('EST')->toDateTimeString();
            $date = "– $date";
        }

        $mermaid = <<<EOT
    xychart-beta
    title "{$metric} — $repo $date"
    x-axis [{$labelString}]
    y-axis "{$metric}" 0 --> {$maxY}
    bar [{$valueString}]
    EOT;

        return $mermaid;
    }
}
