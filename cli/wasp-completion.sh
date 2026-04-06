#!/usr/bin/env bash
# shellcheck shell=bash
#
# WASP CLI completion for:
#   - wasp <command> [options]
#   - php cli/wasp <command> [options]
#
# Usage:
#   source /path/to/wp-content/plugins/wasp/cli/wasp-completion.sh

_WASP_COMMANDS=(
  list
  help
  completion
  project:rename
  project:new
  create:post_type
  create:taxonomy
  create:meta_box
  create:term_meta
  create:admin_page
  create:admin_subpage
  create:setting_fields
  create:user_meta
  create:shortcode
  create:custom_columns
)

_WASP_GLOBAL_OPTIONS=(
  --help
  -h
  --quiet
  -q
  --verbose
  -v
  -vv
  -vvv
  --version
  -V
  --ansi
  --no-ansi
  --no-interaction
  -n
)

_wasp_command_options() {
  case "$1" in
    project:rename)
      echo "--dry-run --backup --config"
      ;;
    project:new)
      echo "--dry-run"
      ;;
    create:post_type|create:taxonomy|create:meta_box|create:term_meta|create:admin_page|create:admin_subpage|create:user_meta|create:shortcode|create:custom_columns)
      echo "--dry-run"
      ;;
    create:setting_fields)
      echo "--dry-run --subpage"
      ;;
    *)
      echo ""
      ;;
  esac
}

_wasp_is_cli_script() {
  case "$1" in
    cli/wasp|./cli/wasp|*/cli/wasp|../wasp/cli/wasp|*/wasp/cli/wasp)
      return 0
      ;;
    *)
      return 1
      ;;
  esac
}

_wasp_bash_complete() {
  local cur cmd opts offset
  cur="${COMP_WORDS[COMP_CWORD]}"
  offset=1

  if [[ "${COMP_WORDS[0]}" == "php" ]]; then
    if (( ${#COMP_WORDS[@]} < 2 )); then
      return 0
    fi
    if ! _wasp_is_cli_script "${COMP_WORDS[1]}"; then
      return 0
    fi
    offset=2
  fi

  if (( COMP_CWORD == offset )); then
    COMPREPLY=( $(compgen -W "${_WASP_COMMANDS[*]}" -- "$cur") )
    return 0
  fi

  cmd="${COMP_WORDS[offset]}"

  if [[ "$cur" == -* ]]; then
    opts="$(_wasp_command_options "$cmd") ${_WASP_GLOBAL_OPTIONS[*]}"
    COMPREPLY=( $(compgen -W "$opts" -- "$cur") )
    return 0
  fi
}

_wasp_zsh_complete() {
  local offset cmd
  # In zsh completion:
  #   words[1] = "wasp", words[2] = "<command>"
  offset=2

  if [[ "$words[1]" == "php" ]]; then
    if (( CURRENT < 3 )); then
      _default
      return $?
    fi
    if ! _wasp_is_cli_script "$words[2]"; then
      _default
      return $?
    fi
    offset=3
  fi

  if (( CURRENT == offset )); then
    compadd -- "${_WASP_COMMANDS[@]}"
    return 0
  fi

  cmd="${words[offset]}"

  if [[ "${words[CURRENT]}" == -* ]]; then
    local -a cmd_opts
    cmd_opts=( ${=(_wasp_command_options "$cmd")} "${_WASP_GLOBAL_OPTIONS[@]}" )
    compadd -- "${cmd_opts[@]}"
    return 0
  fi

  return 0
}

if [[ -n "${BASH_VERSION:-}" ]]; then
  complete -o bashdefault -o default -F _wasp_bash_complete wasp
  complete -o bashdefault -o default -F _wasp_bash_complete php
elif [[ -n "${ZSH_VERSION:-}" ]]; then
  autoload -Uz compinit
  if ! typeset -f compdef >/dev/null 2>&1; then
    compinit
  fi
  compdef _wasp_zsh_complete wasp
  compdef _wasp_zsh_complete php
fi
