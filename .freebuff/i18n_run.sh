#!/bin/bash
export HOME=/workspace/homes/9e03ed61266af80267d81f50
cd /workspace/chats/9e03ed61266af80267d81f50 || exit 1
for loc in en ru ar fa el; do
  for i in 1 2 3 4 5 6 7 8 9 10; do
    out=$(I18N_LIMIT=600 python3 .freebuff/i18n_google.py "$loc" 2>&1)
    echo "$out" | grep -E "eksik|eklendi" >> .freebuff/i18n-progress.log
    case "$out" in
      *"eksik 0 /"*) break;;
    esac
    echo "$out" | grep -q "eklendi 0$" && break
  done
done
echo "DONE $(date)" >> .freebuff/i18n-progress.log
