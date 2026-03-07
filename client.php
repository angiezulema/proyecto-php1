<?php
$ip_servidor = '127.0.0.1'; // Cambia esto por la IP del servidor Fedora
$puerto = 5000;

$socket = stream_socket_client("tcp://$ip_servidor:$puerto", $errno, $errstr, 30);
if (!$socket) {
    die("Error de conexión: $errstr ($errno)\n");
}

// Importante: No bloquear el socket para que stream_select funcione bien
stream_set_blocking($socket, false);
stream_set_blocking(STDIN, false);

echo "Conectado al servidor. Escribe tu mensaje...\n";

while (true) {
    $lectura = [$socket, STDIN];
    $escritura = null;
    $excepcion = null;

    // Espera activa en ambos canales
    if (stream_select($lectura, $escritura, $excepcion, 0, 200000) > 0) {
        
        foreach ($lectura as $stream) {
            // SI LLEGA ALGO DEL SERVIDOR (Mensajes de otros)
            if ($stream == $socket) {
                $respuesta = fread($socket, 2048);
                if ($respuesta === '' || $respuesta === false) {
                    echo "\nConexión cerrada por el servidor.\n";
                    exit;
                }
                echo $respuesta;
            }
            
            // SI TÚ ESCRIBES ALGO
            if ($stream == STDIN) {
                $input = fgets(STDIN);
                if ($input !== false) {
                    $clean_input = trim($input);
                    if ($clean_input != '') {
                        // Truco visual: borrar lo que escribiste y ponerlo bonito
                        echo "\033[1A\033[K"; 
                        echo "Tú: $clean_input\n";

                        fwrite($socket, $clean_input . "\n");

                        if ($clean_input == '4') exit;
                    }
                }
            }
        }
    }
}
