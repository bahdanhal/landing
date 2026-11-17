#!/usr/bin/env bash
# Publish a complete static release by atomically switching a relative symlink.
set -euo pipefail
: "${SSH_HOST:?Required}"
: "${SSH_USER:?Required}"
release="${GITHUB_SHA:-$(date -u +%Y%m%dT%H%M%SZ)}"
[[ "$release" =~ ^[a-zA-Z0-9-]+$ ]] || exit 1
remote="$SSH_USER@$SSH_HOST"
release_path="/home/apps/portfolio/public/.hop-releases/$release"
ssh "$remote" "mkdir -p '$release_path'"
rsync -az public/HOP/ "$remote:$release_path/"
ssh "$remote" bash -s -- "$release" <<'REMOTE'
set -euo pipefail
release="$1"
cd /home/apps/portfolio/public
# Retain previously referenced content-hashed assets for clients already playing.
if [ -L HOP ] && [ -d HOP/assets ]; then
  rsync -a --ignore-existing HOP/assets/ ".hop-releases/$release/assets/"
fi
python3 - "$release" <<'PY'
import pathlib, re, sys
root = pathlib.Path('.hop-releases') / sys.argv[1]
html = (root / 'index.html').read_text()
assert 'noindex, nofollow' in html
assets = re.findall(r'(?:src|href)="(/HOP/[^\"]+)"', html)
assert assets
for asset in assets:
    assert (root / asset.removeprefix('/HOP/')).is_file(), asset
PY
if [ -e HOP ] && [ ! -L HOP ]; then
  echo 'Refusing to replace an existing non-symlink HOP path' >&2
  exit 1
fi
ln -s ".hop-releases/$release" ".hop-next-$release"
mv -Tf ".hop-next-$release" HOP
printf 'Published HOP release %s\n' "$release"
REMOTE
