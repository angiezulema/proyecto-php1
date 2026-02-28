<?php
// Configuración de red (0.0.0.0 escucha en cualquier IP que tenga el servidor, DHCP o Estática)
$host = '0.0.0.0';
$puerto = 5000;

// Crear el socket TCP
$socket_maestro = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($socket_maestro, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($socket_maestro, $host, $puerto);
socket_listen($socket_maestro);

echo "Servidor TCP iniciado en $host:$puerto\n";
echo "Esperando clientes (Windows, Linux Mint, Ubuntu)...\n";

// Arreglo para rastrear todos los clientes conectados
$clientes = [$socket_maestro];

while (true) {
    // socket_select modifica el arreglo, así que usamos una copia
    $lectura = $clientes;
    $escritura = null;
    $excepcion = null;

    // Esperar a que haya actividad en alguno de los sockets
    socket_select($lectura, $escritura, $excepcion, null);

    foreach ($lectura as $socket_actual) {
        // 1. Si hay actividad en el socket maestro, es una NUEVA conexión
        if ($socket_actual == $socket_maestro) {
            $nuevo_cliente = socket_accept($socket_maestro);
            $clientes[] = $nuevo_cliente;
            
            socket_getpeername($nuevo_cliente, $ip_cliente);
            echo "Nuevo cliente conectado desde: $ip_cliente\n";

            // Enviar el menú inicial al cliente
            $menu = "\n--- MENÚ DEL SERVIDOR TCP ---\n" .
                    "1. Ordenar arreglo de 10 números\n" .
                    "2. Multiplicación de matrices NxN\n" .
                    "3. Entrar al Chat\n" .
                    "4. Salir\n" .
                    "Elige una opción: ";
            socket_write($nuevo_cliente, $menu, strlen($menu));
        } 
        // 2. Si la actividad es en otro socket, es un cliente enviando DATOS
        else {
            $datos = @socket_read($socket_actual, 1024, PHP_NORMAL_READ);

            // Si el cliente se desconecta
            if ($datos === false || trim($datos) == '') {
                $indice = array_search($socket_actual, $clientes);
                unset($clientes[$indice]);
                socket_close($socket_actual);
                echo "Un cliente se ha desconectado.\n";
                continue;
            }

            $opcion = trim($datos);
            $respuesta = "";

            // Procesar la opción del menú
            switch ($opcion) {
                case '1':
                    $respuesta = "Has elegido Ordenar Arreglo. Envía 10 números separados por coma:\n";
                    // Aquí irá la lógica de ordenamiento
                    break;
                case '2':
                    $respuesta = "Has elegido Multiplicación de Matrices. Envía el valor de N:\n";
                    // Aquí irá la lógica de matrices
                    break;
                case '3':
                    $respuesta = "Has entrado al chat. Escribe un mensaje (o 'salir_chat' para volver al menú):\n";
                    // Aquí irá la lógica de broadcast a otros clientes
                    break;
                case '4':
                    $respuesta = "Desconectando... ¡Adiós!\n";
                    socket_write($socket_actual, $respuesta, strlen($respuesta));
                    $indice = array_search($socket_actual, $clientes);
                    unset($clientes[$indice]);
                    socket_close($socket_actual);
                    continue 2; // Salta a la siguiente iteración del foreach
                default:
                    $respuesta = "Opción no válida. Intenta de nuevo.\nElige una opción (1-4): ";
                    break;
            }

            // Enviar la respuesta de vuelta al cliente
            socket_write($socket_actual, $respuesta, strlen($respuesta));
        }
    }
}

socket_close($socket_maestro);
?>