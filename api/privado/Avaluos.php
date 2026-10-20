<?php
declare(strict_types=1);

class Avaluos
{
    public const TARIFAS = [
        ['hasta' => 57349000, 'tarifa' => 0.015],
        ['hasta' => 129032000, 'tarifa' => 0.025],
        ['hasta' => INF, 'tarifa' => 0.035],
    ];

    public const TARIFA_MOTOS = 0.015;
    public const TOPE_ELECTRICOS = 0.01;

    private const TILDES = [
        'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Ã' => 'A', 'Å' => 'A',
        'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Ö' => 'O', 'Õ' => 'O',
        'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'Ñ' => 'N', 'Ç' => 'C', 'Ý' => 'Y',
    ];

    private array $d;
    private array $porMarca = [];
    public int $minAnio;
    public int $maxAnio;

    public function __construct(string $archivo)
    {
        $raw = file_get_contents($archivo);
        if ($raw === false) {
            throw new RuntimeException("No se pudo leer $archivo");
        }

        $raw = substr($raw, strpos($raw, "\n") + 1);
        $this->d = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $this->minAnio = $this->d['anios'][0];
        $this->maxAnio = end($this->d['anios']);

        foreach ($this->d['filas'] as $i => [$id, $mi, $tipo, $clase, $linea, $cc, $valores]) {
            $marca = $this->d['marcas'][$mi];
            $lineaN = self::normalizar($linea);
            $fila = [
                'key' => $i,
                'marca' => $marca,
                'tipo' => $tipo,
                'clase' => $clase,
                'linea' => $linea,
                'cc' => $cc,
                'valores' => $valores,
                'lineaN' => $lineaN,
                'lineaTok' => explode(' ', $lineaN),
                'generica' => (bool) preg_match('/NO INCLUIDOS/i', $linea),
                'esMoto' => $tipo === 'MOTOCICLETAS Y MOTOCARROS',
                'esElectrico' => $tipo === 'ELECTRICOS' || (bool) preg_match('/ELECTRIC/i', $clase),
            ];
            $this->porMarca[self::normalizar($marca)][] = $fila;
        }
    }

    public function anioFiscal(): int
    {
        return $this->d['anioFiscal'];
    }

    public static function normalizar($s): string
    {
        $s = mb_strtoupper((string) ($s ?? ''), 'UTF-8');
        $s = strtr($s, self::TILDES);
        $s = preg_replace('/[^A-Z0-9.]+/', ' ', $s);
        $s = preg_replace('/([A-Z])(\d)/', '$1 $2', $s);
        $s = preg_replace('/(\d)([A-Z])/', '$1 $2', $s);
        return trim((string) preg_replace('/\s+/', ' ', $s));
    }

    public function idxAnio($modelo): int
    {
        $m = (int) $modelo ?: $this->maxAnio;
        $a = min(max($m, $this->minAnio), $this->maxAnio);
        return (int) array_search($a, $this->d['anios'], true);
    }

    private function tablasPara(array $veh): array
    {
        $clase = self::normalizar($veh['clase'] ?? '');
        $comb = self::normalizar($veh['combustible'] ?? '');
        $tablas = [];

        if (preg_match('/MOTO|CUATRIMOTO|MOTOCARRO|CICLOMOTOR|TRICIMOTO|MOTOTRICICLO|CUADRICICLO/', $clase)) {
            $tablas[] = 'MOTOCICLETAS Y MOTOCARROS';
        } elseif (preg_match('/AMBULANCIA/', $clase)) {
            $tablas[] = 'AMBULANCIAS';
        } elseif (preg_match('/BUS|MICROBUS|BUSETA/', $clase)) {
            $tablas[] = 'PASAJEROS';
        } elseif (preg_match('/CAMION|VOLQUETA|TRACTO|FURGON|GRUA/', $clase) && !preg_match('/CAMIONETA/', $clase)) {
            $tablas[] = 'CARGA';
        } elseif (preg_match('/CAMIONETA|CAMPERO/', $clase)) {
            $tablas[] = 'CAMIONETAS Y CAMPEROS';
            $tablas[] = 'CAMIONETAS DOBLECABINA';
        } elseif (preg_match('/AUTOMOVIL/', $clase)) {
            $tablas[] = 'AUTOMOVILES';
        }

        if (preg_match('/ELECTRIC/', $comb) && !preg_match('/GASOLINA|DIESEL|HIBRID/', $comb)) {
            $tablas[] = 'ELECTRICOS';
        }

        if (preg_match('/HIBRID/', $comb) || (preg_match('/ELECTRIC/', $comb) && preg_match('/GASOLINA|DIESEL/', $comb))) {
            $tablas[] = 'HIBRIDOS';
        }

        if (preg_match('/PUBLIC/', self::normalizar($veh['servicio'] ?? ''))) {
            $tablas[] = 'PASAJEROS';
        }

        return array_unique($tablas);
    }

    private function puntuar(array $veh): array
    {
        $marcaN = self::normalizar($veh['marca'] ?? '');
        $filas = $this->porMarca[$marcaN] ?? null;

        if ($filas === null) {
            $filas = [];
            if ($marcaN !== '') {
                foreach ($this->porMarca as $key => $lista) {
                    if (str_starts_with((string) $key, $marcaN) || str_starts_with($marcaN, (string) $key)) {
                        array_push($filas, ...$lista);
                    }
                }
            }
        }

        if (!$filas) {
            return [];
        }

        $marcaTok = explode(' ', $marcaN);
        $lineaTok = array_values(array_filter(
            explode(' ', self::normalizar($veh['linea'] ?? '')),
            static fn(string $token): bool => $token !== '' && !in_array($token, $marcaTok, true)
        ));
        $cc = (int) ($veh['cilindraje'] ?? 0);
        $tablas = $this->tablasPara($veh);
        $ia = $this->idxAnio($veh['modelo'] ?? null);
        $coupeVeh = (bool) preg_match('/COUPE/', self::normalizar($veh['carroceria'] ?? ''));
        $publico = (bool) preg_match('/PUBLIC/', self::normalizar($veh['servicio'] ?? ''));

        $resultado = [];
        foreach ($filas as $fila) {
            if ($lineaTok) {
                $comunes = 0;
                foreach ($lineaTok as $token) {
                    if (in_array($token, $fila['lineaTok'], true)) {
                        $comunes += 1;
                        continue;
                    }
                    if (strlen($token) >= 2) {
                        foreach ($fila['lineaTok'] as $lineaToken) {
                            if (strlen($lineaToken) >= 2 && (str_starts_with($lineaToken, $token) || str_starts_with($token, $lineaToken))) {
                                $comunes += 0.5;
                                break;
                            }
                        }
                    }
                }

                if ($comunes) {
                    $cobertura = min(1, $comunes / count($lineaTok));
                    $precision = min(1, $comunes / count($fila['lineaTok']));
                    $score = $cobertura * 0.7 + $precision * 0.3;
                    if ($cobertura < 1) {
                        $score -= 0.15;
                    }
                } else {
                    $score = -0.6;
                }
            } else {
                $score = 0.3;
            }

            if ($cc > 0 && $fila['cc'] > 1 && !$fila['esElectrico']) {
                $d = abs($cc - $fila['cc']);
                $score += $d <= 60 ? 0.25 : ($d <= 250 ? 0.05 : -0.3);
            }

            if ($tablas) {
                $score += in_array($fila['tipo'], $tablas, true) ? 0.2 : -0.15;
            }

            $coupeFila = in_array('COUPE', $fila['lineaTok'], true);
            if ($coupeFila !== $coupeVeh && !empty($veh['carroceria'])) {
                $score -= 0.15;
            }

            $taxi = in_array('TAXI', $fila['lineaTok'], true);
            if ($taxi && !$publico) {
                $score -= 0.3;
            }
            if ($publico && $taxi) {
                $score += 0.15;
            }

            if (($fila['valores'][$ia] ?? null) === null) {
                $score -= 0.5;
            }
            if ($fila['generica']) {
                $score -= 1;
            }

            $resultado[] = ['f' => $fila, 'score' => round($score, 3)];
        }

        usort($resultado, fn(array $a, array $b): int => ($b['score'] <=> $a['score']) ?: self::compararTexto($a['f']['linea'], $b['f']['linea']));
        return $resultado;
    }

    private static function compararTexto(string $a, string $b): int
    {
        $ka = self::claveOrden($a);
        $kb = self::claveOrden($b);
        $n = min(count($ka), count($kb));

        for ($i = 0; $i < $n; $i++) {
            if ($ka[$i] !== $kb[$i]) {
                return $ka[$i] <=> $kb[$i];
            }
        }

        return (count($ka) <=> count($kb)) ?: strcmp($a, $b);
    }

    private static function claveOrden(string $s): array
    {
        $s = strtr(mb_strtoupper($s, 'UTF-8'), ['Ñ' => "N\x7F"] + self::TILDES);
        preg_match_all('/\d+|./su', $s, $m);
        $k = [];

        foreach ($m[0] as $t) {
            if (ctype_digit($t)) {
                $k[] = [1, (int) $t];
                continue;
            }
            if ($t === "\x7F") {
                $k[count($k) - 1][2] = 1;
                continue;
            }
            $k[] = ctype_alpha($t) ? [2, ord($t), 0] : [0, mb_ord($t, 'UTF-8')];
        }

        return $k;
    }

    public function emparejar(array $veh): ?array
    {
        $todas = $this->puntuar($veh);
        if (!$todas) {
            return null;
        }

        $ia = $this->idxAnio($veh['modelo'] ?? null);
        $tope = $todas[0]['score'];
        $mejor = $todas[0];

        foreach ($todas as $fila) {
            if ($fila['score'] < $tope - 0.05) {
                break;
            }
            if (is_int($fila['f']['valores'][$ia] ?? null)) {
                $mejor = $fila;
                break;
            }
        }

        return $mejor['f'];
    }

    public function calcular(array $fila, array $veh): array
    {
        $modelo = (int) ($veh['modelo'] ?? 0);
        $ia = $this->idxAnio($modelo);
        $bruto = $fila['valores'][$ia] ?? null;
        $notas = [];
        $base = [
            'modelo' => $this->d['anios'][$ia],
            'estado' => 'ok',
            'avaluo' => null,
            'tarifa' => null,
            'impuesto' => null,
            'tramo' => null,
            'topado' => false,
            'esMoto' => $fila['esMoto'],
        ];

        if ($modelo > $this->maxAnio) {
            return ['estado' => 'nuevo', 'notas' => $notas] + $base;
        }
        if ($modelo && $modelo < $this->minAnio) {
            $notas[] = "Para modelos anteriores a {$this->minAnio} la tabla oficial usa el valor de \"{$this->minAnio} y anteriores\".";
        }
        if ($bruto === null) {
            return ['estado' => 'sin_valor', 'notas' => $notas] + $base;
        }
        if (is_string($bruto)) {
            return ['estado' => 'ilegible', 'bruto' => $bruto, 'notas' => $notas] + $base;
        }

        $avaluo = $bruto * 1000;

        if (self::normalizar($veh['antiguo_clasico'] ?? '') === 'SI') {
            $v2001 = $fila['valores'][0] ?? null;
            if (is_int($v2001)) {
                $avaluo = (int) round($v2001 * 1000 * 0.5);
                $notas[] = 'Vehiculo antiguo o clasico: la base es el 50 % del avaluo del modelo 2001 y anteriores.';
            }
        }

        $servicio = self::normalizar($veh['servicio'] ?? '');
        $publico = (bool) preg_match('/PUBLIC/', $servicio);
        $oficial = (bool) preg_match('/OFICIAL/', $servicio);

        if ($publico || $oficial) {
            return ['estado' => $publico ? 'publico' : 'oficial', 'avaluo' => $avaluo, 'notas' => $notas] + $base;
        }

        $comb = self::normalizar($veh['combustible'] ?? '');
        $electrico = $fila['esElectrico'] || (preg_match('/ELECTRIC/', $comb) && !preg_match('/GASOLINA|DIESEL|HIBRID/', $comb));

        if ($fila['esMoto'] && !$fila['esElectrico'] && $fila['cc'] > 0 && $fila['cc'] <= 125) {
            return ['estado' => 'exento', 'avaluo' => $avaluo, 'impuesto' => 0, 'notas' => $notas] + $base;
        }

        $tramo = null;
        if ($fila['esMoto']) {
            $tarifa = self::TARIFA_MOTOS;
        } else {
            foreach (self::TARIFAS as $i => $t) {
                if ($avaluo <= $t['hasta']) {
                    $tramo = $i;
                    break;
                }
            }
            $tarifa = self::TARIFAS[$tramo]['tarifa'];
        }

        $topado = false;
        if ($electrico && $tarifa > self::TOPE_ELECTRICOS) {
            $tarifa = self::TOPE_ELECTRICOS;
            $topado = true;
        }

        $impuesto = (int) (round($avaluo * $tarifa / 1000) * 1000);

        return [
            'avaluo' => $avaluo,
            'tarifa' => $tarifa,
            'tramo' => $tramo,
            'impuesto' => $impuesto,
            'topado' => $topado,
            'notas' => $notas,
        ] + $base;
    }
}

function calcularImpuestoVehicular(array $info, string $archivo): ?array
{
    $veh = [
        'marca' => $info['marca'] ?? null,
        'linea' => $info['linea'] ?? null,
        'modelo' => (int) ($info['modelo'] ?? 0),
        'clase' => $info['clase'] ?? null,
        'cilindraje' => (int) ($info['cilindraje'] ?? 0),
        'carroceria' => $info['tipoCarroceria'] ?? null,
        'servicio' => $info['tipoServicio'] ?? null,
        'combustible' => $info['tipoCombustible'] ?? null,
        'antiguo_clasico' => $info['antiguoClasico'] ?? null,
    ];

    $avaluos = new Avaluos($archivo);
    $fila = $avaluos->emparejar($veh);
    if (!$fila) {
        return [
            'anioFiscal' => $avaluos->anioFiscal(),
            'estado' => 'no_encontrado',
            'valorImpuesto' => null,
            'mensaje' => 'La marca no esta en la tabla oficial de avaluos.',
        ];
    }

    $calculo = $avaluos->calcular($fila, $veh);

    $mensajes = [
        'nuevo' => 'Modelo posterior a la tabla: el primer ano se liquida con el valor de la factura de compra.',
        'sin_valor' => 'La version no tiene avaluo para este ano modelo en la tabla oficial.',
        'ilegible' => 'El avaluo aparece en notacion cientifica en la tabla oficial; supera mil millones de pesos (tarifa 3,5 %).',
        'publico' => 'Servicio publico: el impuesto lo liquida el municipio, no el departamento.',
        'oficial' => 'Vehiculo oficial: generalmente no paga este impuesto.',
        'exento' => 'Motos de hasta 125 c.c. no pagan impuesto vehicular (Ley 488 de 1998).',
    ];

    return [
        'anioFiscal' => $avaluos->anioFiscal(),
        'estado' => $calculo['estado'],
        'modeloAvaluo' => $calculo['modelo'],
        'avaluo' => $calculo['avaluo'],
        'tarifa' => $calculo['tarifa'],
        'valorImpuesto' => $calculo['impuesto'],
        'topeElectricos' => $calculo['topado'],
        'versionTabla' => [
            'marca' => $fila['marca'],
            'linea' => $fila['linea'],
            'cilindraje' => $fila['cc'],
            'tipo' => $fila['tipo'],
        ],
        'mensaje' => $mensajes[$calculo['estado']] ?? null,
        'notas' => $calculo['notas'],
    ];
}
