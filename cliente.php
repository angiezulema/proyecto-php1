<?php
$ip_servidor = '127.0.0.1';


$puerto = 5000;

// Conexión TCP al servidor
$socket = stream_socket_client("tcp://$ip_servidor:$puerto", $errno, $errstr, 30);


if (!$socket) {
    die("Error de conexión: $errstr ($errno)\n");
}

// Modo no bloqueante para enviar y recibir mensajes
stream_set_blocking($socket, false);
stream_set_blocking(STDIN, false);

echo "Conectado al servidor. Escribe tu mensaje...\n";

while (true) {

    // Canales que se van a leer (servidor y teclado)
    $lectura = [$socket, STDIN];
    $escritura = null;
    $excepcion = null;

    if (stream_select($lectura, $escritura, $excepcion, 0, 200000) > 0) {
        
        foreach ($lectura as $stream) {

            
            if ($stream == $socket) {

                $respuesta = fread($socket, 2048);

                
                if ($respuesta === '' || $respuesta === false) {
                    echo "\nConexión cerrada por el servidor.\n";
                    exit;
                }

                
                echo $respuesta;
            }
            
            // Si el usuario escribe algo
            if ($stream == STDIN) {

                $input = fgets(STDIN);

                if ($input !== false) {

                    $clean_input = trim($input);

                    if ($clean_input != '') {

                        // Mostrar mensaje propio
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
