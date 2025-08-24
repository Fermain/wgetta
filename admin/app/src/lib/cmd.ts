import type { CommandOption } from './api/mock'

export type EffectiveCommands = { discover: string[]; run: string[] }

export function assembleEffective(command: { baseUrls: string[]; options: CommandOption[] }): EffectiveCommands {
  const base = normalizeOptions(command.options)
  const baseUrl = command.baseUrls[0] || ''

  // Discover: inject --spider (locked)
  const discover = ['wget', '--spider', ...base]
  if (baseUrl) discover.push(baseUrl)

  // Run: strip breadth/regex flags and inject locked flags
  const stripped = stripBreadthFlags(base)
  const run = ['wget', '-e', 'robots=off', '--force-directories', ...stripped]
  if (baseUrl) run.push(baseUrl)

  return { discover, run }
}

function normalizeOptions(options: CommandOption[]): string[] {
  const result: string[] = []
  for (const opt of options) {
    if (!opt || !opt.name) continue
    if (opt.value == null || opt.value === '') {
      result.push(opt.name)
    } else {
      result.push(`${opt.name}=${opt.value}`)
    }
  }
  return result
}

function stripBreadthFlags(args: string[]): string[] {
  const removeNoVal = new Set(['--recursive', '-r', '--mirror', '-m', '--span-hosts', '-H', '--spider', '--page-requisites', '-p', '--convert-links', '-k', '--backup-converted', '-K', '--delete-after'])
  const removeWithVal = new Set(['--level', '--domains', '--exclude-domains', '--accept', '--reject', '--accept-regex', '--reject-regex', '--input-file', '-i', '--adjust-extension', '-E'])
  const out: string[] = []
  for (const tok of args) {
    const name = tok.startsWith('--') && tok.includes('=') ? tok.slice(0, tok.indexOf('=')) : tok
    if (removeNoVal.has(tok) || removeNoVal.has(name)) continue
    if (removeWithVal.has(tok) || removeWithVal.has(name)) continue
    out.push(tok)
  }
  return out
}


