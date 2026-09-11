# GOWA PHP SDK — Diretrizes e Quality Gates de Code Review

Este documento define a política oficial de Code Review do **GOWA PHP SDK** (`gowa-php`).
Ele serve como a constituição de validação técnica para revisores humanos e agentes autônomos (Antigravity, Claude Code, CodeRabbit CLI, Codex, OpenCode).

---

## 1. Filosofia de Code Review: Alto Sinal (Zero Bikeshedding)

1. **Foco no que importa**: O review existe para ser o **guardião da prevenção de bugs, quebras de compatibilidade, perda de performance e segurança**. Não fazemos bikeshedding de estilo cosmético — formatação é dever das ferramentas automatizadas (`composer lint` / `vendor/bin/php-cs-fixer`).
2. **Verificação rigorosa contra o código**: Nunca aponte um problema com base apenas na leitura do diff isolado. Verifique os arquivos adjacentes, a compatibilidade com PHP 8.2 e as chamadas aos endpoints do GOWA antes de emitir um finding. Falsos-positivos corroem a confiança no review.
3. **Sugestões concretas**: Todo apontamento deve explicar **por que** é um problema, **qual o impacto real** e fornecer uma **solução concreta em código**.

---

## 2. Taxonomia de Severidade

Todo comentário de review deve ser classificado em um dos 4 níveis:

### 🔴 Critical (Bloqueante de Merge / Release)
- **Segurança**: Vulnerabilidade SSRF (contornando validação de `GowaHost`), vazamento de segredos de webhook ou tokens de autenticação em exceptions/logs, ausência de verificação HMAC em rotas de webhook.
- **Quebra Fatal de Compatibilidade**: Código incompatível com PHP 8.2 (uso de features do PHP 8.3+, como constantes tipadas, `#[\Override]` ou `json_validate` nativo sem polyfill).
- **Quebra de Execução**: Erro de sintaxe, imports inexistentes, chamada de método em `null` que gera crash inevitável (`TypeError`, `Error`).
- **Quebra de Contrato**: Alteração destrutiva ou não retrocompatível em DTOs ou métodos públicos do `GowaClient` sem deprecation notice ou major version bump.

### 🟠 Major (Necessita de Correção antes do Merge)
- **Lógica de Parsing e Resiliência**: Parsing incorreto de dados do WhatsApp/GOWA (ex: conversão cega de booleano `"false"` que vira `true`, coordenadas geográficas fora de limites aceitas sem validação, aliases não normalizados permitindo criação de DTOs inválidos).
- **Desempenho & Memória**: Carregamento em memória de payloads/arquivos multimídia gigantes sem uso de streams em `MediaUpload`.
- **Violação Estrutural de Arquitetura**: Requisições HTTP fora de `src/GowaClient.php`, DTOs mutáveis (devem ser `readonly`), ausência de `declare(strict_types=1);`.
- **Testes com Rede Real**: Testes que disparam chamadas HTTP reais em vez de usar `MockHandler` do Guzzle.

### 🟡 Minor (Robustez e Resiliência)
- **Defensive Coding**: Ausência de operador null-safe (`?->`) em acessos a propriedades de payloads opcionais que possam vir nulas.
- **Edge Cases & Enums**: Enums de tipos de mensagem ou eventos sem case `Unknown` ou sem fallback seguro em `tryFromValue()`, que possam quebrar ao receber eventos desconhecidos do WhatsApp.
- **Cobertura de Testes**: Novo endpoint, método do client, DTO ou tipo de mensagem sem teste Pest correspondente em `tests/Unit/` ou `tests/Feature/`.

### 💡 Suggestion / Nitpick (Opcional / Não Bloqueante)
- Sugestões de legibilidade, simplificação de algoritmos, documentação PHPDoc de shapes de array complexos.

---

## 3. Quality Gates — GOWA PHP SDK (`src/` — PHP 8.2+, Pest 3.x)

### 🔒 Segurança & Anti-SSRF (Tolerância Zero)
- [ ] **Validação de Host (Anti-SSRF)**: Toda URL externa recebida (como uploads via `fromUrl`) ou caminhos relativos devem ser estritamente validados contra `GowaHost::validate()`, garantindo que apenas o host do servidor GOWA configurado seja acessado.
- [ ] **Assinatura de Webhook (HMAC SHA-256)**: Verificação de payload com `WebhookSignature::verify()` utilizando comparação de tempo constante (`hash_equals`), com suporte flexível ao formato raw ou com prefixo `sha256=`.
- [ ] **Proteção de Segredos**: Segredos de webhook e tokens de autorização nunca devem ser expostos em mensagens de exceções públicas, URLs ou logs em texto claro.

### 🐘 Compatibilidade PHP 8.2 & Tipagem Estrita
- [ ] **Compatibilidade Estrita com PHP 8.2**: O projeto suporta PHP 8.2 a 8.5. Proibido o uso de sintaxe ou funções exclusivas do PHP 8.3+ (ex: constantes de classe tipadas, `#[\Override]`, `json_validate`).
- [ ] **Strict Types**: Todo arquivo em `src/` e `tests/` DEVE iniciar com `declare(strict_types=1);`.
- [ ] **Imutabilidade de DTOs**: DTOs devem utilizar classes `final` com propriedades `public readonly`.
- [ ] **Factory Methods Resilientes (`fromArray`)**:
  - Validar tipos antes de instanciar o DTO; retornar `null` caso nenhum campo seja válido.
  - Normalizar booleanos usando `filter_var($val, FILTER_VALIDATE_BOOLEAN)`.
  - Validar limites geográficos (`-90.0 <= lat <= 90.0` e `-180.0 <= lng <= 180.0`) e finitude (`is_finite()`).
  - Filtrar arrays aninhados (ex: `phones` em `ContactCard`) preservando apenas itens com tipos esperados.
- [ ] **Enums com Fallback Seguro**: Enums como `Event` e `MessageType` devem possuir case `Unknown = 'unknown'` e método `tryFromValue(string $value): self` para não crashar com novas funcionalidades do WhatsApp.

### ⚡ Client HTTP & Webhooks
- [ ] **Centralização HTTP**: Toda comunicação REST vive exclusivamente em `src/GowaClient.php`.
- [ ] **Roteamento Fluente e Resiliente**: O dispatcher `WebhookEvent` e `IncomingMessage` devem permitir manipulação fluente (`when()`, `otherwise()`) sem crashar em payloads malformados ou eventos não tratados.
- [ ] **Retrocompatibilidade com ArrayAccess**: Objetos de resultado de webhook devem manter suporte a acesso via array (`$parsed['data']`, `$parsed['event']`).

### 🧪 Testes Automatizados (Pest 3.x)
- [ ] **Zero Chamadas de Rede Reais**: Todos os testes de feature devem simular respostas da API GOWA utilizando o helper `withMockResponse()` ou `MockHandler` do Guzzle.
- [ ] **Testes de Unidade Isolados**: DTOs, parsing de webhook, regras de segurança e configuração devem ser testados em `tests/Unit/`.
- [ ] **Testes de Feature**: Fluxos de requisição/resposta do `GowaClient` devem ser testados em `tests/Feature/`.
- [ ] **Suíte 100% Verde**: Todos os testes devem passar com `composer test` ou `vendor/bin/pest`.

### 🎨 Estilo & Formatação
- [ ] **PHP-CS-Fixer Limpo**: O código deve passar 100% sem erros em `composer lint` (`vendor/bin/php-cs-fixer fix --dry-run --diff`). Use `composer lint:fix` para formatação automática.

### 📝 Documentação & Conventional Commits
- [ ] **Paridade na Documentação**: Qualquer novo endpoint, DTO ou recurso deve ser documentado tanto em `README.md` (Inglês) quanto em `README.pt.md` (Português).
- [ ] **Changelog Atualizado**: Alterações devem ser registradas em `CHANGELOG.md` na seção `[Unreleased]`.
- [ ] **Conventional Commits**: Mensagens de commit no padrão `feat:`, `fix:`, `docs:`, `test:`, `refactor:`.

---

## 4. Formato Padrão para Apontamentos de Review

Ao emitir um comentário em PR ou relatório de revisão, use a estrutura:

```markdown
### [SEVERIDADE] `Caminho/Arquivo.php:Linha` — Título Descritivo

**Problema:**
Explicação clara da falha, vulnerabilidade ou quebra de compatibilidade identificada, detalhando o impacto real.

**Causa Raiz:**
```php
// trecho de código problemático
```

**Sugestão de Correção:**
```php
// código corrigido, pronto para aplicação
```
```
