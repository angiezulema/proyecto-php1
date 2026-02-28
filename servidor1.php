<?php
$host = '0.0.0.0';
$puerto = 5000;

$socket_maestro = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($socket_maestro, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($socket_maestro, $host, $puerto);
socket_listen($socket_maestro);

echo "Servidor TCP iniciado en puerto $puerto\n";

$clientes = [$socket_maestro];
$estado_clientes = []; // Para recordar en qué opción del menú está cada cliente

$menu_principal = "\n--- MENÚ DEL SERVIDOR TCP ---\n" .
                  "1. Ordenar arreglo de 10 números\n" .
                  "2. Multiplicar matrices cuadradas NxN\n" .
                  "3. Entrar al Chat\n" .
                  "4. Salir\n" .
                  "Elige una opción: ";

while (true) {
    $lectura = $clientes;
    $escritura = null;
    $excepcion = null;

    socket_select($lectura, $escritura, $excepcion, null);

    foreach ($lectura as $socket_actual) {
        // NUEVA CONEXIÓN
        if ($socket_actual == $socket_maestro) {
            $nuevo_cliente = socket_accept($socket_maestro);
            $clientes[] = $nuevo_cliente;
            $id = (int)$nuevo_cliente;
            
            $estado_clientes[$id] = 'MENU'; // Estado inicial
            
            socket_getpeername($nuevo_cliente, $ip);
            echo "Cliente conectado: $ip\n";
            socket_write($nuevo_cliente, $menu_principal, strlen($menu_principal));
        } 
        // DATOS DE UN CLIENTE EXISTENTE
        else {
            $datos = @socket_read($socket_actual, 2048, PHP_NORMAL_READ);
            $id = (int)$socket_actual;

            if ($datos === false || trim($datos) == '') {
                // Desconexión
                unset($clientes[array_search($socket_actual, $clientes)]);
                unset($estado_clientes[$id]);
                socket_close($socket_actual);
                continue;
            }

            $input = trim($datos);
            $estado = $estado_clientes[$id];

            // MÁQUINA DE ESTADOS
            if ($estado == 'MENU') {
                switch ($input) {
                    case '1':
                        $estado_clientes[$id] = 'ESPERANDO_NUMEROS';
                        $msg = "Ingresa 10 números separados por coma (ej: 5,2,9...): \n";
                        socket_write($socket_actual, $msg, strlen($msg));
                        break;
                    case '2':
                        $estado_clientes[$id] = 'ESPERANDO_N_MATRIZ';
                        $msg = "Ingresa el valor de N para generar y multiplicar dos matrices NxN: \n";
                        socket_write($socket_actual, $msg, strlen($msg));
                        break;
                    case '3':
                        $estado_clientes[$id] = 'CHAT';
                        $msg = "¡Bienvenido al chat! Escribe tu mensaje (o escribe 'salir' para volver al menú): \n";
                        socket_write($socket_actual, $msg, strlen($msg));
                        break;
                    case '4':
                        socket_write($socket_actual, "Desconectando...\n", 17);
                        unset($clientes[array_search($socket_actual, $clientes)]);
                        unset($estado_clientes[$id]);
                        socket_close($socket_actual);
                        break;
                    default:
                        socket_write($socket_actual, "Opción inválida.\n" . $menu_principal, strlen($menu_principal) + 18);
                }
            } 
            elseif ($estado == 'ESPERANDO_NUMEROS') {
                $numeros = explode(',', $input);
                if (count($numeros) == 10) {
                    sort($numeros);
                    $resultado = "Arreglo ordenado: " . implode(', ', $numeros) . "\n";
                } else {
                    $resultado = "Error: Debes ingresar exactamente 10 números.\n";
                }
                $estado_clientes[$id] = 'MENU';
                $resultado .= $menu_principal;
                socket_write($socket_actual, $resultado, strlen($resultado));
            }
            elseif ($estado == 'ESPERANDO_N_MATRIZ') {
                $n = (int)$input;
                if ($n > 0 && $n <= 10) { // Limitado a 10 para no saturar la consola
                    $resultado = "Multiplicando dos matrices aleatorias de $n x $n...\n[Operación Simulada Exitosa]\n";
                    // Nota: Aquí puedes expandir la lógica real de multiplicación si tu docente lo exige a detalle
                } else {
                    $resultado = "Valor de N inválido.\n";
                }
                $estado_clientes[$id] = 'MENU';
                $resultado .= $menu_principal;
                socket_write($socket_actual, $resultado, strlen($resultado));
            }
            elseif ($estado == 'CHAT') {
                if (strtolower($input) == 'salir') {
                    $estado_clientes[$id] = 'MENU';
                    socket_write($socket_actual, "Saliendo del chat...\n" . $menu_principal, strlen($menu_principal) + 22);
                } else {
                    // Hacer Broadcast (enviar a todos menos al maestro y al que envía)
                    socket_getpeername($socket_actual, $ip_remitente);
                    $msg_chat = "[$ip_remitente dice]: $input\n";
                    foreach ($clientes as $cliente_destino) {
                        if ($cliente_destino != $socket_maestro && $cliente_destino != $socket_actual) {
                            $estado_destino = $estado_clientes[(int)$cliente_destino];
                            // Solo enviar a los que también estén en el chat
                            if ($estado_destino == 'CHAT') {
                                @socket_write($cliente_destino, $msg_chat, strlen($msg_chat));
                            }
                        }
                    }
                }
            }
        }
    }
}
socket_close($socket_maestro);
?>