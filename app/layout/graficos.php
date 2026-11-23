<?php
// Gráficos SVG dibujados en el servidor (sin bibliotecas, sin CDN y sin
// JavaScript). Los usa panel/indicadores.php.
//
// Por qué así:
//   - La CSP del sitio ("default-src 'self'") bloquea scripts externos y
//     estilos en línea (style="..."): por eso los tamaños van como
//     atributos de SVG (width, height, x, y) y los colores como clases CSS
//     (panel.css, sección "Indicadores"), que cambian en modo oscuro.
//   - En Fedora con 2 GB de RAM no se carga ninguna librería en el
//     navegador: el SVG ya llega armado.
//   - Cada barra tiene <title> (globito al pasar el mouse) y cada gráfico
//     tiene al lado su tabla de datos, para lectores de pantalla.

/**
 * Número con separador de miles (igual que el resto del panel).
 */
function grafico_numero(int $valor): string
{
    return number_format($valor);
}

/**
 * Porcentaje para mostrar ("12,5 %") o "—" si no se puede calcular.
 */
function grafico_porcentaje(?float $valor): string
{
    return $valor === null ? '—' : str_replace('.', ',', number_format($valor, 1)) . ' %';
}

/**
 * Barras horizontales de UNA serie (cantidad por categoría).
 * $datos: ['Etiqueta' => n, ...]. $total: para el porcentaje (null = sin %).
 * Devuelve HTML: una lista donde cada fila es etiqueta + barra + valor.
 * La barra es un SVG de 100 x 8 (el ancho es el porcentaje del máximo).
 */
function grafico_barras_horizontales(array $datos, ?int $total = null, string $clase_serie = 'ind-serie-1'): string
{
    $maximo = max(array_merge([0], array_values($datos)));
    $html = '<ul class="ind-barras" role="list">';

    foreach ($datos as $etiqueta => $valor) {
        $valor = (int) $valor;
        // Ancho relativo al mayor (el mayor llena la barra). Con valor > 0 se
        // deja un mínimo visible de 1 para que no "desaparezca".
        $ancho = $maximo > 0 ? max($valor > 0 ? 1 : 0, round($valor * 100 / $maximo, 2)) : 0;
        $porcentaje = $total !== null ? indicadores_porcentaje($valor, $total) : null;
        $detalle = grafico_numero($valor) . ($total !== null ? ' (' . grafico_porcentaje($porcentaje) . ')' : '');

        $html .= '<li class="ind-barra-fila">'
            . '<span class="ind-barra-etiqueta">' . e($etiqueta) . '</span>'
            . '<svg class="ind-barra-svg" viewBox="0 0 100 8" preserveAspectRatio="none" aria-hidden="true" focusable="false">'
            . '<rect class="ind-barra-fondo" x="0" y="0" width="100" height="8" rx="1.5"/>'
            . ($ancho > 0
                ? '<rect class="' . e($clase_serie) . '" x="0" y="0" width="' . e((string) $ancho) . '" height="8" rx="1.5">'
                  . '<title>' . e($etiqueta . ': ' . $detalle) . '</title></rect>'
                : '')
            . '</svg>'
            . '<span class="ind-barra-valor">' . e($detalle) . '</span>'
            . '</li>';
    }

    return $html . '</ul>';
}

/**
 * Columnas agrupadas por mes: citas programadas y atenciones registradas.
 * $meses: ['AAAA-MM' => ['citas' => n, 'atenciones' => n], ...].
 * Un solo eje Y (las dos series son "cantidad de registros por mes").
 */
function grafico_columnas_mensuales(array $meses): string
{
    $nombres = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $cantidad = max(1, count($meses));

    // Medidas del dibujo (unidades del viewBox; el SVG se estira al ancho).
    $margen_izq = 34;
    $margen_der = 8;
    $margen_sup = 18;
    $margen_inf = 34;
    // Con pocos meses los grupos se ensanchan: el gráfico mide al menos
    // ~720 unidades, así el texto queda de un tamaño parecido siempre.
    $ancho_grupo = max(56, (int) floor(680 / $cantidad));
    $ancho = $margen_izq + $cantidad * $ancho_grupo + $margen_der;
    $alto = 240;
    $alto_util = $alto - $margen_sup - $margen_inf;

    // Escala "redonda" del eje: 1, 2, 5, 10, 20, 50... por encima del máximo.
    $maximo = 0;
    foreach ($meses as $valores) {
        $maximo = max($maximo, $valores['citas'], $valores['atenciones']);
    }
    $paso = 1;
    foreach ([1, 2, 5, 10, 20, 25, 50, 100, 200, 250, 500, 1000, 2000, 5000, 10000] as $candidato) {
        $paso = $candidato;
        if ($maximo / $candidato <= 5) {
            break;
        }
    }
    $tope = max($paso, (int) ceil($maximo / $paso) * $paso);
    $y = fn (int $valor): float => $margen_sup + $alto_util - ($valor / $tope) * $alto_util;

    $svg = '<svg class="ind-columnas-svg" viewBox="0 0 ' . $ancho . ' ' . $alto . '" role="img" '
        . 'aria-labelledby="ind-evolucion-titulo" focusable="false">';

    // Rejilla y números del eje (discretos)
    for ($valor = 0; $valor <= $tope; $valor += $paso) {
        $linea = round($y($valor), 2);
        $svg .= '<line class="ind-rejilla" x1="' . $margen_izq . '" x2="' . ($ancho - $margen_der)
            . '" y1="' . $linea . '" y2="' . $linea . '"/>'
            . '<text class="ind-eje-texto" x="' . ($margen_izq - 6) . '" y="' . ($linea + 3.5)
            . '" text-anchor="end">' . e(grafico_numero($valor)) . '</text>';
    }

    $i = 0;
    foreach ($meses as $mes => $valores) {
        $x = $margen_izq + $i * $ancho_grupo;
        $centro = $x + $ancho_grupo / 2;
        [$anio, $numero] = explode('-', $mes);
        $nombre_mes = $nombres[(int) $numero - 1];
        $columnas = [
            // [clase, serie, valor, desplazamiento desde el centro del grupo]
            ['ind-serie-1', 'Citas programadas', $valores['citas'], -19],
            ['ind-serie-2', 'Atenciones registradas', $valores['atenciones'], 3],
        ];

        foreach ($columnas as [$clase, $serie, $valor, $desplazamiento]) {
            $arriba = round($y($valor), 2);
            $altura = round($margen_sup + $alto_util - $arriba, 2);
            $titulo = $serie . ' — ' . $nombre_mes . ' ' . $anio . ': ' . grafico_numero($valor);
            // Zona transparente más grande que la barra: más fácil de "tocar".
            $svg .= '<g class="ind-columna"><title>' . e($titulo) . '</title>'
                . '<rect class="ind-zona" x="' . ($centro + $desplazamiento - 2) . '" y="' . $margen_sup
                . '" width="20" height="' . $alto_util . '"/>';
            if ($valor > 0) {
                $svg .= '<rect class="' . $clase . '" x="' . ($centro + $desplazamiento) . '" y="' . $arriba
                    . '" width="16" height="' . max(1, $altura) . '" rx="2"/>';
                // Valor encima de la columna (pocos meses: se lee sin pasar el mouse)
                if ($cantidad <= 12) {
                    $svg .= '<text class="ind-valor-texto" x="' . ($centro + $desplazamiento + 8) . '" y="'
                        . ($arriba - 4) . '" text-anchor="middle">' . e(grafico_numero($valor)) . '</text>';
                }
            }
            $svg .= '</g>';
        }

        $svg .= '<text class="ind-eje-texto" x="' . $centro . '" y="' . ($alto - $margen_inf + 16)
            . '" text-anchor="middle">' . e($nombre_mes) . '</text>'
            . '<text class="ind-eje-texto ind-eje-anio" x="' . $centro . '" y="' . ($alto - $margen_inf + 28)
            . '" text-anchor="middle">' . e($anio) . '</text>';
        $i++;
    }

    // Línea base
    $base = round($y(0), 2);
    $svg .= '<line class="ind-base" x1="' . $margen_izq . '" x2="' . ($ancho - $margen_der)
        . '" y1="' . $base . '" y2="' . $base . '"/>';

    return $svg . '</svg>';
}
