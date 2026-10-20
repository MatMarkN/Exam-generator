# Schema oficial para teste do contrato

`schema.json` é uma cópia sem alterações de https://spec.openapis.org/oas/3.1/schema/2025-09-15, obtida em 30/09/2026.

SHA-256: `d0a3955182364c7b5fdebfd0583ecad259a870b4a2fe86a1b0fe8785f8224fed`.

Este schema verifica a estrutura do documento OpenAPI 3.1. Os schemas de dados são conferidos separadamente contra exemplos e respostas reais da API nos testes. A cópia permite executar a suíte sem acessar a rede.

No teste PHP, as referências dinâmicas `#meta` são convertidas em memória para o alvo estático equivalente `#/$defs/schema`, pois Opis 2.6 resolve essa âncora aninhada incorretamente. Não há extensão do meta-schema neste teste. A fixture oficial não é alterada e o teste inclui também um contrato inválido para conferir a rejeição.

Fonte: [OpenAPI Initiative](https://github.com/OAI/OpenAPI-Specification), licença Apache-2.0.
