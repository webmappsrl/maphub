# Elasticsearch: la sequenza di avvio

## Come funziona oggi

L'ordine è `elasticsearch` → `elasticsearch-init` → `kibana`.

`elasticsearch-init` è un container curl usa-e-getta che imposta la password di `kibana_system`
via API e poi si rimuove da sé.

`scout-init`, presente solo in `local.compose.yml`, esegue `scout:import` sui modelli di wm-package
quando Elasticsearch e il database sono pronti.

## Perché esiste `elasticsearch-init`

**Elasticsearch non accetta la password di `kibana_system` da variabile d'ambiente**: è l'unico
motivo per cui serve un container in più nella sequenza, e senza quel passaggio Kibana non si
autentica. Non è un vezzo di orchestrazione.
