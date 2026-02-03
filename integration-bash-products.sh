#!/usr/bin/env bash
set -Eeuo pipefail

LOG="/home/yii/sambaprod.m2itsolutions.pl/logs/integration-log-products.txt"
PHP="/usr/bin/php"
YII="/home/yii/sambaprod.m2itsolutions.pl/yii"

LOCKDIR="/home/yii/.locks"
LOCKFILE="$LOCKDIR/generate-products.lock"
mkdir -p "$LOCKDIR"

# BLOKADA — tylko jedna instancja
exec 200>"$LOCKFILE"
if ! flock -n 200; then
  printf '%s [INFO] Już działa – wychodzę.\n' "$(date -Is)" >>"$LOG"
  exit 0
fi
trap 'flock -u 200' EXIT

printf '%s [INFO] START pętli\n' "$(date -Is)" >>"$LOG"

run_once() {
  # (opcjonalnie chroń przed zawiśnięciem pojedynczego runu)
  # timeout --foreground 30m \
  "$PHP" "$YII" xml-generator/generate-products 2>&1 | tee -a "$LOG"
}

# pętla: odpal od razu po zakończeniu poprzedniego
while :; do
  if ! run_once; then
    rc=$?
    printf '%s [WARN] run zakończony rc=%d – retry za 5s\n' "$(date -Is)" "$rc" | tee -a "$LOG"
    sleep 5
  fi
done
