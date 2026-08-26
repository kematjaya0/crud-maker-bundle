#!/usr/bin/env bash
#
# Lockstep release: tags the monorepo, then subtree-splits each packages/* into
# its own mirror repo and pushes the SAME tag there, so kematjaya/crud-maker-core,
# kematjaya/crud-maker-twig-bundle and kematjaya/crud-maker-api-bundle all publish
# the same version number on Packagist.
#
# Usage:
#   scripts/release.sh v1.2.0            # do it for real
#   scripts/release.sh v1.2.0 --dry-run  # print every command without pushing anything
#
# Requirements: clean working tree, on the release branch, tag not already used.
# Written for plain bash 3.2 (macOS default) — no associative arrays.

set -euo pipefail

# --- config: local package path -> mirror repo URL (parallel arrays) ---------
# Edit this once the mirror repos exist on GitHub (see README "Package layout").
PACKAGE_PATHS=(
    "packages/crud-maker-core"
    "packages/crud-maker-twig-bundle"
    "packages/crud-maker-api-bundle"
)
PACKAGE_URLS=(
    "https://github.com/kematjaya0/crud-maker-core.git"
    "https://github.com/kematjaya0/crud-maker-twig-bundle.git"
    "https://github.com/kematjaya0/crud-maker-api-bundle.git"
)
RELEASE_BRANCH="master"
MIRROR_BRANCH="main"
# -------------------------------------------------------------------------------

DRY_RUN=false
VERSION="${1:-}"
if [[ "${2:-}" == "--dry-run" ]]; then
    DRY_RUN=true
fi

if [[ -z "$VERSION" ]]; then
    echo "usage: $0 vX.Y.Z [--dry-run]" >&2
    exit 1
fi

if [[ ! "$VERSION" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "error: version must look like vX.Y.Z (got: $VERSION)" >&2
    exit 1
fi

run() {
    echo "+ $*"
    if [[ "$DRY_RUN" == false ]]; then
        "$@"
    fi
}

repo_root="$(git rev-parse --show-toplevel)"
cd "$repo_root"

current_branch="$(git branch --show-current)"
if [[ "$current_branch" != "$RELEASE_BRANCH" ]]; then
    echo "error: on branch '$current_branch', expected '$RELEASE_BRANCH' — checkout $RELEASE_BRANCH first" >&2
    exit 1
fi

if [[ -n "$(git status --porcelain)" ]]; then
    echo "error: working tree is not clean — commit or stash your changes first" >&2
    git status --short
    exit 1
fi

if git rev-parse "$VERSION" >/dev/null 2>&1; then
    echo "error: tag $VERSION already exists locally" >&2
    exit 1
fi

if git ls-remote --tags origin "refs/tags/$VERSION" | grep -q "$VERSION"; then
    echo "error: tag $VERSION already exists on origin" >&2
    exit 1
fi

if [[ ${#PACKAGE_PATHS[@]} -ne ${#PACKAGE_URLS[@]} ]]; then
    echo "error: PACKAGE_PATHS and PACKAGE_URLS are out of sync — check the config block" >&2
    exit 1
fi

echo "=== releasing $VERSION (dry-run: $DRY_RUN) ==="

echo
echo "--- 1/3: tagging monorepo ---"
run git tag "$VERSION"
run git push origin "$VERSION"

echo
echo "--- 2/3: subtree-split + push each package ---"
for i in "${!PACKAGE_PATHS[@]}"; do
    path="${PACKAGE_PATHS[$i]}"
    url="${PACKAGE_URLS[$i]}"
    name="$(basename "$path")"
    split_branch="split-${name}-tmp"

    if [[ ! -d "$path" ]]; then
        echo "error: $path does not exist — check PACKAGE_PATHS in this script" >&2
        exit 1
    fi

    echo
    echo ">>> $name -> $url"
    run git branch -D "$split_branch" 2>/dev/null || true
    run git subtree split --prefix="$path" -b "$split_branch"
    run git push "$url" "${split_branch}:${MIRROR_BRANCH}"
    run git push "$url" "refs/heads/${split_branch}:refs/tags/${VERSION}"
    run git branch -D "$split_branch"
done

echo
echo "--- 3/3: done ---"
echo "Tag $VERSION pushed to origin and to:"
for i in "${!PACKAGE_URLS[@]}"; do
    echo "  - ${PACKAGE_URLS[$i]}"
done
echo
echo "Packagist should pick up the new tag on each mirror repo automatically"
echo "(via its GitHub webhook) and publish version ${VERSION#v} for each package."
