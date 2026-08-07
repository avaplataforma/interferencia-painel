# Split financeiro da rede

O contrato assinado e ativado no ADM Central é a fonte da regra comercial aplicada às novas cobranças da franquia.

## Funcionamento

- A cobrança é emitida pela conta central do Mundo Inter.
- A comissão definida no contrato permanece na conta central.
- O percentual líquido restante é enviado à wallet validada da franquia pelo campo `split` da API Asaas.
- Cobranças, parcelamentos e assinaturas criados antes da ativação não são alterados.
- Se a wallet não estiver validada ou o split estiver desabilitado, a emissão é bloqueada antes de chamar o Asaas.
- Cada tentativa fica registrada para auditoria e diagnóstico no ADM Central.

O percentual é calculado sobre o valor líquido processado pelo Asaas, conforme a regra oficial do gateway.
