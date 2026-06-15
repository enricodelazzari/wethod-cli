#!/bin/sh
# Wethod CLI installer for macOS and Linux.
#
#   curl -fsSL https://raw.githubusercontent.com/enricodelazzari/wethod-cli/main/install.sh | bash
#
# Environment variables:
#   WETHOD_INSTALL_DIR   Directory to install into (default: /usr/local/bin,
#                        falling back to $HOME/.local/bin when not writable).
#   WETHOD_VERSION       Release tag to install (default: the latest release).

set -eu

REPO="enricodelazzari/wethod-cli"
BIN_NAME="wethod"

info() { printf '\033[34m==>\033[0m %s\n' "$1"; }
err() { printf '\033[31merror:\033[0m %s\n' "$1" >&2; exit 1; }

command -v curl >/dev/null 2>&1 || err "curl is required but not installed."

# Detect the platform (must match the release asset names).
os="$(uname -s)"
arch="$(uname -m)"

case "$os" in
  Darwin)
    case "$arch" in
      arm64) platform="darwin-arm64" ;;
      x86_64) platform="darwin-x64" ;;
      *) err "Unsupported macOS architecture: $arch" ;;
    esac
    ;;
  Linux)
    case "$arch" in
      aarch64 | arm64) platform="linux-arm64" ;;
      x86_64) platform="linux-x64" ;;
      *) err "Unsupported Linux architecture: $arch" ;;
    esac
    ;;
  *)
    err "Unsupported operating system: $os (use the PowerShell installer on Windows)."
    ;;
esac

# Resolve the release tag to install.
tag="${WETHOD_VERSION:-}"
if [ -z "$tag" ]; then
  info "Resolving the latest release..."
  tag="$(curl -fsSL "https://api.github.com/repos/$REPO/releases/latest" \
    | grep '"tag_name"' | head -n1 | cut -d '"' -f4)"
  [ -n "$tag" ] || err "Could not determine the latest release."
fi

asset="wethod-${tag}-${platform}"
url="https://github.com/$REPO/releases/download/${tag}/${asset}"

# Download to a temporary file.
tmp="$(mktemp)"
trap 'rm -f "$tmp"' EXIT

info "Downloading wethod $tag ($platform)..."
curl -fSL --progress-bar -o "$tmp" "$url" \
  || err "Download failed: $url"
chmod 0755 "$tmp"

# Pick an install directory.
install_dir="${WETHOD_INSTALL_DIR:-/usr/local/bin}"
target="$install_dir/$BIN_NAME"

if [ -w "$install_dir" ] || { [ ! -e "$install_dir" ] && mkdir -p "$install_dir" 2>/dev/null; }; then
  mv "$tmp" "$target"
elif command -v sudo >/dev/null 2>&1; then
  info "Writing to $install_dir requires elevated permissions."
  sudo mkdir -p "$install_dir"
  sudo mv "$tmp" "$target"
else
  # Fall back to a per-user location.
  install_dir="$HOME/.local/bin"
  target="$install_dir/$BIN_NAME"
  mkdir -p "$install_dir"
  mv "$tmp" "$target"
fi
trap - EXIT

info "Installed wethod to $target"

# Warn if the install directory is not on PATH.
case ":$PATH:" in
  *":$install_dir:"*) ;;
  *) printf '\033[33mnote:\033[0m %s is not on your PATH. Add it with:\n  export PATH="%s:$PATH"\n' "$install_dir" "$install_dir" ;;
esac

info "Run 'wethod login' to get started."
