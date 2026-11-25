<?php

return [
    'import' => [
        'empty_xml' => 'Arquivo XML vazio.',
        'invalid_xml' => 'XML inválido ou corrompido.',
        'missing_infnfe' => 'Não foi encontrado infNFe no XML (não parece NF-e).',
        'missing_id' => 'Atributo Id ausente em infNFe.',
        'invalid_chave' => 'Chave da NF-e inválida no Id do infNFe.',
        'duplicate_chave' => 'Esta chave de documento já foi importada.',
        'duplicate_chave_other_org' => 'Esta chave já existe em outra organização.',
        'xml_not_for_organization' => 'O XML não pertence a nenhuma filial desta empresa (emitente/destinatário).',
        'chave_emitter_mismatch' => 'A chave de acesso não corresponde ao CNPJ/CPF do emitente.',
        'branch_forbidden' => 'Filial não pertence às organizações do usuário.',
        'invalid_event' => 'Evento de NF-e inválido (chave ausente ou malformada).',
        'not_an_event' => 'O XML não é um evento de NF-e.',
        'storage_failed' => 'Falha ao gravar arquivo no armazenamento.',
        'done' => 'Importação concluída.',
        'partial_errors' => 'Alguns arquivos não foram importados.',
    ],
];
