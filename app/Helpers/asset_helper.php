<?php

/**
 * Retorna a URL do asset com ?v= baseado na data de modificação do arquivo.
 * Força recarregamento no browser sempre que o arquivo mudar no servidor.
 */
function asset_v(string $path): string
{
    $file = FCPATH . $path;
    $v    = file_exists($file) ? filemtime($file) : time();

    return base_url($path) . '?v=' . $v;
}
