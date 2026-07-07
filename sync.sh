#!/usr/bin/env bash
# ------------------------------------------------------------------
# GEÇİCİ GELİŞTİRİCİ ARACI
# GitHub <-> local hızlı senkronizasyon kısayolu.
#
# Canlıya (production) geçerken bu dosyayı silmeniz yeterlidir;
# başka hiçbir yere bağımlı değildir.
# ------------------------------------------------------------------
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")"

branch="$(git rev-parse --abbrev-ref HEAD)"

usage() {
    cat <<EOF
Kullanım:
  ./sync.sh pull            GitHub'daki değişiklikleri local'e çeker (branch: $branch)
  ./sync.sh push ["mesaj"]  Local değişiklikleri commit'leyip GitHub'a gönderir

Not: Bu geçici bir geliştirme aracıdır, canlıya çıkmadan önce silinmelidir.
EOF
}

do_pull() {
    local stashed=0

    if [[ -n "$(git status --porcelain)" ]]; then
        echo "Local'de commit edilmemiş değişiklikler var, önce stash'leniyor..."
        git stash push -u -m "sync.sh: pull öncesi otomatik stash"
        stashed=1
    fi

    echo "GitHub'dan çekiliyor (origin/$branch)..."
    git fetch origin "$branch"
    git pull origin "$branch"

    if [[ "$stashed" == "1" ]]; then
        echo "Stash geri uygulanıyor..."
        git stash pop
    fi

    echo "Tamamlandı."
}

do_push() {
    local msg="${1:-}"

    if [[ -z "$(git status --porcelain)" ]]; then
        echo "Gönderilecek değişiklik yok."
        exit 0
    fi

    if [[ -z "$msg" ]]; then
        msg="sync: $(date '+%Y-%m-%d %H:%M:%S')"
    fi

    git add -A
    git commit -m "$msg"

    echo "GitHub'a gönderiliyor (origin/$branch)..."
    git push -u origin "$branch"

    echo "Tamamlandı."
}

case "${1:-}" in
    pull)
        do_pull
        ;;
    push)
        shift
        do_push "${1:-}"
        ;;
    *)
        usage
        ;;
esac
