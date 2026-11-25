# Integração SIEG — download automatizado de XMLs

Baixa do **Cofre SIEG** as NF-e e NFC-e (e os eventos de cancelamento e CC-e) das empresas que contrataram a SIEG, por exemplo o Sacolão. O download respeita os limites de taxa da API e importa as notas no Mais XML com as mesmas regras de isolamento entre empresas da importação manual.

Fontes oficiais:
- [Portal de clientes](https://integracoes.sieg.com/clientes-sieg/docs)
- [Swagger docs-fiscais](https://api.sieg.com/swagger/docs/docs-fiscais)
- [Swagger autenticação](https://api.sieg.com/swagger/docs/autenticacao)

---

## 1. O que a API oferece (confirmado no Swagger em 2026-11-25)

| Item | Valor |
|------|-------|
| Base | `https://api.sieg.com` |
| JWT (cliente direto) | `POST /api/v1/create-jwt`, header `X-Access-Mode: direct` (mais `X-API-Key`). Válido por **24h**. |
| Rotas protegidas | `Authorization: Bearer {jwt}` + `X-API-Key: {api_key}` + `Content-Type: application/json` |
| API Key | Criada pelo admin em app.sieg.com → Minha conta → *Integrações API SIEG*. É mostrada uma única vez, expira em 3 meses a 5 anos e tem nível `read`, `write` ou `fullAccess` (use **read**). Vale para a **conta SIEG** inteira. |
| `GET /api/v1/consultar-nsu` | `PrimeiroNsu` (o menor disponível, últimos 4 meses) e `UltimoNsu` (contador atual da conta) |
| `POST /api/v1/baixar-xml/nsu/lote` | `{"NumerosNsu":[...]}`, até 50 por chamada. Cobre todos os tipos e os eventos, últimos 90 dias. Retorna ZIP em Base64. |
| `POST /api/v1/baixar-xmls` | `TipoXml` (1 NF-e, 2 CT-e, 3 NFS-e, 4 NFC-e, 5 CF-e), `Take` (≤ 50), `Skip`, `DataEmissaoInicio/Fim`, `DataUploadInicio/Fim`, `CnpjEmit`, `CnpjDest`, `BaixarEventos`. Retorna ZIP em Base64. |
| `POST /api/v1/contar-xmls` | Contagem por tipo. Período obrigatório, de no máximo 3 meses. |
| Eventos | 110111 cancelamento · 110112 cancelamento por substituição · 110110 CC-e · 2102xx manifestação |

**Limites (por API Key):** `baixar-xmls` **2/min**, `contar-xmls` **5/min**, `baixar-xml/nsu` **3.000/min**. Os demais não são documentados.

Como a API sinaliza cada situação:
- **429**: limite atingido. O `Retry-After` pode vir; o recomendado é backoff exponencial.
- **404**: nada encontrado.
- **401**: credencial inválida ou JWT expirado.
- **403**: recurso não liberado para a empresa.
- **5xx**: falha. O 503 indica manutenção; o 504 pede para reduzir o filtro.

**Ainda sem documentação:**
- o nome do campo da resposta que traz o ZIP;
- o campo do token no `create-jwt`;
- se o modo NSU está liberado para a conta do cliente.

O cliente HTTP decodifica de forma tolerante. O comando `sieg:probe` grava **só a estrutura** (chaves, tipos e tamanhos) de uma resposta real, para confirmarmos. Ele exige autorização para chamar a API de verdade.

---

## 2. Estratégia

**Modo `nsu` (padrão, incremental e rápido):**
1. Lê `consultar-nsu`.
2. Se `last_nsu` for nulo, começa em `PrimeiroNsu - 1`.
3. Baixa `last_nsu+1 … UltimoNsu` em lotes de 50 via `baixar-xml/nsu/lote`.
4. Importa cada XML e só então avança `last_nsu` (checkpoint por lote).

Cobre entradas, saídas e eventos de toda a conta numa única sequência.

**Modo `periodo` (alternativa):** usado quando o NSU não está liberado (403), ou para buscar dados com mais de 90 dias.
- Por padrão (`SIEG_PERIODO_SCOPE=account`), consulta a **conta SIEG inteira** por `TipoXml` (1 e 4), sem filtro de CNPJ. São 2 cursores, cerca de 1 requisição por tipo em cada rodada. Notas que não são da empresa são descartadas na importação.
- Com `branch`, itera CNPJ de filial × papel (`dest`/`emit`) × tipo. Use só se a SIEG exigir filtro de CNPJ: a 2 req/min fica muito mais lento.
- Usa janelas de `DataUpload` (`window_hours`, com `window_end` fixado quando a janela abre) com `Take` 50, `Skip` incremental e `BaixarEventos=true`.
- O checkpoint fica em `sieg_sync_cursors` (janela, `skip`, `synced_until`) e é salvo a cada página.
- A primeira janela de cada rodada recua `overlap_hours` (padrão **2 h**) para pegar uploads atrasados.
- **Vazão:** cerca de 100 XMLs/min, ou ~6.000/h. Para NFC-e de varejo em volume, prefira o modo NSU (lotes de 50 a até 60 req/min configurados, contra 3.000/min permitidos).

Em ambos os modos:
- **Duplicatas são ignoradas** (`OnDuplicateImport::Skip`).
- XMLs de outros tipos (CT-e, NFS-e, CF-e) são contados como `ignored` e não travam a fila.
- Eventos vão para `fiscal_events`. O cancelamento marca `xml.cancelada = 1`, inclusive quando a nota chega depois do evento.

### Limites de taxa

`SiegRateLimiter` faz o **espaçamento mínimo** entre chamadas por `(API Key, endpoint)`:
- O intervalo é `60 / (limite × rate_limit_safety)`. Por exemplo, `baixar-xmls` 2/min resulta numa chamada a cada ~33 s.
- O estado fica persistido no cache da aplicação (store `database` em produção) com lock atômico. Assim, execuções paralelas e servidores diferentes respeitam o mesmo ritmo.

Tratamento de erros:
- **429**: cooldown pelo `Retry-After` ou por backoff exponencial com jitter (60 s → 900 s), no máximo 3 tentativas.
- **401**: descarta o JWT em cache, gera outro e tenta uma vez.
- **5xx e timeout**: backoff.
- **Espera além do prazo da execução**: interrompe de forma limpa e retoma do checkpoint na próxima.

### Execução

`php artisan sieg:sync [--account=ID] [--max-runtime=240]`:
- Tem orçamento de tempo, é retomável e usa lock por conta, então não roda duas vezes ao mesmo tempo.
- Registra cada rodada em `sieg_sync_runs` com o status (`ok`, `partial`, `failed`, `skipped`) e os contadores: requisições, baixados, importados, duplicados, ignorados, eventos e erros. `partial` significa que o tempo acabou e a próxima rodada continua do checkpoint.
- Depois de 5 falhas seguidas, pausa a conta por 60 min (`paused_until`). Para rodar mesmo pausada, use `--force`.
- `sieg:accounts` mostra o estado de cada conta e a última rodada.

Agendamento:
- `SIEG_SCHEDULE_ENABLED=true` agenda `sieg:sync` em `SIEG_SCHEDULE_CRON` (padrão a cada 10 min). Precisa do cron do Laravel (`schedule:run` a cada minuto).
- Na **VPS**: cron padrão.
- Na **hospedagem compartilhada da Locaweb**: o agendador só roda entre 2h e 3h. Nesse caso, agende `sieg:sync --max-runtime=3300` direto no painel, uma vez por noite.
- Vazão no modo `periodo`: cerca de 100 XMLs/min, ou seja ~6.000/h.

---

## 3. Contratos (implementação)

```
config/sieg.php                         limites, backoff, sync, schedule
App\Services\Sieg\SiegConnection        (apiKey) → limiterKey = sha256(apiKey)[0..32]

App\Services\Sieg\SiegClient
  consultarNsu(SiegConnection, ?CarbonInterface $deadline): array{primeiro:int, ultimo:int}
  baixarNsuLote(SiegConnection, list<int> $nsus, ?CarbonInterface $deadline): SiegDownloadResult
  baixarXmls(SiegConnection, array $filtros, ?CarbonInterface $deadline): SiegDownloadResult
  contarXmls(SiegConnection, array $filtros, ?CarbonInterface $deadline): array<string,int>
  probe(SiegConnection): array            // só estrutura, nunca conteúdo/segredos
App\Services\Sieg\SiegRateLimiter
  acquire(string $limiterKey, string $endpoint, ?CarbonInterface $deadline): void
  penalize(string $limiterKey, string $endpoint, ?int $retryAfterSeconds, int $attempt): int
App\Services\Sieg\SiegPayloadDecoder
  decode(mixed $body): list<SiegDocument>
App\Services\Sieg\SiegDownloadResult     documents: list<SiegDocument>, notFound: bool
App\Services\Sieg\SiegDocument           xml: string, filename: ?string
App\Services\Sieg\Exceptions\{SiegException, SiegAuthException, SiegForbiddenException,
  SiegRateLimitedException, SiegUnavailableException, SiegDeadlineException}

App\Models\SiegAccount                  organization_id, name, api_key (encrypted), mode, last_nsu,
                                        enabled, paused_until, consecutive_failures, last_* ; connection()
App\Models\SiegSyncCursor               sieg_account_id, cnpj, role, tipo_xml, window_start, skip, synced_until
App\Models\SiegSyncRun                  contadores por execução

App\Services\Nfe\NfeEventParser         parse(string $xml): NfeEventData
App\Services\Nfe\NfeEventImporter       import(int $organizationId, string $xml): string
App\Models\FiscalEvent                  chave, tp_evento, n_seq_evento, dh_evento, c_stat, protocolo, ...
App\Services\Nfe\NfeImportService
  importForOrganization(int $organizationId, string $xml, OnDuplicateImport $mode, string $via): NfeImportOutcome

App\Services\Sieg\SiegSyncService       sync(SiegAccount, CarbonInterface $deadline): SiegSyncRun
Comandos: sieg:account, sieg:accounts, sieg:sync, sieg:probe
```

---

## 4. Operação (cadastro da conta)

1. No SIEG, o admin cria uma API Key com nível **read** para a integração Mais XML.
2. No servidor, rode o comando abaixo. A chave é pedida com entrada oculta, **não** vai para o histórico do shell e **nunca deve ser enviada por chat ou e-mail**.
   ```bash
   php artisan sieg:account {id-ou-cnpj-da-empresa} --name="Sacolão"
   ```
3. `php artisan sieg:probe {conta}` (opcional, uma única chamada real): confirma a autenticação e o formato da resposta.
4. `php artisan sieg:sync --account={conta}`: primeira carga manual; acompanhe com `php artisan sieg:accounts`.
5. Ligue `SIEG_SCHEDULE_ENABLED=true` (VPS) ou agende no painel (hospedagem compartilhada).
