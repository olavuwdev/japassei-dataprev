# 📋 Exemplos Práticos - API FSRS

Guia prático de como fazer requisições a cada endpoint com dados reais.

**Antes de começar:** Certifique-se de que o serviço está rodando:
```bash
cd fsrs-service
npm start
```

---

## 1️⃣ Health Check (Sem autenticação)

Verifica se o serviço está online.

```bash
curl -X GET http://127.0.0.1:3100/internal/health
```

**Resposta esperada:**
```json
{
  "status": "ok",
  "fsrs_version": "5.2.1",
  "node_version": "v20.0.0",
  "uptime_seconds": 42
}
```

---

## 2️⃣ Criar um Cartão Novo

Cria um cartão no estado inicial (Novo).

```bash
curl -X POST http://127.0.0.1:3100/internal/fsrs/cards \
  -H "Content-Type: application/json" \
  -d '{"now":"2026-07-29T10:00:00Z"}'
```

**Dados enviados:**
- `now` (obrigatório): Data/hora atual em ISO 8601

**Resposta esperada:**
```json
{
  "card": {
    "due": "2026-07-29T10:00:00Z",
    "stability": 0,
    "difficulty": 0,
    "elapsed_days": 0,
    "scheduled_days": 0,
    "reps": 0,
    "lapses": 0,
    "state": 0,
    "learning_step": 0,
    "last_review": null
  }
}
```

**Estados do cartão:**
- `0` = Novo
- `1` = Aprendendo
- `2` = Revisão
- `3` = Reaprendendo

---

## 3️⃣ Pré-visualizar Resultados (Preview)

Mostra os 4 possíveis resultados de uma avaliação SEM aplicar.

```bash
curl -X POST http://127.0.0.1:3100/internal/fsrs/preview \
  -H "Content-Type: application/json" \
  -d '{
    "card": {
      "due": "2026-07-29T10:00:00Z",
      "stability": 0,
      "difficulty": 0,
      "elapsed_days": 0,
      "scheduled_days": 0,
      "reps": 0,
      "lapses": 0,
      "state": 0,
      "learning_step": 0,
      "last_review": null
    },
    "now": "2026-07-29T10:00:00Z"
  }'
```

**Dados enviados:**
- `card` (obrigatório): O cartão atual
- `now` (obrigatório): Data/hora da revisão
- `params` (opcional): Parâmetros FSRS (ver seção Parâmetros)

**Resposta esperada:**
```json
{
  "ratings": {
    "1": {
      "due": "2026-07-29T10:01:00Z",
      "interval_label": "1 min",
      "interval_minutes": 1,
      "state": 1,
      "stability": 0.4,
      "difficulty": 5.65,
      "elapsed_days": 0,
      "scheduled_days": 0,
      "reps": 1,
      "lapses": 0,
      "learning_step": 1
    },
    "2": {
      "due": "2026-07-29T10:06:00Z",
      "interval_label": "6 min",
      "interval_minutes": 6,
      "state": 1
    },
    "3": {
      "due": "2026-07-29T10:10:00Z",
      "interval_label": "10 min",
      "interval_minutes": 10,
      "state": 1
    },
    "4": {
      "due": "2026-08-02T10:00:00Z",
      "interval_label": "4 dias",
      "interval_minutes": 5760,
      "state": 2
    }
  }
}
```

**O que significam as avaliações:**
- `1` = Esqueceu (Again) - pior resposta
- `2` = Difícil (Hard)
- `3` = Bom (Good) - resposta esperada
- `4` = Fácil (Easy) - melhor resposta

---

## 4️⃣ Enviar Avaliação (Review)

Aplica uma avaliação e retorna o cartão atualizado + log.

```bash
curl -X POST http://127.0.0.1:3100/internal/fsrs/review \
  -H "Content-Type: application/json" \
  -d '{
    "card": {
      "due": "2026-07-29T10:00:00Z",
      "stability": 0,
      "difficulty": 0,
      "elapsed_days": 0,
      "scheduled_days": 0,
      "reps": 0,
      "lapses": 0,
      "state": 0,
      "learning_step": 0,
      "last_review": null
    },
    "rating": 3,
    "now": "2026-07-29T10:00:00Z"
  }'
```

**Dados enviados:**
- `card` (obrigatório): O cartão atual
- `rating` (obrigatório): 1, 2, 3 ou 4
- `now` (obrigatório): Data/hora da revisão
- `params` (opcional): Parâmetros FSRS

**Resposta esperada:**
```json
{
  "card": {
    "due": "2026-08-02T10:00:00Z",
    "stability": 2.1234,
    "difficulty": 5.3,
    "elapsed_days": 0,
    "scheduled_days": 4,
    "reps": 1,
    "lapses": 0,
    "state": 2,
    "learning_step": 0,
    "last_review": "2026-07-29T10:00:00Z"
  },
  "log": {
    "rating": 3,
    "state": 0,
    "due": "2026-07-29T10:00:00Z",
    "stability": 0,
    "difficulty": 0,
    "elapsed_days": 0,
    "last_elapsed_days": 0,
    "scheduled_days": 0,
    "learning_steps": 0,
    "review": "2026-07-29T10:00:00Z"
  }
}
```

**Guardar o log:** Você PRECISA guardar o `log` no banco de dados se quiser poder desfazer depois!

---

## 5️⃣ Desfazer Avaliação (Rollback)

Usa o log anterior para desfazer uma revisão.

```bash
curl -X POST http://127.0.0.1:3100/internal/fsrs/rollback \
  -H "Content-Type: application/json" \
  -d '{
    "card": {
      "due": "2026-08-02T10:00:00Z",
      "stability": 2.1234,
      "difficulty": 5.3,
      "elapsed_days": 0,
      "scheduled_days": 4,
      "reps": 1,
      "lapses": 0,
      "state": 2,
      "learning_step": 0,
      "last_review": "2026-07-29T10:00:00Z"
    },
    "log": {
      "rating": 3,
      "state": 0,
      "due": "2026-07-29T10:00:00Z",
      "stability": 0,
      "difficulty": 0,
      "elapsed_days": 0,
      "last_elapsed_days": 0,
      "scheduled_days": 0,
      "learning_steps": 0,
      "review": "2026-07-29T10:00:00Z"
    }
  }'
```

**Dados enviados:**
- `card` (obrigatório): O cartão atual (após a revisão)
- `log` (obrigatório): O log retornado no review anterior
- `params` (opcional): Parâmetros FSRS

**Resposta esperada:**
```json
{
  "card": {
    "due": "2026-07-29T10:00:00Z",
    "stability": 0,
    "difficulty": 0,
    "elapsed_days": 0,
    "scheduled_days": 0,
    "reps": 0,
    "lapses": 0,
    "state": 0,
    "learning_step": 0,
    "last_review": null
  }
}
```

O cartão volta ao estado anterior! ✅

---

## 6️⃣ Calcular Probabilidade de Recordação (Retrievability)

Calcula a chance atual de lembrar o cartão (0 a 1).

```bash
curl -X POST http://127.0.0.1:3100/internal/fsrs/retrievability \
  -H "Content-Type: application/json" \
  -d '{
    "card": {
      "due": "2026-08-02T10:00:00Z",
      "stability": 2.1234,
      "difficulty": 5.3,
      "elapsed_days": 0,
      "scheduled_days": 4,
      "reps": 1,
      "lapses": 0,
      "state": 2,
      "learning_step": 0,
      "last_review": "2026-07-29T10:00:00Z"
    },
    "now": "2026-07-29T10:00:00Z"
  }'
```

**Dados enviados:**
- `card` (obrigatório): O cartão
- `now` (obrigatório): Data/hora atual
- `params` (opcional): Parâmetros FSRS

**Resposta esperada:**
```json
{
  "retrievability": 0.85
}
```

**Interpretação:**
- `0.85` = 85% de chance de lembrar agora
- `0.50` = 50% de chance (deve revisar em breve)
- `0.30` = 30% de chance (revisar urgente)

---

## 7️⃣ Reconstruir Histórico (Rebuild)

Reconstrói o estado atual a partir do histórico completo de revisões.

```bash
curl -X POST http://127.0.0.1:3100/internal/fsrs/rebuild \
  -H "Content-Type: application/json" \
  -d '{
    "history": [
      {
        "rating": 3,
        "state": 0,
        "due": "2026-07-29T10:00:00Z",
        "stability": 0,
        "difficulty": 0,
        "review": "2026-07-29T10:00:00Z",
        "interval": 0
      },
      {
        "rating": 4,
        "state": 2,
        "due": "2026-08-02T10:00:00Z",
        "stability": 2.1,
        "difficulty": 5.3,
        "review": "2026-08-05T10:00:00Z",
        "interval": 4
      },
      {
        "rating": 3,
        "state": 2,
        "due": "2026-08-12T10:00:00Z",
        "stability": 5.5,
        "difficulty": 5.2,
        "review": "2026-08-12T10:00:00Z",
        "interval": 7
      }
    ]
  }'
```

**Dados enviados:**
- `history` (obrigatório): Array de todos os logs em ordem cronológica
- `params` (opcional): Parâmetros FSRS

**Resposta esperada:**
```json
{
  "card": {
    "due": "2026-08-19T10:00:00Z",
    "stability": 12.3,
    "difficulty": 5.1,
    "elapsed_days": 7,
    "scheduled_days": 7,
    "reps": 3,
    "lapses": 0,
    "state": 2,
    "learning_step": 0,
    "last_review": "2026-08-12T10:00:00Z"
  },
  "logs": [
    { "rating": 3, "state": 0, ... },
    { "rating": 4, "state": 2, ... },
    { "rating": 3, "state": 2, ... }
  ]
}
```

**Quando usar:**
- Mudar parâmetros FSRS
- Recalcular estado após ajuste
- Verificar consistência de dados

---

## ⚙️ Parâmetros FSRS (Opcional)

Em qualquer endpoint que aceite `params`, você pode customizar:

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

| Parâmetro | Padrão | Descrição |
|-----------|--------|-----------|
| `request_retention` | 0.90 | Objetivo de probabilidade de recordação (70-99%) |
| `maximum_interval` | 36500 | Intervalo máximo em dias (~100 anos) |
| `enable_fuzz` | true | Adiciona variação nos intervalos |
| `enable_short_term` | true | Usa passos de aprendizado curtos |
| `learning_steps` | ["1m", "10m"] | Passos iniciais de aprendizado |
| `relearning_steps` | ["10m"] | Passos de reaprendizado |

---

## 🔐 Autenticação (Produção)

Se `FSRS_SERVICE_TOKEN` estiver definido, você PRECISA enviar em todas as requisições (exceto `/internal/health`):

```bash
curl -X POST http://127.0.0.1:3100/internal/fsrs/preview \
  -H "Content-Type: application/json" \
  -H "X-Internal-Token: seu_token_secreto_aqui" \
  -d '{...}'
```

Localmente, deixe em branco:
```bash
export FSRS_SERVICE_TOKEN=""
npm start
```

---

## 📝 Fluxo Completo (Exemplo Real)

### Passo 1: Criar um cartão novo
```bash
CARD=$(curl -s -X POST http://127.0.0.1:3100/internal/fsrs/cards \
  -H "Content-Type: application/json" \
  -d '{"now":"2026-07-29T10:00:00Z"}' | jq '.card')

echo "Cartão criado:"
echo $CARD
```

### Passo 2: Pré-visualizar respostas possíveis
```bash
curl -s -X POST http://127.0.0.1:3100/internal/fsrs/preview \
  -H "Content-Type: application/json" \
  -d "{\"card\":$CARD,\"now\":\"2026-07-29T10:00:00Z\"}" | jq '.ratings'
```

### Passo 3: Enviar avaliação (rating 3 = Good)
```bash
REVIEW=$(curl -s -X POST http://127.0.0.1:3100/internal/fsrs/review \
  -H "Content-Type: application/json" \
  -d "{\"card\":$CARD,\"rating\":3,\"now\":\"2026-07-29T10:00:00Z\"}")

CARD_NOVO=$(echo $REVIEW | jq '.card')
LOG=$(echo $REVIEW | jq '.log')

echo "Cartão após revisão:"
echo $CARD_NOVO
echo "Log (guardar no banco!):"
echo $LOG
```

### Passo 4: Verificar probabilidade de recordação
```bash
curl -s -X POST http://127.0.0.1:3100/internal/fsrs/retrievability \
  -H "Content-Type: application/json" \
  -d "{\"card\":$CARD_NOVO,\"now\":\"2026-08-01T10:00:00Z\"}" | jq '.retrievability'
```

---

## 🐛 Erros Comuns

| Erro | Causa | Solução |
|------|-------|---------|
| `Connection refused` | Serviço não está rodando | `npm start` na pasta fsrs-service |
| `Token interno ausente` | Falta header de autenticação | Adicione `-H "X-Internal-Token: ..."` |
| `Avaliação inválida` | Rating não é 1-4 | Use 1, 2, 3 ou 4 |
| `JSON inválido` | Formato de requisição errado | Verifique sintaxe JSON |
| `Corpo excede limite` | Requisição muito grande | Máximo 2MB |

---

**Pronto! Agora você sabe como chamar cada endpoint! 🚀**
