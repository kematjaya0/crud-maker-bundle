#!/usr/bin/env bash
#
# Lockstep release for the THREE SPLIT PACKAGES ONLY (core, twig-bundle,
# api-bundle): subtree-splits each packages/* into its own mirror repo and
# pushes the SAME tag to all three, so they always publish matching versions
# on Packagist and their `^1.0`-style require-each-other constraints stay
# meaningful.
#
# This does NOT tag the umbrella/root repo (kematjaya/crud-maker-bundle,
# this repo's own `origin`). That package has its own pre-existing version
# history (7.x/8.x, predating the split) and is versioned independently —
# bump and tag it manually, on its own track, only when its own composer.json
# require-ranges actually change:
#
#   git tag 8.5.0 && git push origin 8.5.0
#
# Usage:
#   scripts/release.sh v1.2.0            # do it for real
#   scripts/release.sh v1.2.0 --dry-run  # print every command without pushing anything
#
# Requirements: clean working tree, on the release branch, tag not already
# used on any of the three mirror repos.
# Written for plain bash 3.2 (macOS default) — no associative arrays.

set -euo pipefail

# --- config: local package path -> mirror repo URL (parallel arrays) ---------
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

if [[ -n "$(git status --porcelain --untracked-files=no)" ]]; then
    echo "error: working tree has uncommitted changes — commit or stash them first" >&2
    git status --short --untracked-files=no
    exit 1
fi

if [[ ${#PACKAGE_PATHS[@]} -ne ${#PACKAGE_URLS[@]} ]]; then
    echo "error: PACKAGE_PATHS and PACKAGE_URLS are out of sync — check the config block" >&2
    exit 1
fi

echo "--- 0/2: checking $VERSION isn't already tagged on any mirror ---"
for i in "${!PACKAGE_URLS[@]}"; do
    url="${PACKAGE_URLS[$i]}"
    if git ls-remote --tags "$url" "refs/tags/$VERSION" | grep -q "$VERSION"; then
        echo "error: tag $VERSION already exists on $url" >&2
        exit 1
    fi
done

echo "=== releasing $VERSION for crud-maker-core / -twig-bundle / -api-bundle (dry-run: $DRY_RUN) ==="

echo
echo "--- 1/2: subtree-split + push each package ---"
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
echo "--- 2/2: done ---"
echo "Tag $VERSION pushed to:"
for i in "${!PACKAGE_URLS[@]}"; do
    echo "  - ${PACKAGE_URLS[$i]}"
done
echo
echo "Packagist should pick up the new tag on each mirror repo automatically"
echo "(via its GitHub webhook) and publish version ${VERSION#v} for each package."
echo
echo "Reminder: the umbrella repo (origin, kematjaya/crud-maker-bundle) is NOT"
echo "touched by this script — it's on its own 7.x/8.x version track. Tag it"
echo "manually if its own composer.json require-ranges changed:"
echo "  git tag <next-8.x-version> && git push origin <next-8.x-version>"
