<?php

return [
    'title' => 'SPED — importação C100',
    'branch' => 'Filial',
    'file' => 'Arquivo SPED (.txt)',
    'select_branch' => 'Filial (escopo da carga)',
    'upload_btn' => 'Importar registro C100',
    'upload_hint' => 'Arquivo texto do SPED Fiscal (Bloco C). Linhas |C100| são lidas; chave 44 posições quando existir.',
    'footer_hint' => 'Na listagem de documentos, coluna “SPED” indica se existe linha C100 com a mesma chave e organização.',
    'empty_file' => 'Arquivo vazio.',
    'import_error' => 'Não foi possível importar o SPED.',
    'lines_imported' => '{0} Nenhuma linha C100 importada|{1} :count linha C100 importada|[2,*] :count linhas C100 importadas',
];
