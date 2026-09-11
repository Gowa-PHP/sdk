---
name: code-review
description: Executa revisão de código completa de PR, branch ou alterações locais contra o REVIEW.md do GOWA PHP SDK. Integra CodeRabbit CLI (--agent), auditoria multi-eixo (Compatibilidade PHP 8.2 & Tipagem, Segurança & Anti-SSRF, Resiliência de Parsing e Testes Pest), validação automatizada local (composer test e composer lint), filtra falsos-positivos e publica comentários detalhados no GitHub PR via gh. Ative sempre que o usuário pedir "fazer code review", "revisar PR", "rodar coderabbit na PR", "review de código" ou ao validar branches antes de merge.
---

# Code Review Skill — GOWA PHP SDK

Esta skill é o **guardião da prevenção de bugs, quebras de compatibilidade, perda de performance e segurança** no GOWA PHP SDK (`gowa-php`).
Ela consolida as diretrizes do projeto (`REVIEW.md`, `AGENTS.md`), executa o motor do **CodeRabbit CLI (`--agent`)**, valida o código com as ferramentas locais (`composer lint`, `composer test`), audita as alterações em múltiplos eixos e, opcionalmente, publica os comentários diretamente no GitHub via `gh`.

---

## Fluxo de Execução

```
[1. Resolver Alvo] ──> [2. Obter Contexto & Spec] ──> [3. Executar CodeRabbit CLI]
                                                                  │
[6. Publicar / Reportar] <── [5. Filtrar Alto Sinal] <── [4. Auditoria Multi-Eixo & Testes]
```

---

## Passo 1: Resolver Alvo e Preparar o Diff

Identifique o que será revisado com base no comando do usuário:

1. **Pull Request (ex: `PR #7` ou URL):**
   ```bash
   gh pr view <PR> --json number,title,body,baseRefName,headRefName,headRefOid,commits
   ```
   - Obtenha o commit base e o SHA do commit da PR.
   - Obtenha o diff exato:
     ```bash
     git diff origin/<BASE_BRANCH>...<HEAD_SHA>
     ```

2. **Branch Local vs Base (ex: `feat/...` contra `main`):**
   ```bash
   git log origin/main..HEAD --oneline
   git diff origin/main...HEAD
   ```

3. **Alterações Locais Não Commitadas (Work in Progress):**
   ```bash
   git diff
   ```

---

## Passo 2: Contexto, Spec e Diretrizes

Carregue as referências necessárias:

1. **Diretrizes do Projeto:**
   - Leia `REVIEW.md` (regras de severidade e quality gates específicos do SDK).
   - Consulte `AGENTS.md` para diretrizes de arquitetura e convenções do repositório.

2. **Identificação da Spec / Intenção:**
   - Se for PR: leia a descrição completa da PR e as issues referenciadas (`Refs #...` ou `Closes #...`).
   - A revisão deve verificar se o código **atendeu fielmente ao propósito** ou se deixou pontas soltas.

---

## Passo 3: CodeRabbit CLI (com Fallback Nativo Transparente)

Antes de invocar o CodeRabbit, verifique se a CLI está disponível e autenticada:

```bash
if command -v coderabbit >/dev/null 2>&1 && coderabbit auth status >/dev/null 2>&1; then
    echo "CODERABBIT_READY"
else
    echo "CODERABBIT_UNAVAILABLE"
fi
```

### Cenário A: CodeRabbit Instalado e Autenticado
Execute o CodeRabbit CLI em modo estruturado (`--agent`):
```bash
# Para alterações commitadas da branch/PR:
coderabbit review --committed --base <BASE_BRANCH> --agent

# Para alterações locais não commitadas:
coderabbit review --uncommitted --agent
```
Capture os findings emitidos para cruzamento na auditoria multi-eixo.

> **IMPORTANTE — Cuidado com o Working Tree Local:**
> O CodeRabbit CLI pode ler arquivos do sistema local. Se houver alterações não commitadas no workspace que não façam parte da PR sob revisão, filtre e descarte apontamentos decorrentes dessas edições locais.

### Cenário B: CodeRabbit Ausente ou Não Autenticado (Fallback Automático)
- **NUNCA interrompa ou falhe a revisão.**
- O agente assume **100% da auditoria de forma autônoma**, analisando o diff diretamente contra os eixos do `REVIEW.md` nos Passos 4 e 5.
- Apenas inclua uma nota informativa de rodapé no relatório final:
  > ℹ️ *CodeRabbit CLI não detectado ou não autenticado neste ambiente. A revisão foi conduzida com sucesso pelo motor nativo de Quality Gates do agente.*

---

## Passo 4: Verificação Automatizada Local

Antes de finalizar a análise, rode a validação mecânica local:

```bash
# 1. Verificar estilo e sintaxe (PHP CS Fixer)
composer lint

# 2. Executar suíte completa de testes (Pest 3.x)
composer test
```

Caso haja falhas nos testes ou no linter, elas devem ser apontadas com severidade **Major** ou **Critical**.

---

## Passo 5: Auditoria Multi-Eixo & Quality Gates

Além dos achados do CodeRabbit e dos testes, audite o diff contra os 5 eixos do `REVIEW.md`:

### Eixo 1: Compatibilidade PHP 8.2 & Tipagem Estrita
- **PHP 8.2 Baseline:** Há uso de features exclusivas de PHP 8.3+? (Proibido: constantes de classe tipadas `const string FOO`, `#[\Override]`, `json_validate` nativo sem polyfill).
- **Strict Types:** Todo arquivo novo ou editado possui `declare(strict_types=1);` no topo?
- **Imutabilidade:** DTOs usam `final class` com propriedades `public readonly`?
- **Enums Seguros:** Enums possuem fallback `case Unknown = 'unknown'` e método estático `tryFromValue()` para absorver novos tipos do WhatsApp sem exceções não tratadas?

### Eixo 2: Segurança & Anti-SSRF (Tolerância Zero)
- **Anti-SSRF:** URLs externas ou caminhos recebidos passam por `GowaHost::validate()` antes de qualquer chamada HTTP?
- **Assinatura HMAC:** Webhooks são validados via `WebhookSignature::verify()` com comparação em tempo constante (`hash_equals`)?
- **Vazamento de Segredos:** Tokens de API ou segredos de webhook são expostos em logs, exceções ou query strings?

### Eixo 3: Parsing Resiliente & Robustez de Dados
- **Factory Methods (`fromArray`):**
  - Tratam entradas vazias ou com tipos inesperados (ex: `['question' => 123]`) retornando `null`?
  - Coordenadas geográficas (`latitude`, `longitude`) são validadas com `is_finite()` e limites `-90..90` e `-180..180`?
  - Booleanos aceitam strings `"true"` / `"false"` via `filter_var(..., FILTER_VALIDATE_BOOLEAN)`?
  - Arrays associativos aninhados (como `phones`) são filtrados descartando chaves inválidas?
- **Null Safety:** Chamadas encadeadas tratam possíveis retornos nulos antes de acessar propriedades?

### Eixo 4: Client HTTP, Roteamento & Pest (Zero Rede Real)
- **Centralização:** Novas chamadas à API GOWA estão encapsuladas em `src/GowaClient.php`?
- **Testes Mockados:** Os testes usam `withMockResponse()` / `MockHandler` do Guzzle? É estritamente proibido disparar chamadas HTTP reais durante testes.
- **Roteamento Fluente:** Métodos `when()` e `otherwise()` em `WebhookEvent` e `IncomingMessage` funcionam de forma atômica e param após o primeiro match.

### Eixo 5: Paridade de Documentação & Commits
- **Documentação Bilíngue:** Novos métodos ou DTOs estão documentados em `README.md` (EN) e `README.pt.md` (PT)?
- **Changelog:** As mudanças estão resumidas em `CHANGELOG.md` na seção `[Unreleased]`?
- **Conventional Commits:** Os commits usam mensagens descritivas (`feat:`, `fix:`, `docs:`, `test:`)?

---

## Passo 6: Filtragem de Alto Sinal (Zero Bikeshedding)

Aplique o filtro de qualidade antes de relatar:

- **Mantenha:**
  - 🔴 **Critical**: Falhas de segurança (SSRF, HMAC bypass), quebra de sintaxe/PHP 8.2, quebra de execução, quebra destrutiva de contrato público.
  - 🟠 **Major**: Falhas de parsing resiliente, testes com rede real, testes quebrando, linter falhando, DTOs mutáveis.
  - 🟡 **Minor**: Falta de null-safe, ausência de testes para novos caminhos, documentação desatualizada.
- **Descarte:**
  - Estilo de código que o PHP-CS-Fixer corrige automaticamente via `composer lint:fix`.
  - Sugestões puramente teóricas que não impactam o funcionamento da biblioteca.
  - Apontamentos do CodeRabbit que sejam falsos-positivos decorrentes de arquivos sujos locais.

---

## Passo 7: Publicação e Apresentação

### 1. No Terminal / Chat
Apresente o resumo categorizado por severidade seguindo a taxonomia do `REVIEW.md`:
- Resumo executivo da revisão
- Tabela ou lista de apontamentos com:
  - Arquivo e linha exatos com link clicável
  - Descrição objetiva do problema e impacto
  - Snippet com o código sugerido para correção

### 2. No GitHub (quando solicitado ou com flag `--post-comments`)
Envie a revisão diretamente para a PR via GitHub CLI:

```bash
cat << 'JSON' > /tmp/pr_review.json
{
  "commit_id": "HEAD_SHA",
  "body": "## 🤖 Code Review — PR #NUMERO\n\nRESUMO",
  "event": "COMMENT",
  "comments": [
    {
      "path": "src/Dto/LocationPayload.php",
      "line": 35,
      "side": "RIGHT",
      "body": "**[Major] Validação de limites de coordenadas**\n\nDescricao..."
    }
  ]
}
JSON

gh api repos/OWNER/REPO/pulls/NUMERO/reviews --input /tmp/pr_review.json
rm -f /tmp/pr_review.json
```
