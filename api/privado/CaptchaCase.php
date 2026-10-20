<?php
/**
 * Recupera mayúsculas/minúsculas del texto devuelto por CapSolver (que siempre
 * viene en minúsculas) analizando la geometría de cada glyph en la imagen del
 * captcha (fuente serif bold, tamaño fijo: altura de mayúscula y≈13-15,
 * altura x y≈26-28, línea base y≈61).
 */
class CaptchaCase
{
    const DARK      = 128; // umbral de binarización
    const ROW_MIN   = 3;   // píxeles oscuros mínimos por fila para ignorar ruido
    const TALL_Y    = 21;  // top < TALL_Y => glyph alcanza altura de mayúscula
    const XHEIGHT_Y = 26;  // inicio aproximado de la altura x
    const UNIDAD    = 38;  // ancho típico (px) de una letra

    // Ancho relativo aproximado por carácter (para repartir glyphs pegados)
    private static function peso(string $c): float
    {
        if (strpos('ijlt', $c) !== false) return 0.55;
        if (strpos('fr', $c) !== false)  return 0.65;
        if (strpos('mw', $c) !== false)  return 1.4;
        return 1.0;
    }

    private array $ink = []; // ink[x][y] => bool
    private int $w;
    private int $h;

    public function __construct(string $png)
    {
        $im = imagecreatefromstring($png);
        $this->w = imagesx($im);
        $this->h = imagesy($im);
        for ($x = 0; $x < $this->w; $x++) {
            for ($y = 0; $y < $this->h; $y++) {
                $this->ink[$x][$y] = (imagecolorat($im, $x, $y) & 0xFF) < self::DARK;
            }
        }
    }

    private function colCount(int $x): int
    {
        return count(array_filter($this->ink[$x]));
    }

    /** Divide la imagen en exactamente $n regiones [x0, x1] */
    public function segmentar(string $texto): array
    {
        $n = strlen($texto);
        $cols = [];
        for ($x = 0; $x < $this->w; $x++) $cols[$x] = $this->colCount($x);

        // Segmentos por columnas vacías (ignorando columnas con 1-2 píxeles de ruido)
        $segs = [];
        $in = false;
        for ($x = 0; $x < $this->w; $x++) {
            $on = $cols[$x] > 2;
            if ($on && !$in) { $s = $x; $in = true; }
            if (!$on && $in) { if ($x - $s > 4) $segs[] = [$s, $x - 1]; $in = false; }
        }
        if ($in) $segs[] = [$s, $this->w - 1];
        if (!$segs) return [];

        // Asignación segmentos <-> letras por programación dinámica.
        // Cada "unidad" es: 1 segmento con k letras pegadas, o r segmentos (letra partida) con 1 letra.
        $pesos = array_map([self::class, 'peso'], str_split($texto));
        $m = count($segs);
        $span = fn($i, $j) => $segs[$j][1] - $segs[$i][0] + 1; // ancho de segs i..j
        $esp  = fn($i, $k) => array_sum(array_slice($pesos, $i, $k)) * self::UNIDAD;
        $INF = PHP_FLOAT_MAX;
        $dp = array_fill(0, $m + 1, array_fill(0, $n + 1, $INF));
        $bk = [];
        $dp[0][0] = 0;
        for ($i = 0; $i < $m; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if ($dp[$i][$j] === $INF) continue;
                for ($k = 1; $j + $k <= $n; $k++) {           // 1 segmento -> k letras
                    $c = $dp[$i][$j] + abs($span($i, $i) - $esp($j, $k)) + ($k > 1 ? 6 * ($k - 1) : 0);
                    if ($c < $dp[$i + 1][$j + $k]) { $dp[$i + 1][$j + $k] = $c; $bk[$i + 1][$j + $k] = [$i, $j]; }
                }
                for ($r = 2; $i + $r <= $m; $r++) {           // r segmentos -> 1 letra
                    $c = $dp[$i][$j] + abs($span($i, $i + $r - 1) - $esp($j, 1)) + 6 * ($r - 1);
                    if ($c < $dp[$i + $r][$j + 1]) { $dp[$i + $r][$j + 1] = $c; $bk[$i + $r][$j + 1] = [$i, $j]; }
                }
            }
        }
        if ($dp[$m][$n] === $INF) return [];

        $unidades = [];
        for ([$i, $j] = [$m, $n]; $i > 0;) {
            [$pi, $pj] = $bk[$i][$j];
            array_unshift($unidades, [$segs[$pi][0], $segs[$i - 1][1], $pj, $j - $pj]);
            [$i, $j] = [$pi, $pj];
        }

        $regiones = [];
        foreach ($unidades as [$a, $b, $ci, $k]) {
            // cortar la unidad en k partes buscando columnas de poca tinta
            $sub = array_slice($pesos, $ci, $k);
            $sumSub = array_sum($sub);
            $cortes = [$a];
            $acum = 0;
            for ($j = 0; $j < $k - 1; $j++) {
                $acum += $sub[$j];
                $esperado = $a + ($b - $a + 1) * $acum / $sumSub;
                $prev = end($cortes);
                $mejor = (int)round($esperado); $mejorCost = PHP_FLOAT_MAX;
                for ($x = (int)($esperado - 15); $x <= (int)($esperado + 15); $x++) {
                    if ($x <= $prev + 12 || $x >= $b - 12) continue;
                    $cost = $cols[$x] + abs($x - $esperado) * 0.5;
                    if ($cost < $mejorCost) { $mejorCost = $cost; $mejor = $x; }
                }
                $cortes[] = $mejor;
            }
            $cortes[] = $b + 1;
            for ($j = 0; $j < $k; $j++) $regiones[] = [$cortes[$j], $cortes[$j + 1] - 1];
        }
        return $regiones;
    }

    /** Características de una región */
    public function features(int $a, int $b): array
    {
        $rows = [];
        for ($y = 0; $y < $this->h; $y++) {
            $c = 0;
            for ($x = $a; $x <= $b; $x++) if ($this->ink[$x][$y]) $c++;
            $rows[$y] = $c;
        }
        $top = $bot = null;
        foreach ($rows as $y => $c) if ($c >= self::ROW_MIN) { $top ??= $y; $bot = $y; }
        $top ??= $this->h; $bot ??= 0;

        // hueco vertical (punto de la i/j)
        $gap = false;
        for ($y = $top + 1; $y < self::XHEIGHT_Y + 4 && $y < $bot; $y++) {
            if ($rows[$y] < 2) { $gap = true; break; }
        }

        // densidad de tinta en la banda entre altura de mayúscula y altura x
        $y0 = 15; $y1 = 24; $tinta = 0;
        $colsBanda = 0;
        for ($x = $a; $x <= $b; $x++) {
            $c = 0;
            for ($y = $y0; $y <= $y1; $y++) if ($this->ink[$x][$y]) $c++;
            $tinta += $c;
            if ($c >= 4) $colsBanda++;
        }
        $ancho = $b - $a + 1;
        return [
            'top'   => $top,
            'bot'   => $bot,
            'ancho' => $ancho,
            'gap'   => $gap,
            'dens'  => round($tinta / ($ancho * ($y1 - $y0 + 1)), 2),
            'cobert'=> round($colsBanda / $ancho, 2),
        ];
    }

    /** Decide mayúscula/minúscula por carácter */
    public static function decidir(string $c, array $f): string
    {
        if (!ctype_alpha($c)) return $c;
        // minúsculas sin ascendente no tienen tinta en la banda superior
        $tall = $f['dens'] >= 0.12;
        $up = strtoupper($c);

        if (strpos('acegmnopqrsuvwxyz', $c) !== false) return $tall ? $up : $c;
        if ($c === 'i' || $c === 'j') return $f['gap'] ? $c : $up;
        if ($c === 't') return $f['top'] < 18 ? $up : $c;
        // b d f h k l: ambos llegan arriba; minúscula = solo el asta en la banda superior
        return $f['cobert'] >= 0.45 ? $up : $c;
    }

    public function corregir(string $texto): string
    {
        $texto = strtolower($texto);
        $reg = $this->segmentar($texto);
        if (count($reg) !== strlen($texto)) return $texto;
        $out = '';
        foreach (str_split($texto) as $i => $c) {
            $out .= self::decidir($c, $this->features(...$reg[$i]));
        }
        return $out;
    }
}
