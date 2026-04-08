#!/usr/bin/env bash

set -euo pipefail

usage() {
  cat <<'EOF'
Usage:
  scripts/release.sh <version> [options]

Arguments:
  <version>               Semver version number (for example: 0.3.3)

Options:
  --notes <text>          Release notes text
  --notes-file <path>     Read release notes from a file
  --no-push               Do not push commit/tag to origin
  --no-release            Do not create a GitHub release
  --allow-non-main        Allow running on a branch other than main
  -h, --help              Show this help

Examples:
  scripts/release.sh 0.3.3
  scripts/release.sh 0.3.3 --notes "Bug fixes and updater improvements"
  scripts/release.sh 0.3.3 --no-release --no-push
EOF
}

die() {
  echo "Error: $*" >&2
  exit 1
}

require_cmd() {
  command -v "$1" >/dev/null 2>&1 || die "Required command not found: $1"
}

NEW_VERSION=""
RELEASE_NOTES=""
NOTES_FILE=""
NO_PUSH=0
NO_RELEASE=0
ALLOW_NON_MAIN=0

while (($# > 0)); do
  case "$1" in
    -h|--help)
      usage
      exit 0
      ;;
    --notes)
      shift
      [[ $# -gt 0 ]] || die "--notes requires a value"
      RELEASE_NOTES="$1"
      ;;
    --notes-file)
      shift
      [[ $# -gt 0 ]] || die "--notes-file requires a file path"
      NOTES_FILE="$1"
      ;;
    --no-push)
      NO_PUSH=1
      ;;
    --no-release)
      NO_RELEASE=1
      ;;
    --allow-non-main)
      ALLOW_NON_MAIN=1
      ;;
    -*)
      die "Unknown option: $1"
      ;;
    *)
      if [[ -n "$NEW_VERSION" ]]; then
        die "Version already provided: $NEW_VERSION"
      fi
      NEW_VERSION="$1"
      ;;
  esac
  shift
done

[[ -n "$NEW_VERSION" ]] || {
  usage
  die "Missing required <version> argument"
}

[[ "$NEW_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || die "Version must match semver format X.Y.Z"

if [[ -n "$NOTES_FILE" ]]; then
  [[ -f "$NOTES_FILE" ]] || die "Notes file not found: $NOTES_FILE"
  RELEASE_NOTES="$(cat "$NOTES_FILE")"
fi

require_cmd git
require_cmd php

if [[ "$NO_RELEASE" -eq 0 ]]; then
  require_cmd gh
fi

REPO_ROOT="$(git rev-parse --show-toplevel)"
cd "$REPO_ROOT"

PLUGIN_FILE="$REPO_ROOT/synq-animated-backgrounds.php"
README_FILE="$REPO_ROOT/README.md"

[[ -f "$PLUGIN_FILE" ]] || die "Plugin file not found at $PLUGIN_FILE"
[[ -f "$README_FILE" ]] || die "README file not found at $README_FILE"

CURRENT_BRANCH="$(git rev-parse --abbrev-ref HEAD)"
if [[ "$ALLOW_NON_MAIN" -eq 0 && "$CURRENT_BRANCH" != "main" ]]; then
  die "Current branch is '$CURRENT_BRANCH'. Switch to main or use --allow-non-main."
fi

if [[ -n "$(git status --porcelain)" ]]; then
  die "Working tree is not clean. Commit/stash changes before running release."
fi

if [[ "$NO_RELEASE" -eq 0 ]]; then
  gh auth status -h github.com >/dev/null 2>&1 || die "GitHub CLI is not authenticated. Run: gh auth login"
fi

CURRENT_VERSION="$(php -r '
$content = file_get_contents("synq-animated-backgrounds.php");
if (preg_match("/define\\x28\\x27SYNQ_AB_PLUGIN_VERSION\\x27,\\s*\\x27([^\\x27]+)\\x27\\x29;/", $content, $m)) {
  echo $m[1];
}
')"

[[ -n "$CURRENT_VERSION" ]] || die "Could not detect current plugin version"
[[ "$CURRENT_VERSION" != "$NEW_VERSION" ]] || die "New version is the same as current version ($CURRENT_VERSION)"

TAG="v$NEW_VERSION"

if git rev-parse -q --verify "refs/tags/$TAG" >/dev/null; then
  die "Tag already exists locally: $TAG"
fi

if git ls-remote --exit-code --tags origin "refs/tags/$TAG" >/dev/null 2>&1; then
  die "Tag already exists on origin: $TAG"
fi

echo "Bumping version: $CURRENT_VERSION -> $NEW_VERSION"

php -r '
$pluginFile = $argv[1];
$newVersion = $argv[2];
$content = file_get_contents($pluginFile);
if ($content === false) {
    fwrite(STDERR, "Failed to read plugin file\n");
    exit(1);
}
$countHeader = 0;
$countDefine = 0;
$content = preg_replace(
    "/^\\s*\\* Version:\\s+.*$/m",
    " * Version:           " . $newVersion,
    $content,
    1,
    $countHeader
);
$content = preg_replace(
    "/^define\\x28\\x27SYNQ_AB_PLUGIN_VERSION\\x27,\\s*\\x27[^\\x27]+\\x27\\x29;$/m",
    "define('\''SYNQ_AB_PLUGIN_VERSION'\'', '\''" . $newVersion . "'\'');",
    $content,
    1,
    $countDefine
);
if ($countHeader !== 1 || $countDefine !== 1) {
    fwrite(STDERR, "Failed to update plugin version markers\n");
    exit(1);
}
if (file_put_contents($pluginFile, $content) === false) {
    fwrite(STDERR, "Failed to write plugin file\n");
    exit(1);
}
' "$PLUGIN_FILE" "$NEW_VERSION"

php -r '
$readmeFile = $argv[1];
$newVersion = $argv[2];
$content = file_get_contents($readmeFile);
if ($content === false) {
    fwrite(STDERR, "Failed to read README\n");
    exit(1);
}
$count = 0;
$content = preg_replace(
    "/^Current version: `[^`]+`$/m",
    "Current version: `" . $newVersion . "`",
    $content,
    1,
    $count
);
if ($count !== 1) {
    fwrite(STDERR, "Failed to update README current version line\n");
    exit(1);
}
if (file_put_contents($readmeFile, $content) === false) {
    fwrite(STDERR, "Failed to write README\n");
    exit(1);
}
' "$README_FILE" "$NEW_VERSION"

php -l "$PLUGIN_FILE" >/dev/null

git add "$PLUGIN_FILE" "$README_FILE"
git commit -m "Release $TAG"
git tag "$TAG"

ZIP_PATH="$REPO_ROOT/synq-animated-backgrounds.zip"
rm -f "$ZIP_PATH"
git archive --format=zip --output="$ZIP_PATH" --prefix="synq-animated-backgrounds/" "$TAG"

if [[ "$NO_PUSH" -eq 0 ]]; then
  echo "Pushing branch and tag to origin"
  git push origin "$CURRENT_BRANCH"
  git push origin "$TAG"
else
  echo "Skipping push (--no-push)."
fi

if [[ "$NO_RELEASE" -eq 0 ]]; then
  echo "Creating GitHub release $TAG"
  if [[ -n "$RELEASE_NOTES" ]]; then
    gh release create "$TAG" "$ZIP_PATH" --title "$TAG" --notes "$RELEASE_NOTES"
  else
    gh release create "$TAG" "$ZIP_PATH" --title "$TAG" --generate-notes
  fi
else
  echo "Skipping GitHub release creation (--no-release)."
fi

echo "Release workflow complete."
echo "Version: $NEW_VERSION"
echo "Tag: $TAG"
echo "Zip: $ZIP_PATH"
