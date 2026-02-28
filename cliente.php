<?php
// Cambia esta IP por la de tu servidor Fedora cuando conectes las otras PCs
$ip_servidor = '127.0.0.1'; 
$puerto = 5000;

$socket = stream_socket_client("tcp://$ip_servidor:$puerto", $errno, $errstr, 30);
if (!$socket) {
    die("Error de conexión: $errstr ($errno)\n");
}

echo "Conectado al servidor. Esperando respuesta...\n";

// Bucle principal interactivo
while (true) {
    $lectura = [$socket, STDIN]; // Escucha al servidor Y a tu teclado
    $escritura = null;
    $excepcion = null;

    stream_select($lectura, $escritura, $excepcion, null);

    foreach ($lectura as $stream) {
        // Si el servidor nos envió algo
        if ($stream == $socket) {
            $respuesta = fread($socket, 2048);
            if ($respuesta === '' || $respuesta === false) {
                echo "\nConexión cerrada por el servidor.\n";
                exit;
            }
            echo $respuesta;
        }
        // Si nosotros escribimos algo en la consola
        elseif ($stream == STDIN) {
            $input = fgets(STDIN);
            // Reemplazar saltos de línea de Windows (\r\n) y Linux (\n)
            $input = str_replace(array("\r", "\n"), '', $input) . "\n"; 
            fwrite($socket, $input);
            if (trim($input) == '4') {
                exit; // Opción 4 es salir
            }
        }
    }
}