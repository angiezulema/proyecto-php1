<?php


$host = '0.0.0.0';


$puerto = 5000;


$socket_maestro = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

// Permite reutilizar el puerto
socket_set_option($socket_maestro, SOL_SOCKET, SO_REUSEADDR, 1);

// Asociar socket al host y puerto
socket_bind($socket_maestro, $host, $puerto);


socket_listen($socket_maestro);

echo "Servidor TCP iniciado en $host:$puerto\n";
echo "Esperando clientes...\n";

// clientes conectados
$clientes = [$socket_maestro];

while (true) {

    // actividad en clientes
    $lectura = $clientes;
    $escritura = null;
    $excepcion = null;

    if (socket_select($lectura, $escritura, $excepcion, null) < 1) continue;

    foreach ($lectura as $socket_actual) {

        // nueva conexion
        if ($socket_actual == $socket_maestro) {

            $nuevo_cliente = socket_accept($socket_maestro);
            $clientes[] = $nuevo_cliente;

            socket_getpeername($nuevo_cliente, $ip_cliente);
            echo "Nuevo cliente conectado desde: $ip_cliente\n";

            $menu = "\n--- BIENVENIDO AL CHAT TCP ---\n" .
                    "Escribe algo para chatear o '4' para salir.\n";

            socket_write($nuevo_cliente, $menu, strlen($menu));
        } 
        
        // mensaje de un cliente
        else {

            $bytes = @socket_recv($socket_actual, $buffer, 2048, 0);
            
            // cliente desconectado
            if ($bytes == 0) {

                socket_getpeername($socket_actual, $ip_cliente);
                echo "Cliente desconectado: $ip_cliente\n";

                $key = array_search($socket_actual, $clientes);
                unset($clientes[$key]);

                socket_close($socket_actual);

            } else {

                $mensaje = trim($buffer);
                socket_getpeername($socket_actual, $ip_remitente);

                if ($mensaje == '4') {

                    socket_close($socket_actual);
                    $key = array_search($socket_actual, $clientes);
                    unset($clientes[$key]);

                } 
                
                // Enviar mensaje a todos los clientes
                elseif ($mensaje != '') {

                    $formato = "[" . date('H:i') . "] $ip_remitente: " . $mensaje . "\n";

                    foreach ($clientes as $cliente_destino) {

                        if ($cliente_destino !== $socket_maestro && $cliente_destino !== $socket_actual) {
                            socket_write($cliente_destino, $formato, strlen($formato));
                        }
                    }
                }
            }
        }
    }
}
