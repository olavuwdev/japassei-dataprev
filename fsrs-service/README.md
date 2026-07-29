# Serviço FSRS — Já Passei DATAPREV

Serviço interno que calcula **todo** o agendamento por repetição espaçada do
módulo de flashcards, usando a biblioteca [`ts-fsrs`](https://github.com/open-spaced-repetition/ts-fsrs).

O backend em CodeIgniter nunca calcula intervalos: ele apenas consulta este
serviço e grava o resultado.

## Requisitos

- Node.js 20 ou superior (a hospedagem Node da Hostinger atende).

## Instalação

```bash
cd fsrs-service
npm install
npm start
```

## Variáveis de ambiente

| Variável              | Padrão      | Descrição |
|-----------------------|-------------|-----------|
| `PORT`                | `3100`      | Porta HTTP. Na Hostinger, use a porta que o painel informar. |
| `HOST`                | `127.0.0.1` | Use `0.0.0.0` quando o painel exigir. |
| `FSRS_SERVICE_TOKEN`  | vazio       | Token compartilhado com o PHP. **Defina sempre em produção.** |

## Implantação na hospedagem Node da Hostinger

1. Envie a pasta `fsrs-service/` para o servidor (ou aponte o repositório para ela).
2. No painel **Node.js**, configure:
   - *Application root*: caminho da pasta `fsrs-service`
   - *Application startup file*: `server.js`
   - *Node version*: 20 ou superior
3. Cadastre a variável `FSRS_SERVICE_TOKEN` com um valor aleatório longo.
4. Execute `npm install` pelo painel e inicie a aplicação.
5. No `.env` do CodeIgniter, aponte:

```env
FSRS_SERVICE_URL = 'https://SEU-APP-NODE.hostingersite.com'
FSRS_SERVICE_TOKEN = 'o-mesmo-token-do-passo-3'
```

O serviço **não deve** ficar acessível publicamente sem token: todas as rotas,
exceto `/internal/health`, exigem o cabeçalho `X-Internal-Token`.

## Endpoints

Todos recebem e devolvem JSON. Datas sempre em UTC (ISO 8601).

| Método | Rota | Descrição |
|--------|------|-----------|
| POST | `/internal/fsrs/cards` | Estado inicial de um cartão novo |
| POST | `/internal/fsrs/preview` | Os quatro resultados possíveis, sem aplicar |
| POST | `/internal/fsrs/review` | Aplica uma avaliação (1–4) e devolve cartão + log |
| POST | `/internal/fsrs/rollback` | Desfaz a última avaliação |
| POST | `/internal/fsrs/retrievability` | Probabilidade de recordação agora |
| POST | `/internal/fsrs/rebuild` | Reconstrói o estado a partir do histórico |
| GET  | `/internal/health` | Verificação de saúde (não exige token) |

### Formato do cartão

```json
{
  "due": "2026-07-29T10:00:00Z",
  "stability": 12.3456,
  "difficulty": 5.1,
  "elapsed_days": 3,
  "scheduled_days": 6,
  "reps": 4,
  "lapses": 1,
  "state": 2,
  "learning_step": 0,
  "last_review": "2026-07-26T10:00:00Z"
}
```

`state`: `0` Novo · `1` Aprendendo · `2` Revisão · `3` Reaprendendo.

### Parâmetros

```json
{
  "request_retention": 0.90,
  "maximum_interval": 36500,
  "enable_fuzz": true,
  "enable_short_term": true,
  "learning_steps": ["1m", "10m"],
  "relearning_steps": ["10m"]
}
```

### Exemplo — pré-visualizar

```bash
curl -X POST http://127.0.0.1:3100/internal/fsrs/preview \
  -H "Content-Type: application/json" \
  -H "X-Internal-Token: SEU_TOKEN" \
  -d '{"card":{"state":0,"due":"2026-07-29T10:00:00Z"},"now":"2026-07-29T10:00:00Z"}'
```

Resposta:

```json
{
  "ratings": {
    "1": { "due": "...", "interval_label": "1 min",  "state": 1, "...": "..." },
    "2": { "due": "...", "interval_label": "6 min",  "state": 1, "...": "..." },
    "3": { "due": "...", "interval_label": "10 min", "state": 1, "...": "..." },
    "4": { "due": "...", "interval_label": "4 dias", "state": 2, "...": "..." }
  }
}
```

## Testes

```bash
npm test
```
