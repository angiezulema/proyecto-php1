<?php

$ip_servidor='127.0.0.1';
$puerto=5000;

$socket=stream_socket_client("tcp://$ip_servidor:$puerto",$errno,$errstr,30);

if(!$socket){
    die("Error conexion\n");
}

stream_set_blocking($socket,false);
stream_set_blocking(STDIN,false);

echo "Conectado al servidor\n";

while(true){

    $lectura=[$socket,STDIN];
    $escritura=null;
    $excepcion=null;

    if(stream_select($lectura,$escritura,$excepcion,0,200000)>0){

        foreach($lectura as $stream){

            if($stream==$socket){

                $respuesta=fread($socket,2048);

                if($respuesta===false || $respuesta===''){

                    echo "Servidor desconectado\n";
                    exit;
                }

                echo $respuesta;
            }

            if($stream==STDIN){

                $input=fgets(STDIN);

                if($input!==false){

                    fwrite($socket,$input);
                }
            }
        }
    }
}
