# Swagger UI local

Versão fixada: **5.32.11**, sem alterações nos arquivos de terceiros. Baixados em 30/09/2026.

- [Release oficial](https://github.com/swagger-api/swagger-ui/releases/tag/v5.32.11)
- `swagger-ui-bundle.js` e `swagger-ui.css`: diretório `dist` da tag `v5.32.11` em `https://raw.githubusercontent.com/swagger-api/swagger-ui/v5.32.11/dist/`.
- `LICENSE`: `https://raw.githubusercontent.com/swagger-api/swagger-ui/v5.32.11/LICENSE` (Apache-2.0).
- `swagger-ui-bundle.js.LICENSE.txt`: avisos do pacote `swagger-ui-dist@5.32.11`, obtidos em `https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.32.11/swagger-ui-bundle.js.LICENSE.txt`.

SHA-256 dos arquivos originais:

| Arquivo | SHA-256 |
|---|---|
| swagger-ui-bundle.js | `fcb81e2c79e7e3b76ddb9bd7fc791552045040fde05c19d3f98f9213e7f7724d` |
| swagger-ui.css | `ca238f7d7c2cf4480c1e77a9c3b9da915ab216e96ffd354e69076560c650c6de` |
| LICENSE | `cfc7749b96f63bd31c3c42b5c471bf756814053e847c10f3eb003417bc523d30` |
| swagger-ui-bundle.js.LICENSE.txt | `f22a5ade2354a48bddcb9746c2d17ed94a267fa5dbf72f631f42a72eba0d081c` |

`app.css` e `init.js` pertencem ao projeto. O navegador carrega os assets deste diretório local e `/openapi.json`; não usa CDN ou validador remoto. Ao atualizar a versão, substitua também os avisos de licença, confira os hashes e execute a suíte e o fluxo no navegador.
