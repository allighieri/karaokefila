<?php
/**
* Exibe o conteúdo de uma variável com formatação visual similar ao dd() do Laravel.
*
* @param mixed $var A variável a ser exibida.
* @return void
*/
function dump_and_format($var) {
echo '<style>
    .dump-container {
        background-color: #1c1c1c;
        color: #ccc;
        font-family: monospace;
        padding: 1rem;
        margin: 1rem 0;
        border-radius: 5px;
        overflow-x: auto;
        white-space: pre-wrap;
        word-wrap: break-word;
        border: 1px solid #444;
    }
    .dump-container pre {
        margin: 0;
        white-space: pre-wrap;
        word-wrap: break-word;
    }
    .dump-container .string { color: #d09a24; }
    .dump-container .int { color: #64b5f6; }
    .dump-container .bool { color: #f06292; }
    .dump-container .array { color: #9ccc65; }
    .dump-container .null { color: #ffab40; }
</style>';

echo '<div class="dump-container">';
    echo '<pre>';
    ob_start();
    var_dump($var);
    $output = ob_get_clean();
    // Adiciona cores básicas para alguns tipos de dados
    $output = preg_replace('/string\((.*?)\)/', '<span class="string">string($1)</span>', $output);
    $output = preg_replace('/int\((.*?)\)/', '<span class="int">int($1)</span>', $output);
    $output = preg_replace('/bool\((.*?)\)/', '<span class="bool">bool($1)</span>', $output);
    $output = preg_replace('/array/', '<span class="array">array</span>', $output);
    $output = preg_replace('/NULL/', '<span class="null">NULL</span>', $output);
    echo $output;
    echo '</pre>';
    echo '</div>';
}