<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class GitHub extends Controller
{


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
        $maxY = max($values ?? 0);
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
