<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
header('Content-Type: text/html; charset=UTF-8');
header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
error_reporting(E_ALL);
date_default_timezone_set('America/Lima');

require_once('config.php');

$responseData = array();
$placas = array();
$SqlUpdate = "";
$mensajeUpdate = "";
$devicesCount = 0;
$enableUpdate = true;
$placaTemp = "";

$conexion = @new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);

if ($conexion->connect_error) {
    die('Error de conectando a la base de datos: ' . $conexion->connect_error);
}

$sqlQuery = "SELECT `posicionId`, `vehiculoId`, `velocidad`, `satelites`, `rumbo`, `latitud`, `longitud`, `altitud`, `gpsDateTime`, `statusCode`, `ignition`, `odometro`, `horometro`, `nivelBateria`, `estado` FROM `Comsatel` WHERE `estado`='Nuevo' ORDER BY `vehiculoId`, `posicionId` DESC LIMIT 100;";

$resultado = $conexion->query($sqlQuery);

if ($resultado->num_rows > 0) {

    while ($row = $resultado->fetch_array(MYSQLI_ASSOC)) {

        $rID = utf8_encode($row['posicionId']);
        $dID = utf8_encode($row['vehiculoId']);

        $statusCode     = utf8_encode($row['statusCode']);
        $evento         = 0;
        $sendDateTime     = date("Ymdhis");

        switch ($statusCode) {
            case 61714:    // movimiento
                $evento = 1;
                break;
            case 63553:    // panico
                $evento = 2;
                break;
            case 62476: // motor encendido
                $evento = 3;
                break;
            case 62477: // motor apagado
                $evento = 4;
                break;
            case 61722: // exceso de velocidad
                $evento = 5;
                break;
            case 64787: // energia desconectada
                $evento = 6;
                break;
            case 64789: // energia desconectada
                $evento = 7;
                break;
            default:
                $evento = 1;
                break;
        }


        $responseData[] = array(
            "posicionId"        => $rID,
            "vehiculoId"        => $dID,
            "velocidad"            => utf8_encode($row['velocidad']),
            "satelites"            => utf8_encode($row['satelites']),
            "rumbo"                => utf8_encode($row['rumbo']),
            "latitud"            => utf8_encode($row['latitud']),
            "longitud"            => utf8_encode($row['longitud']),
            "altitud"            => utf8_encode($row['altitud']),
            "gpsDateTime"        => utf8_encode($row['gpsDateTime']),
            "sendDateTime"        => $sendDateTime,
            "evento"            => $evento,
            "ignition"            => 2,
            "odometro"            => utf8_encode($row['odometro']),
            "horometro"            => utf8_encode($row['horometro']),
            "nivelBateria"        => utf8_encode($row['nivelBateria']),
            "valido"            => 1,
            "fuente"             => $gpsProvider
        );

        if (strcmp($placaTemp, $dID) != 0) {
            $placaTemp         = $dID;
            $placas[]         = $dID;
        }

        if ($enableUpdate) {
            $SqlUpdate .= "UPDATE `Comsatel` SET `estado`='Sent' WHERE `posicionId`=$rID AND `vehiculoId`='$dID' LIMIT 1;";
        }

        $devicesCount++;
    }
} else {
    die("No se encontraron unidades sin transmisión dentro del intervalo seleccionado");
}

$jsonData     = json_encode($responseData);

$curl         = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL             => $wsURL,
    CURLOPT_POSTFIELDS      => $jsonData,
    CURLOPT_RETURNTRANSFER  => true,
    CURLOPT_MAXREDIRS       => 10,
    CURLOPT_TIMEOUT         => 30,
    CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST   => "POST",
    CURLOPT_HTTPHEADER      => array(
        'Content-Type: application/json;charset=utf-8',
        'Authorization: Basic ' . base64_encode($wsUser . ':' . $wsPass),
        'Aplicacion: SERVICIO_RECOLECTOR'
    ),
));

$response   = curl_exec($curl);
$err        = curl_error($curl);

curl_close($curl);

if ($err) {
    die("cURL Error #:" . $err);
} else {
    if ($enableUpdate) {
        if ($conexion->multi_query($SqlUpdate) === TRUE) {
            $mensajeUpdate    = "Registros Insertados!  ";
        } else {
            $mensajeUpdate    = "Error insertando en la tabla " . $conexion->error;
        }
    }
}

print_r("  <!DOCTYPE html>\n");
print_r("  <html lang=\"en\">\n");
print_r("    <head>\n");
print_r("      <meta charset=\"utf-8\">\n");
print_r("      <meta name=\"viewport\" content=\"width=device-width, initial-scale=1, shrink-to-fit=no\">\n");
print_r("      <link rel=\"stylesheet\" href=\"https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css\" integrity=\"sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u\" crossorigin=\"anonymous\">");
print_r("      <title>WebService Alicorp</title>\n");
print_r("    </head>\n");
print_r("    <body>\n");
print_r("      <div class=\"container\">\n");
print_r("         <nav class=\"navbar navbar-default\">");
print_r("           <div class=\"container-fluid\">");
print_r("             <div class=\"navbar-header\">");
print_r("               <a class=\"navbar-brand\" href=\"#\">");
print_r("                 Unidades a Transmitir: " . json_encode($placas, JSON_PRETTY_PRINT) . "");
print_r("               </a>");
print_r("             </div>");
print_r("           </div>");
print_r("         </nav>");
print_r("         <div class=\"panel panel-default\">");
print_r("           <div class=\"panel-body\">");
print_r("				<pre><code>" . json_encode($jsonData, JSON_PRETTY_PRINT) . "</code></pre>");
print_r("           </div>");
print_r("         </div>");
print_r("         <div class=\"panel panel-default\">");
print_r("           <div class=\"panel-body\">");
print_r("				<pre><code>" . json_encode($response, JSON_PRETTY_PRINT) . "</code></pre>");
print_r("           </div>");
print_r("         </div>");
print_r("      </div>\n");
print_r("    </body>\n");
print_r("  </html>\n");

mysqli_close($conexion);
