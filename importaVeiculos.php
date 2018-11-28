<?php
require_once('include/ConexaoPdo.php');

$conexao = ConexaoPdo::getConexao();

lerPagina(1, 'minha-loja', $conexao);

function lerPagina($pagina = 1, $nomeLoja, $conexao) {
    $url = 'http://www.riodosulcarros.com.br/loja/'.$nomeLoja.'?&p='.$pagina;

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $retorno = curl_exec($ch);

    curl_close($ch);

    $document = new DOMDocument();
    @$document->loadHTML($retorno);

    $finder = new DOMXPath($document);

    $elementos = $finder->query("//div[contains(@class, 'listagem')]");

    if ($elementos->length > 0) {
        foreach ($elementos as $elemento) {
            $elementosLista = $elemento->getElementsByTagName('li');

            foreach ($elementosLista as $elementoLista) {
                if (substr_count($elementoLista->getAttribute('class'),'item') > 0) {
                    /**
                     * Zerando dados para inserção
                     */
                    $tituloVeiculo = null;
                    $linkVeiculo = null;
                    $valorVeiculo = $elementoLista->getAttribute('data-price');

                    $dadosVeiculos = $elementoLista->getElementsByTagName('div');
                    foreach ($dadosVeiculos as $dadosVeiculo) {
                        if (substr_count($dadosVeiculo->getAttribute('class'),'dados') > 0) {

                            $dadosVeiculosDetalhe = $dadosVeiculo->getElementsByTagName('div');
                            foreach ($dadosVeiculosDetalhe as $dadosVeiculoDetalhe) {
                                if (substr_count($dadosVeiculoDetalhe->getAttribute('class'),'dv') > 0) {

                                    $dadosTituloVeiculos = $dadosVeiculoDetalhe->getElementsByTagName('h2');
                                    foreach ($dadosTituloVeiculos as $dadosTituloVeiculo) {

                                        $dadosTituloVeiculosLink = $dadosTituloVeiculo->getElementsByTagName('a');
                                        foreach ($dadosTituloVeiculosLink as $dadosTituloVeiculoLink) {
                                            $linkVeiculo = $dadosTituloVeiculoLink->getAttribute('href');

                                            $dadosTituloVeiculosSpan = $dadosTituloVeiculoLink->getElementsByTagName('span');
                                            foreach ($dadosTituloVeiculosSpan as $dadosTituloVeiculoSpan) {
                                                $tituloVeiculo .= ' '.trim($dadosTituloVeiculoSpan->nodeValue);
                                            }
                                        }
                                    }
                                }
                            }

                            $sqlInsereVeiculo = 'INSERT INTO veiculo (titulo, valor, link) VALUES (:titulo, :valor, :link)';
                            $stmtInsereVeiculo = $conexao->prepare($sqlInsereImagem);
                            $stmtInsereVeiculo->bindValue(':titulo', $tituloVeiculo);
                            $stmtInsereVeiculo->bindValue(':valor', $valorVeiculo);
                            $stmtInsereVeiculo->bindValue(':link', $linkVeiculo);
                            $stmtInsereVeiculo->execute();

                            $sqlRetornaIdInserido = 'SELECT max(id) as ultimo FROM veiculo';
                            $stmtRetornaIdInserido = $conexao->prepare($sqlRetornaIdInserido);
                            $stmtRetornaIdInserido->execute();

                            $idVeiculo = null;
                            while ($linha = $stmtRetornaIdInserido->fetch(PDO::FETCH_OBJ)) {
                                $idVeiculo = $linha->ultimo;
                            }

                            importaDadosAdicionais($tituloVeiculo, $valorVeiculo, $linkVeiculo, $idVeiculo, $conexao);
                        }
                    }
                }
            }
        }

        lerPagina($pagina + 1, $nomeLoja, $conexao);
    }
}

function importaDadosAdicionais($tituloVeiculo, $valorVeiculo, $linkVeiculo, $idVeiculo, $conexao) {
    $url = 'http://www.riodosulcarros.com.br'.$linkVeiculo;

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $retorno = curl_exec($ch);

    curl_close($ch);

    $document = new DOMDocument();
    @$document->loadHTML($retorno);

    $finder = new DOMXPath($document);

    $elementosImagens = $finder->query("//div[contains(@class, 'pictures')]");

    if ($elementosImagens->length > 0) {
        foreach ($elementosImagens as $elementosImagem) {
            if (substr_count($elementosImagem->getAttribute('class'), 'active') > 0) {
                $enderecoImagem = null;

                $dadosImagensVeiculos = $elementosImagem->getElementsByTagName('img');
                foreach ($dadosImagensVeiculos as $dadosImagemVeiculo) {
                    $enderecoImagem = $dadosImagemVeiculo->getAttribute('data-src-lg');

                    $sqlInsereImagem = 'INSERT INTO veiculo_imagem (id_veiculo, endereco_imagem) VALUES (:id_veiculo, :endereco_imagem)';
                    $stmtInsereImagem = $conexao->prepare($sqlInsereImagem);
                    $stmtInsereImagem->bindValue(':id_veiculo', $idVeiculo);
                    $stmtInsereImagem->bindValue(':endereco_imagem', $enderecoImagem);
                    $stmtInsereImagem->execute();
                }
            }
        }
    }
}