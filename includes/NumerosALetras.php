<?php
// includes/NumerosALetras.php
function convertirNumerosALetras($num, $moneda = 'USD') {
    $num = str_replace(',', '', $num);
    $pos = strpos($num, '.');
    if ($pos === false) {
        $entero = $num;
        $decimal = '00';
    } else {
        $entero = substr($num, 0, $pos);
        $decimal = substr($num, $pos + 1, 2);
        $decimal = str_pad($decimal, 2, '0', STR_PAD_RIGHT);
    }
    return 'SON: ' . numero_a_letras_basico($entero) . ' ' . $decimal . '/100 ' . $moneda;
}

function numero_a_letras_basico($n) {
    // (Versión simplificada para no llenar mucho espacio, puedes usar una librería completa si prefieres)
    $f = new NumberFormatter("es", NumberFormatter::SPELLOUT);
    return strtoupper($f->format($n));
}
?>