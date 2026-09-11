---
description: Executa o code review da PR ou branch com CodeRabbit CLI e Quality Gates do REVIEW.md do GOWA PHP SDK
argument-hint: "[PR_NUMERO_OU_URL] [--base <branch>] [--post-comments]"
allowed-tools: "Bash(gh:*), Bash(git:*), Bash(coderabbit:*), Bash(composer:*), Bash(vendor/bin/*)"
---

# /code-review

Execute a skill `code-review` para auditar a PR, branch ou alterações locais especificadas em **$ARGUMENTS**.

## Instruções

1. Se nenhum argumento for fornecido, verifique a branch atual contra `origin/main` ou pergunte ao usuário qual PR deseja revisar.
2. Siga rigorosamente o fluxo definido em `REVIEW.md` e na skill `code-review`.
3. Se o CodeRabbit CLI estiver instalado e autenticado (`coderabbit auth status`), rode `coderabbit review --committed --base <BASE> --agent` (ou `--uncommitted --agent` para alterações locais não commitadas); caso contrário, prossiga normalmente com o motor nativo de auditoria do próprio agente baseado nos Quality Gates do `REVIEW.md`.
4. Valide e filtre todos os findings descartando falsos-positivos de working tree sujo.
5. Rode as validações locais automatizadas do SDK: `composer test` (Pest 3.x) e `composer lint` (PHP-CS-Fixer).
6. Apresente o relatório categorizado por severidade (Critical, Major, Minor, Suggestion) seguindo o padrão do `REVIEW.md`.
7. Caso a flag `--post-comments` esteja presente ou o usuário solicite expressamente, publique a revisão no GitHub usando `gh api repos/:owner/:repo/pulls/:number/reviews`.
