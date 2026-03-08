<?php

$host = '0.0.0.0';
$puerto = 5000;

$socket_maestro = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($socket_maestro, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($socket_maestro, $host, $puerto);
socket_listen($socket_maestro);

echo "Servidor TCP iniciado en puerto $puerto\n";

$clientes = [$socket_maestro];
$estado_clientes = [];

$menu = "\n--- MENU DEL SERVIDOR ---\n".
        "1. Ordenar arreglo de 10 numeros\n".
        "2. Multiplicar matrices NxN\n".
        "3. Chat\n".
        "4. Salir\n".
        "Seleccione opcion: ";

while(true){

    $lectura = $clientes;
    $escritura = null;
    $excepcion = null;

    socket_select($lectura,$escritura,$excepcion,null);

    foreach($lectura as $socket_actual){

        // NUEVO CLIENTE
        if($socket_actual == $socket_maestro){

            $nuevo_cliente = socket_accept($socket_maestro);
            $clientes[] = $nuevo_cliente;

            $id = (int)$nuevo_cliente;
            $estado_clientes[$id] = 'MENU';

            socket_write($nuevo_cliente,$menu,strlen($menu));
        }

        // CLIENTE EXISTENTE
        else{

            $datos = @socket_read($socket_actual,2048,PHP_NORMAL_READ);
            $id = (int)$socket_actual;

            if($datos === false){

                unset($clientes[array_search($socket_actual,$clientes)]);
                unset($estado_clientes[$id]);
                socket_close($socket_actual);
                continue;
            }

            $input = trim($datos);
            $estado = $estado_clientes[$id];

            // MENU
            if($estado == 'MENU'){

                switch($input){

                    case '1':

                        $estado_clientes[$id] = 'ORDENAR';
                        $msg = "Ingrese 10 numeros separados por coma:\n";
                        socket_write($socket_actual,$msg,strlen($msg));
                        break;

                    case '2':

                        $estado_clientes[$id] = 'MATRIZ';
                        $msg = "Ingrese el valor de N:\n";
                        socket_write($socket_actual,$msg,strlen($msg));
                        break;

                    case '3':

                        $estado_clientes[$id] = 'CHAT';
                        $msg = "Entraste al chat. Escribe 'salir' para volver al menu\n";
                        socket_write($socket_actual,$msg,strlen($msg));
                        break;

                    case '4':

                        socket_write($socket_actual,"Desconectando...\n",17);
                        unset($clientes[array_search($socket_actual,$clientes)]);
                        unset($estado_clientes[$id]);
                        socket_close($socket_actual);
                        break;

                    default:

                        socket_write($socket_actual,"Opcion invalida\n".$menu,strlen($menu)+14);
                }
            }

            // ORDENAR NUMEROS
            elseif($estado == 'ORDENAR'){

                $nums = explode(',',$input);

                if(count($nums)==10){

                    sort($nums);
                    $resultado = "Arreglo ordenado: ".implode(', ',$nums)."\n";
                }
                else{

                    $resultado = "Error: deben ser 10 numeros\n";
                }

                $estado_clientes[$id] = 'MENU';
                $resultado .= $menu;

                socket_write($socket_actual,$resultado,strlen($resultado));
            }

            // MATRICES
            elseif($estado == 'MATRIZ'){

                $n = (int)$input;

                if($n>0 && $n<=5){

                    $A=[];
                    $B=[];
                    $C=[];

                    for($i=0;$i<$n;$i++){
                        for($j=0;$j<$n;$j++){

                            $A[$i][$j]=rand(1,9);
                            $B[$i][$j]=rand(1,9);
                            $C[$i][$j]=0;
                        }
                    }

                    for($i=0;$i<$n;$i++){
                        for($j=0;$j<$n;$j++){
                            for($k=0;$k<$n;$k++){

                                $C[$i][$j]+=$A[$i][$k]*$B[$k][$j];
                            }
                        }
                    }

                    $resultado="Resultado matriz:\n";

                    for($i=0;$i<$n;$i++){

                        $resultado.=implode(' ',$C[$i])."\n";
                    }

                }
                else{

                    $resultado="N invalido\n";
                }

                $estado_clientes[$id]='MENU';
                $resultado.=$menu;

                socket_write($socket_actual,$resultado,strlen($resultado));
            }

            // CHAT
            elseif($estado == 'CHAT'){

                if(strtolower($input)=='salir'){

                    $estado_clientes[$id]='MENU';
                    socket_write($socket_actual,$menu,strlen($menu));
                }

                else{

                    socket_getpeername($socket_actual,$ip);
                    $msg="[$ip]: $input\n";

                    foreach($clientes as $cliente){

                        if($cliente!=$socket_maestro && $cliente!=$socket_actual){

                            $estado_destino=$estado_clientes[(int)$cliente];

                            if($estado_destino=='CHAT'){

                                socket_write($cliente,$msg,strlen($msg));
                            }
                        }
                    }
                }
            }
        }
    }
}
