# Qual Campo Contém o Dado Hasheado?

## 📋 Resposta Direta

O campo que contém o **msg_hash** (hash da mensagem) é:

### **`input`** (ou `data`)

Na transação Ethereum, esses dois nomes são **sinônimos**:
- `input` - nome usado na API JSON-RPC
- `data` - nome usado no Etherscan e documentação

## 🔍 Estrutura da Transação

### Exemplo Real da Sua Transação:

```json
{
  "hash": "0xf2c310f5676de0255c3a12cb158c2726dad8be520500c735fec082d638823975",
  "from": "0x93d824352f9d2d654b42c62b02dabc6b7b49ba42",
  "to": "0x93d824352f9d2d654b42c62b02dabc6b7b49ba42",
  "value": "0x0",
  "input": "0x3d84eb1d34a76750a0b8597d27b62bcb88d69cb0442dc9d4560610e3c8894c9c",  ← AQUI ESTÁ O MSG_HASH!
  "gas": "0x21000",
  "gasPrice": "0x1019516539",
  "nonce": "0x0"
}
```

## 📊 Comparação dos Campos

| Campo | Valor | O que representa |
|-------|-------|------------------|
| `hash` | `0xf2c310f56...` | Hash da transação completa (identificador único) |
| `from` | `0x93d82435...` | Endereço que envia (sua wallet) |
| `to` | `0x93d82435...` | Endereço que recebe (mesmo endereço = self-transaction) |
| `value` | `0x0` | Quantidade de ETH enviada (0 neste caso) |
| **`input`** | **`0x3d84eb1d...`** | **MSG_HASH - Hash da mensagem (keccak256)** ✅ |
| `gas` | `0x21000` | Limite de gas (21000 = transação básica) |
| `gasPrice` | `0x10195165...` | Preço do gas em wei |
| `nonce` | `0x0` | Número sequencial da transação |

## 🔐 Fluxo Completo

```
1. Mensagem Original:
   "Primeira mensagem na blockchain real!"
                    ↓
2. Hash Keccak256:
   0x3d84eb1d34a76750a0b8597d27b62bcb88d69cb0442dc9d4560610e3c8894c9c
                    ↓
3. Campo "input" da Transação:
   {
     "input": "0x3d84eb1d34a76750a0b8597d27b62bcb88d69cb0442dc9d4560610e3c8894c9c"
   }
                    ↓
4. Blockchain Ethereum (imutável)
```

## 👀 Como Ver no Etherscan

1. Acesse a transação:
   https://sepolia.etherscan.io/tx/0xf2c310f5676de0255c3a12cb158c2726dad8be520500c735fec082d638823975

2. Role até encontrar **"More Details"** ou **"Click to show more"**

3. Procure pela seção **"Input Data"**:
   ```
   Input Data:
   0x3d84eb1d34a76750a0b8597d27b62bcb88d69cb0442dc9d4560610e3c8894c9c
   ```

4. **Esse valor corresponde ao `msg_hash` armazenado no banco!**

## 🔄 Verificação de Correspondência

### 1. Hash armazenado no banco SQLite:
```bash
curl http://localhost:8000/v1/messages/4650ccc6-d421-4203-b61e-2d0e3df1a911
# Resultado: msg_hash = "0x3d84eb1d34a76750a0b8597d27b62bcb88d69cb0442dc9d4560610e3c8894c9c"
```

### 2. Hash no campo `input` da blockchain:
```bash
curl -X POST https://sepolia.infura.io/v3/SUA_API_KEY \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc":"2.0",
    "method":"eth_getTransactionByHash",
    "params":["0xf2c310f5676de0255c3a12cb158c2726dad8be520500c735fec082d638823975"],
    "id":1
  }' | jq -r '.result.input'
# Resultado: "0x3d84eb1d34a76750a0b8597d27b62bcb88d69cb0442dc9d4560610e3c8894c9c"
```

### 3. **São idênticos!** ✅

## 💡 Observações Importantes

### Por que usar o campo `input`?

- ✅ **Armazenamento permanente**: Dados no `input` ficam imutáveis na blockchain
- ✅ **Custo baixo**: Self-transaction com 0 ETH tem gas fee mínimo (~0.001 ETH)
- ✅ **Rastreabilidade**: Cada transação tem um `tx_hash` único
- ✅ **Verificação**: Qualquer um pode verificar o hash na blockchain

### Limitações Atuais (MVP):

- ❌ Não há contrato inteligente para estruturar melhor os dados
- ❌ O Etherscan não decodifica automaticamente (precisa clicar em "More Details")
- ❌ Não há eventos (logs) para facilitar busca

### Futuro (Com Contrato):

Se implementarmos um contrato inteligente, o `input` conteria:
- **Function selector** (primeiros 4 bytes): Identifica qual função do contrato
- **Parâmetros codificados** (resto): Dados da função, incluindo o msg_hash

Exemplo futuro:
```
input: 0x12345678[function selector] + 0x3d84eb1d34a76750a0b8597d27b62bcb88d69cb0442dc9d4560610e3c8894c9c[msg_hash]
```

## 📝 Resumo

**Pergunta:** Qual campo é o dado hasheado?

**Resposta:** O campo **`input`** (ou `data`) da transação Ethereum.

**Valor no seu caso:**
```
input: 0x3d84eb1d34a76750a0b8597d27b62bcb88d69cb0442dc9d4560610e3c8894c9c
```

**Isso é:**
- Hash keccak256 da mensagem "Primeira mensagem na blockchain real!"
- Armazenado permanentemente na blockchain
- Visível no Etherscan em "Input Data"
- Correspondente ao `msg_hash` no banco SQLite

