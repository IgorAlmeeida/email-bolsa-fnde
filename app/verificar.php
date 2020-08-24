<?php

$pdo = new PDO('mysql:host=database-2.c88nbyo6r5kw.us-east-1.rds.amazonaws.com;dbname=petbolsa', "site", "superpetuag1");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 

$array_pessoas = pegar_hash_banco();

for ($i = 0; $i < count($array_pessoas); $i++){
    $json_file = file_get_contents("https://www.fnde.gov.br/digef/rs/spba/publica/pagamento/".$array_pessoas[$i]['hash_pessoa']);

    $obj = json_decode($json_file);
    $programas = $obj->programas;

    $pagamentos = pegar_pagamentos($programas);

    if (verificar_pagamentos_banco($array_pessoas[$i]['id_pessoa'])){
        inserir_pagamento($array_pessoas[$i]['id_pessoa'], $pagamentos[count($pagamentos)-2], true);
        echo(json_encode($pagamentos[count($pagamentos)-2]));
    }
    else{
        inserir_pagamento($array_pessoas[$i]['id_pessoa'], $pagamentos[count($pagamentos)-1], false);
        echo(json_encode($pagamentos[count($pagamentos)-1]));
    }
}

function veriricar_ultimo_pagamento($id_pessoa){
    try{

        $conect = $GLOBALS["pdo"];
        $conect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 

        $conect->beginTransaction();
        $consulta = $conect->prepare("SELECT * FROM bolsa WHERE id_pessoa = :id_pessoa ORDER BY id_bolsa DESC" );
        $consulta->bindParam(':id_pessoa', $id_pessoa, PDO::PARAM_INT);
        $consulta->execute();

        $result =  $consulta->fetch(PDO::FETCH_ASSOC);
        $last_bolsa = $result["bolsa"];

        $conect->commit();
        return $last_bolsa;

    } catch (Exception $e ) {
        $conect->rollBack();
        return $e->getMessage();
    }
}

function inserir_pagamento($id_pessoa, $pagamento, $is_enviado){
    if($is_enviado){
        try{

            $conect = $conect = $GLOBALS["pdo"];
            $conect->beginTransaction();       
            $consulta = $conect->prepare("INSERT INTO bolsa (id_pessoa, bolsa, ordem_bancaria, valor_bolsa, is_enviado)
            VALUES(?,?,?,?,?)");

            $valor = str_replace('.', '', $pagamento->valor);
            $consulta->bindParam( 1, $id_pessoa,PDO::PARAM_INT);
            $consulta->bindParam( 2, $pagamento->referencia, PDO::PARAM_STR);
            $consulta->bindParam( 3, $pagamento->ordermBancaria, PDO::PARAM_STR);
            $consulta->bindParam( 4, $valor, PDO::PARAM_STR);
            $consulta->bindParam( 5, $is_enviado, PDO::PARAM_BOOL);
            $consulta->execute();
    
            $conect->commit();                
            
        } catch ( Exception $e ) {
            $conect->rollBack();
            $e->getMessage();
        }
    }
    else{
        $last_bolsa = veriricar_ultimo_pagamento($id_pessoa);
        if ($last_bolsa == $pagamento->referencia){
            return;
        }
        try{
            $conect = $conect = $GLOBALS["pdo"];  
            $conect->beginTransaction();     
            $consulta = $conect->prepare("INSERT INTO bolsa (id_pessoa, bolsa, ordem_bancaria, valor_bolsa, is_enviado)
            VALUES(?,?,?,?,?)");
            $valor = str_replace('.', '', $pagamento->valor);
            $consulta->bindParam( 1, $id_pessoa, PDO::PARAM_INT);
            $consulta->bindParam( 2, $pagamento->referencia, PDO::PARAM_STR);
            $consulta->bindParam( 3, $pagamento->ordermBancaria, PDO::PARAM_STR);
            $consulta->bindParam( 4, $valor, PDO::PARAM_STR);
            $consulta->bindParam( 5, $is_enviado, PDO::PARAM_BOOL);
            $consulta->execute();
    
            $conect->commit();                
            
        } catch ( Exception $e ) {
            $conect->rollBack();
            $e->getMessage();
        }

    }
} 

function verificar_pagamentos_banco($id_pessoa){
    try{

        $conect = $GLOBALS["pdo"];
        $conect->beginTransaction();
        $consulta = $conect->prepare("SELECT count(id_bolsa) as qtt FROM bolsa WHERE id_pessoa = :id_pessoa");
        $consulta->bindParam(':id_pessoa', $id_pessoa, PDO::PARAM_INT);
        $consulta->execute();

        $result =  $consulta->fetch(PDO::FETCH_ASSOC);
        $qtt = $result["qtt"];

        $conect->commit();

        if (intval($qtt) === intval("0")){
            return true;
        }

        return false;

    } catch (Exception $e ) {
        $conect->rollBack();
        return $e->getMessage();
    }
}

function pegar_hash_banco(){
    
    try{

        $conect = $conect = $GLOBALS["pdo"];    
        $conect->beginTransaction();
        $consulta = $conect->prepare("SELECT * FROM pessoa");
        $consulta->execute();

        $result = [];

        while($linha = $consulta->fetch(PDO::FETCH_ASSOC)){
            $result[] = $linha;
        }   
        
        $conect->commit();

        return $result;

    } catch (Exception $e ) {
        $conect->rollBack();
        return $e->getMessage();
    }
}

function pegar_pagamentos($programas){
    for ($i = 0; $i < count($programas); $i++){
        if($programas[$i]->nome == "PET-ALUNO" || $programas[$i]->nome == "PET-TUTOR"){ 

            $entirades = $programas[$i]->entidades; //pegando as entidades

            $new_json = json_decode(tratar_json_mal_formado(json_encode($entirades, JSON_UNESCAPED_SLASHES))); //alterando as entidades para acessar o campo vaiável

            $entidade = $new_json->pppppppppppppp; //entrando na entidade

            $funcao = $entidade->funcoes; //entrando na funcao

            $funcao_formatada = json_decode(tratar_json_mal_formado_funcao(json_encode($funcao, JSON_UNESCAPED_SLASHES)));

            $pagamentos = $funcao_formatada->pp->pagamentos;

            return $pagamentos;

        }

    }
}

function tratar_json_mal_formado_funcao($string){
    for($i = 0 ; $i < strlen($string); $i++){
        if($string[$i] === chr(34)){
            for ($j = 1 ; $j < 3; $j++){
                if ($string[$i+$j] === chr(34)){
                    break;
                }
                $string[$i+$j] = "p";
            }
        break;
        }
    }
    return $string;    
}


function tratar_json_mal_formado($string){
    for($i = 0 ; $i < strlen($string); $i++){
        if($string[$i] === chr(34)){
            for ($j = 1 ; $j < 15; $j++){
                if ($string[$i+$j] === chr(34)){
                break;
                }
                $string[$i+$j] = "p";
            }
        break;
        }
    }
    return $string;

}













?>