<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific test case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the `pest()` function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * Builds a 44-digit access key whose positions 7-20 hold the emitter document
 * (left-padded with zeros to 14 digits), as the importer requires.
 */
function chave_for(string $fill, string $emitDoc = '16657751000130'): string
{
    $emit = str_pad(preg_replace('/\D/', '', $emitDoc), 14, '0', STR_PAD_LEFT);

    return '352406'.$emit.str_repeat($fill, 24);
}

function sample_party_tag(string $doc): string
{
    $digits = preg_replace('/\D/', '', $doc);

    return strlen($digits) === 11 ? "<CPF>{$digits}</CPF>" : "<CNPJ>{$digits}</CNPJ>";
}

function sample_nfe_xml(string $chave, ?string $emitCnpj = null, ?string $destCnpj = null): string
{
    $emitTag = sample_party_tag($emitCnpj ?? '16657751000130');
    $destTag = sample_party_tag($destCnpj ?? '12345678901');

    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<nfeProc xmlns="http://www.portalfiscal.inf.br/nfe">
<NFe>
<infNFe Id="NFe{$chave}" versao="4.00">
<ide>
  <cUF>35</cUF>
  <natOp>Venda</natOp>
  <mod>55</mod>
  <serie>1</serie>
  <nNF>99</nNF>
  <dhEmi>2024-06-01T12:00:00-03:00</dhEmi>
  <tpNF>1</tpNF>
  <idDest>1</idDest>
  <cMunFG>3550308</cMunFG>
  <tpImp>1</tpImp>
  <tpEmis>1</tpEmis>
  <cDV>0</cDV>
  <tpAmb>1</tpAmb>
  <finNFe>1</finNFe>
  <indFinal>1</indFinal>
  <indPres>1</indPres>
  <procEmi>0</procEmi>
  <verProc>MaisXML</verProc>
</ide>
<emit>
  {$emitTag}
  <xNome>Emitente Demonstracao LTDA</xNome>
  <CRT>3</CRT>
  <enderEmit>
    <xLgr>Rua das Flores</xLgr>
    <nro>100</nro>
    <xBairro>Centro</xBairro>
    <xMun>Sao Paulo</xMun>
    <UF>SP</UF>
    <CEP>01001000</CEP>
  </enderEmit>
</emit>
<dest>
  {$destTag}
  <xNome>Destinatario Teste</xNome>
  <enderDest>
    <xLgr>Av Paulista</xLgr>
    <nro>2000</nro>
    <xBairro>Bela Vista</xBairro>
    <xMun>Sao Paulo</xMun>
    <UF>SP</UF>
    <CEP>01310100</CEP>
  </enderDest>
</dest>
<det nItem="1">
  <prod>
    <cProd>001</cProd>
    <xProd>Produto teste importacao</xProd>
    <NCM>12345678</NCM>
    <CFOP>5102</CFOP>
    <uCom>UN</uCom>
    <qCom>1.0000</qCom>
    <vUnCom>123.4500000000</vUnCom>
    <vProd>123.45</vProd>
    <uTrib>UN</uTrib>
    <qTrib>1.0000</qTrib>
    <vUnTrib>123.4500000000</vUnTrib>
    <indTot>1</indTot>
  </prod>
  <imposto>
    <ICMS>
      <ICMS00>
        <orig>0</orig>
        <CST>00</CST>
        <modBC>3</modBC>
        <vBC>100.00</vBC>
        <pICMS>18.00</pICMS>
        <vICMS>18.00</vICMS>
      </ICMS00>
    </ICMS>
  </imposto>
</det>
<total>
  <ICMSTot>
    <vBC>100.00</vBC>
    <vICMS>18.00</vICMS>
    <vICMSDeson>0.00</vICMSDeson>
    <vFCP>0.00</vFCP>
    <vBCST>0.00</vBCST>
    <vST>0.00</vST>
    <vFCPST>0.00</vFCPST>
    <vFCPSTRet>0.00</vFCPSTRet>
    <vProd>123.45</vProd>
    <vFrete>0.00</vFrete>
    <vSeg>0.00</vSeg>
    <vDesc>0.00</vDesc>
    <vII>0.00</vII>
    <vIPI>0.00</vIPI>
    <vIPIDevol>0.00</vIPIDevol>
    <vPIS>0.80</vPIS>
    <vCOFINS>3.70</vCOFINS>
    <vOutro>0.00</vOutro>
    <vNF>123.45</vNF>
  </ICMSTot>
</total>
<transp><modFrete>9</modFrete></transp>
<pag><detPag><tPag>01</tPag><vPag>123.45</vPag></detPag></pag>
</infNFe>
</NFe>
<protNFe versao="4.00">
  <infProt>
    <tpAmb>1</tpAmb>
    <verAplic>SP_NFE</verAplic>
    <chNFe>{$chave}</chNFe>
    <dhRecbto>2024-06-01T12:10:00-03:00</dhRecbto>
    <nProt>135240000000000</nProt>
    <digVal>dGVzdGU=</digVal>
    <cStat>100</cStat>
    <xMotivo>Autorizado o uso da NF-e</xMotivo>
  </infProt>
</protNFe>
</nfeProc>
XML;
}

function sample_nfce_xml(string $chave, ?string $emitCnpj = null): string
{
    $emitTag = sample_party_tag($emitCnpj ?? '16657751000130');

    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<nfeProc xmlns="http://www.portalfiscal.inf.br/nfe">
<NFe>
<infNFe Id="NFe{$chave}" versao="4.00">
<ide>
  <cUF>35</cUF>
  <cNF>99999999</cNF>
  <natOp>Venda</natOp>
  <mod>65</mod>
  <serie>1</serie>
  <nNF>1</nNF>
  <dhEmi>2024-06-01T12:00:00-03:00</dhEmi>
  <tpNF>1</tpNF>
  <idDest>1</idDest>
  <cMunFG>3550308</cMunFG>
  <tpImp>4</tpImp>
  <tpEmis>1</tpEmis>
  <cDV>0</cDV>
  <tpAmb>1</tpAmb>
  <finNFe>1</finNFe>
  <indFinal>1</indFinal>
  <indPres>1</indPres>
  <procEmi>0</procEmi>
  <verProc>MaisXML</verProc>
</ide>
<emit>
  {$emitTag}
  <xNome>Emitente NFC-e Teste</xNome>
  <CRT>1</CRT>
  <enderEmit>
    <xLgr>Rua A</xLgr>
    <nro>1</nro>
    <xBairro>Centro</xBairro>
    <xMun>Sao Paulo</xMun>
    <UF>SP</UF>
    <CEP>01001000</CEP>
  </enderEmit>
</emit>
<det nItem="1">
  <prod>
    <cProd>001</cProd>
    <xProd>Item cupom</xProd>
    <NCM>12345678</NCM>
    <CFOP>5102</CFOP>
    <uCom>UN</uCom>
    <qCom>1</qCom>
    <vUnCom>10.00</vUnCom>
    <vProd>10.00</vProd>
    <indTot>1</indTot>
  </prod>
  <imposto>
    <ICMS>
      <ICMS00>
        <orig>0</orig>
        <CST>00</CST>
        <modBC>3</modBC>
        <vBC>10.00</vBC>
        <pICMS>18.00</pICMS>
        <vICMS>1.80</vICMS>
      </ICMS00>
    </ICMS>
  </imposto>
</det>
<total>
  <ICMSTot>
    <vBC>10.00</vBC>
    <vICMS>1.80</vICMS>
    <vICMSDeson>0.00</vICMSDeson>
    <vFCP>0.00</vFCP>
    <vBCST>0.00</vBCST>
    <vST>0.00</vST>
    <vFCPST>0.00</vFCPST>
    <vFCPSTRet>0.00</vFCPSTRet>
    <vProd>10.00</vProd>
    <vFrete>0.00</vFrete>
    <vSeg>0.00</vSeg>
    <vDesc>0.00</vDesc>
    <vII>0.00</vII>
    <vIPI>0.00</vIPI>
    <vIPIDevol>0.00</vIPIDevol>
    <vPIS>0.00</vPIS>
    <vCOFINS>0.00</vCOFINS>
    <vOutro>0.00</vOutro>
    <vNF>10.00</vNF>
  </ICMSTot>
</total>
<pag><detPag><tPag>01</tPag><vPag>10.00</vPag></detPag></pag>
<infNFeSupl>
  <qrCode>https://www.sefaz.sp.gov.br/nfce/qrcode?chave={$chave}</qrCode>
  <urlChave>https://www.nfe.fazenda.gov.br/portal/consultaRecaptcha.aspx?tipoConsulta=resumo&amp;tipoConteudo=7PhJ+gAVw2g=</urlChave>
</infNFeSupl>
</infNFe>
</NFe>
<protNFe versao="4.00">
  <infProt>
    <tpAmb>1</tpAmb>
    <chNFe>{$chave}</chNFe>
    <nProt>135240000000001</nProt>
    <cStat>100</cStat>
    <xMotivo>Autorizado o uso da NF-e</xMotivo>
  </infProt>
</protNFe>
</nfeProc>
XML;
}

function sample_nfe_event_xml(
    string $chave,
    int $tpEvento = 110111,
    string $cStat = '135',
    int $nSeq = 1,
    ?string $authorCnpj = null,
    ?string $xCorrecao = null,
): string {
    $authorTag = sample_party_tag($authorCnpj ?? substr($chave, 6, 14));
    $desc = match ($tpEvento) {
        110110 => 'Carta de Correcao',
        110111 => 'Cancelamento',
        default => 'Evento',
    };
    $corr = $xCorrecao !== null
        ? "<xCorrecao>{$xCorrecao}</xCorrecao><xCondUso>Condicoes de uso</xCondUso>"
        : '<xJust>Justificativa do cancelamento de teste</xJust>';

    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<procEventoNFe xmlns="http://www.portalfiscal.inf.br/nfe" versao="1.00">
<evento versao="1.00">
<infEvento Id="ID{$tpEvento}{$chave}0{$nSeq}">
  <cOrgao>35</cOrgao>
  <tpAmb>1</tpAmb>
  {$authorTag}
  <chNFe>{$chave}</chNFe>
  <dhEvento>2024-06-02T10:00:00-03:00</dhEvento>
  <tpEvento>{$tpEvento}</tpEvento>
  <nSeqEvento>{$nSeq}</nSeqEvento>
  <verEvento>1.00</verEvento>
  <detEvento versao="1.00">
    <descEvento>{$desc}</descEvento>
    {$corr}
  </detEvento>
</infEvento>
</evento>
<retEvento versao="1.00">
<infEvento>
  <tpAmb>1</tpAmb>
  <cStat>{$cStat}</cStat>
  <xMotivo>Evento registrado e vinculado a NF-e</xMotivo>
  <chNFe>{$chave}</chNFe>
  <tpEvento>{$tpEvento}</tpEvento>
  <nProt>135240000099999</nProt>
</infEvento>
</retEvento>
</procEventoNFe>
XML;
}
