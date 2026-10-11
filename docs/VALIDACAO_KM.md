# Proteção contra KM digitado errado

## Problema

Motoristas registravam KM com dígito a mais (65.432 → 654.320) ou trocavam o dígito inicial (6 → 9), gerando quilometragens irreais em relatórios, dashboard e consumo. O sistema só recusava KM **menor** que o anterior; qualquer valor maior era aceito.

## O que foi implementado (em produção desde 11/10/2026)

### 1. Referência confiável
`Veiculo::ultimoKmConhecido()` retorna o maior KM já registrado do veículo, considerando viagens, check-ins, abastecimentos e `km_atual`. Antes, as viagens não atualizavam o KM do veículo e a referência ficava defasada.

### 2. Validação em três níveis
Implementada no backend (`app/Services/KmPlausibilidade.php`), vale para qualquer tela.

| Situação | Exemplo | Comportamento |
|---|---|---|
| Normal | Saída até 300 km acima do último registro; distância compatível com o tempo | Registra normalmente |
| Suspeita | Salto > 300 km na saída, ou distância > 800 km ou acima do possível no tempo decorrido (110 km/h + 20 km de folga) | Exige confirmação + justificativa; viagem marcada `km_suspeito` (⚠) |
| Absurda | Dígito a mais (KM ≥ 5× a referência) ou salto > 2.000 km | **Bloqueia** com mensagem específica, ex.: *"KM 654.500 parece ter um dígito a mais. Último KM: 65.432. Confira o odômetro."* |

Limites configuráveis em `config/frota.php` via `.env` (`FROTA_KM_*`).

Quando o KM cai na faixa de alerta, a API responde 422 com `km_confirmacao_necessaria: true`; o cliente reenvia com `confirmar_km: true` e `justificativa_km`. A justificativa fica gravada em `viagens.justificativa_km`.

### 3. Ajuda visual na tela
- Abaixo do campo, o valor aparece formatado com a distância calculada ("654.500 km · Distância: 589.068 km"), tornando o erro visível antes de enviar.
- Na chegada, mostra o KM de saída da viagem.
- Viagens suspeitas exibem ⚠ na lista e no detalhe, com a justificativa.
- Componente compartilhado: `resources/js/components/shared/KmConfirmacao.jsx`.

### 4. Fluxos automáticos
No aceite de solicitação e no check-out feito pela gestão (sem tela de confirmação), valores absurdos são bloqueados e valores duvidosos apenas marcam a viagem como suspeita, sem travar a operação.

### 5. Auditoria do histórico
```bash
php artisan frota:auditar-km            # lista
php artisan frota:auditar-km --marcar   # lista e marca como km_suspeito
```
Para cada veículo, em ordem cronológica, sinaliza:
- **Distância** acima do limite dentro da viagem;
- **Salto**: saída muito acima do fim da viagem anterior (detecta ida e chegada infladas juntas);
- **Regressão**: saída muito abaixo do fim da anterior (indica que a anterior está inflada — a viagem listada em si costuma estar correta).

Na primeira execução em produção foi encontrado um registro errado na CGL-2J92 (1.274.751 em vez de 124.751), corrigido pela tela de correção. A auditoria hoje retorna limpa.

> Um KM inflado já gravado eleva a referência do veículo e desativa a proteção de salto para ele até ser corrigido. Rode a auditoria periodicamente.

## Implementação futura: foto obrigatória do odômetro

**Objetivo:** prova visual de cada leitura, eliminando dúvidas e disputas sobre o KM real.

**Proposta:**
- Foto do painel obrigatória na saída e na chegada, capturada pela câmera do app (sem galeria, para evitar fotos antigas).
- Registro de data/hora e geolocalização junto da imagem.
- Foto visível no detalhe da viagem e na tela de correção, para o gestor conferir o valor real.
- Viagens com ⚠ e foto entram com prioridade na revisão.
- **Evolução:** leitura automática do odômetro por OCR, preenchendo o campo e alertando quando o digitado divergir do lido.

**Pontos a decidir:**
- Armazenamento das fotos (disco do servidor ou storage externo) e tempo de retenção.
- Compressão da imagem no celular, para economizar dados móveis.
- Comportamento sem sinal (guardar e enviar depois).
- Obrigatoriedade para toda a frota ou, inicialmente, só ambulâncias.
