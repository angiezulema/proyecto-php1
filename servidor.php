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
$datos_temporales = []; // NUEVO: Para guardar N y la Matriz A temporalmente en el paso 2

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
            
            // SOLUCIÓN 1: Usar spl_object_id en lugar de (int)
            $id = spl_object_id($nuevo_cliente); 
            
            $estado_clientes[$id] = 'MENU'; // Estado inicial
            
            socket_getpeername($nuevo_cliente, $ip);
            echo "Cliente conectado: $ip\n";
            socket_write($nuevo_cliente, $menu_principal, strlen($menu_principal));
        } 
        // DATOS DE UN CLIENTE EXISTENTE
        else {
            $datos = @socket_read($socket_actual, 2048, PHP_NORMAL_READ);
            $id = spl_object_id($socket_actual); // SOLUCIÓN 1

            if ($datos === false || trim($datos) == '') {
                // Desconexión
                unset($clientes[array_search($socket_actual, $clientes)]);
                unset($estado_clientes[$id]);
                unset($datos_temporales[$id]);
                socket_close($socket_actual);
                continue;
            }

            $input = trim($datos);
            $estado = $estado_clientes[$id] ?? 'MENU';

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
                        unset($datos_temporales[$id]);
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
            
            // --- INICIO SOLUCIÓN 2: LÓGICA DE MATRICES POR ESTADOS ---
            elseif ($estado == 'ESPERANDO_N_MATRIZ') {
                $n = (int)$input;
                if ($n > 0 && $n <= 10) { 
                    $datos_temporales[$id]['n'] = $n;
                    $estado_clientes[$id] = 'ESPERANDO_MATRIZ_A';
                    $msg = "Ingresa los " . ($n*$n) . " valores de la MATRIZ A separados por comas: \n";
                    socket_write($socket_actual, $msg, strlen($msg));
                } else {
                    $resultado = "Valor de N inválido.\n" . $menu_principal;
                    $estado_clientes[$id] = 'MENU';
                    socket_write($socket_actual, $resultado, strlen($resultado));
                }
            }
            elseif ($estado == 'ESPERANDO_MATRIZ_A') {
                $n = $datos_temporales[$id]['n'];
                $valores = explode(',', $input);
                if (count($valores) == ($n*$n)) {
                    $datos_temporales[$id]['matrizA'] = $valores;
                    $estado_clientes[$id] = 'ESPERANDO_MATRIZ_B';
                    $msg = "Ingresa los " . ($n*$n) . " valores de la MATRIZ B separados por comas: \n";
                    socket_write($socket_actual, $msg, strlen($msg));
                } else {
                    $msg = "Error: Ingresaste " . count($valores) . " valores, se esperaban " . ($n*$n) . ".\nIngresa de nuevo la MATRIZ A: \n";
                    socket_write($socket_actual, $msg, strlen($msg));
                }
            }
            elseif ($estado == 'ESPERANDO_MATRIZ_B') {
                $n = $datos_temporales[$id]['n'];
                $valoresB = explode(',', $input);
                
                if (count($valoresB) == ($n*$n)) {
                    $valoresA = $datos_temporales[$id]['matrizA'];
                    
                    // Convertir a matrices 2D
                    $A = []; $B = []; $C = [];
                    for ($i = 0; $i < $n; $i++) {
                        for ($j = 0; $j < $n; $j++) {
                            $A[$i][$j] = (int)$valoresA[$i * $n + $j];
                            $B[$i][$j] = (int)$valoresB[$i * $n + $j];
                        }
                    }

                    // Multiplicar
                    for ($i = 0; $i < $n; $i++) {
                        for ($j = 0; $j < $n; $j++) {
                            $C[$i][$j] = 0;
                            for ($k = 0; $k < $n; $k++) {
                                $C[$i][$j] += $A[$i][$k] * $B[$k][$j];
                            }
                        }
                    }

                    $resultado = "\n--- Resultado de la Multiplicación ---\n";
                    for ($i = 0; $i < $n; $i++) {
                        $resultado .= implode("\t", $C[$i]) . "\n";
                    }
                    
                    $estado_clientes[$id] = 'MENU';
                    unset($datos_temporales[$id]); // Limpiar memoria
                    $resultado .= $menu_principal;
                    socket_write($socket_actual, $resultado, strlen($resultado));
                    
                } else {
                    $msg = "Error: Ingresaste " . count($valoresB) . " valores, se esperaban " . ($n*$n) . ".\nIngresa de nuevo la MATRIZ B: \n";
                    socket_write($socket_actual, $msg, strlen($msg));
                }
            }
            // --- FIN LÓGICA DE MATRICES ---

            elseif ($estado == 'CHAT') {
                if (strtolower($input) == 'salir') {
                    $estado_clientes[$id] = 'MENU';
                    socket_write($socket_actual, "Saliendo del chat...\n" . $menu_principal, strlen($menu_principal) + 22);
                } else {
                    socket_getpeername($socket_actual, $ip_remitente);
                    
                    // SOLUCIÓN 3: Imprimir en la consola del servidor para que tú lo veas
                    echo "CHAT -> [$ip_remitente dice]: $input\n";

                    // Hacer Broadcast a los demás
                    $msg_chat = "[$ip_remitente dice]: $input\n";
                    foreach ($clientes as $cliente_destino) {
                        if ($cliente_destino != $socket_maestro && $cliente_destino != $socket_actual) {
                            $id_destino = spl_object_id($cliente_destino); // SOLUCIÓN 1
                            $estado_destino = $estado_clientes[$id_destino] ?? '';
                            
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
