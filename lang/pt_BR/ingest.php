<?php

return [
    'disabled' => 'Ingestão por máquina desabilitada: defina INGEST_TOKEN e/ou INGEST_HMAC_SECRET.',
    'bad_token' => 'Token de ingestão inválido (X-Ingest-Token).',
    'bad_signature' => 'Assinatura HMAC inválida (X-Ingest-Signature).',
    'branch_required' => 'Informe a filial (header X-Branch-Id ou query branch_id).',
    'branch_not_found' => 'Filial não encontrada.',
];
