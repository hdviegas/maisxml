<?php

return [
    'title' => 'Manifestação do destinatário',
    'p1' => 'A manifestação do destinatário é o conjunto de eventos que o tomador da NF-e registra perante a SEFAZ (ciência, confirmação, desconhecimento, não realização), após a autorização da nota.',
    'p2' => 'No sistema legado, fluxos como manifestadest.php tratavam da operação em lote sobre chaves selecionadas. Na versão nova, isso exige integração segura com os webservices (certificado, ambiente homologação/produção, retorno cStat).',
    'li1' => 'Somente documentos já autorizados na SEFAZ podem ser manifestados.',
    'li2' => 'A operação é irreversível em alguns casos; o contador deve validar o processo interno.',
    'li3' => 'Tokens de API do portal não substituem assinatura digital A1/A3 nos serviços da Receita.',
    'planned' => 'Esta tela descreve o escopo. A implementação técnica (NFePHP sped-nfe, filas e auditoria) será priorizada quando o ambiente de certificados e políticas de tenant estiver definido.',
    'back_docs' => 'Ir para documentos',
];
