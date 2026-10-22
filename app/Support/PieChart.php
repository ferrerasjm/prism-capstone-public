<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Computes solid pie-wedge SVG paths for a set of labeled values, shared
 * between the report web pages and the PDF export so both render identical
 * charts from the same math (dompdf's SVG support doesn't include
 * conic-gradient, so wedges are drawn as explicit <path> arcs).
 */
final class PieChart
{
    /**
     * @param  iterable<array{label:string, value:int|float, color:string}>  $raw
     * @return Collection<int, array{label:string, value:int|float, color:string, pct:float, path:?string}>
     */
    public static function segments(iterable $raw, float $cx = 90, float $cy = 90, float $r = 80): Collection
    {
        $segments = collect($raw)->filter(fn (array $segment) => $segment['value'] > 0)->values();
        $total = (float) $segments->sum('value');

        if ($total <= 0) {
            return collect();
        }

        $angle = -90.0;

        return $segments->map(function (array $segment) use (&$angle, $total, $cx, $cy, $r) {
            $fraction = $segment['value'] / $total;
            $sweep = $fraction * 360;
            $start = $angle;
            $end = $angle + $sweep;
            $angle = $end;

            $isFullCircle = round($sweep, 4) >= 360;
            $path = null;

            if (! $isFullCircle) {
                $largeArc = $sweep > 180 ? 1 : 0;
                $x1 = $cx + $r * cos(deg2rad($start));
                $y1 = $cy + $r * sin(deg2rad($start));
                $x2 = $cx + $r * cos(deg2rad($end));
                $y2 = $cy + $r * sin(deg2rad($end));

                $path = sprintf(
                    'M%.3F,%.3F L%.3F,%.3F A%.3F,%.3F 0 %d,1 %.3F,%.3F Z',
                    $cx, $cy, $x1, $y1, $r, $r, $largeArc, $x2, $y2
                );
            }

            return array_merge($segment, [
                'label' => $segment['label'],
                'value' => $segment['value'],
                'color' => $segment['color'],
                'pct' => round($fraction * 100, 1),
                'path' => $path,
            ]);
        });
    }
}
