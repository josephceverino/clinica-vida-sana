<?php
$ubicacion = (require __DIR__ . '/../../../config/contacto_publico.php')['ubicacion'];
$latitud = $ubicacion['latitud'];
$longitud = $ubicacion['longitud'];
$parametros = http_build_query([
    'bbox' => implode(',', [$longitud - 0.00479, $latitud - 0.0035, $longitud + 0.00521, $latitud + 0.0035]),
    'layer' => 'mapnik',
    'marker' => $latitud . ',' . $longitud,
]);
?>
<!doctype html>
<html lang="es" class="pub-mapa-documento">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Content-Security-Policy" content="default-src 'none'; style-src 'self'; frame-src https://www.openstreetmap.org; base-uri 'none'; form-action 'none'">
    <title><?= htmlspecialchars($ubicacion['nombre'], ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="../css/publico.css">
</head>
<body>
    <iframe src="https://www.openstreetmap.org/export/embed.html?<?= htmlspecialchars($parametros, ENT_QUOTES, 'UTF-8') ?>"
            title="<?= htmlspecialchars('OpenStreetMap: ' . $ubicacion['nombre'], ENT_QUOTES, 'UTF-8') ?>"
            referrerpolicy="no-referrer" sandbox="allow-scripts allow-same-origin allow-popups"></iframe>
</body>
</html>
