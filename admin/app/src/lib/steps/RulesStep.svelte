<script lang="ts">
  let { onNext = undefined, onBack = undefined }: { onNext?: () => void; onBack?: () => void } = $props()
  import WgettaStep from '../WgettaStep.svelte'
  import { Button } from '$lib/components/ui/button/index.js'
  import { Checkbox } from '$lib/components/ui/checkbox/index.js'
  import { Input } from '$lib/components/ui/input/index.js'
  import { Label } from '$lib/components/ui/label/index.js'

  type Row = { enabled: boolean; pattern: string; note: string; preview?: string }
  let rows = $state<Row[]>([])
  let domains = $state<string[]>([])
  let newDomain = $state('')
  // Initialize from session on mount
  $effect(() => {
    try {
      const rej = JSON.parse(sessionStorage.getItem('wgetta.rules.reject') || '[]') as string[]
      if (Array.isArray(rej) && rej.length) {
        rows = rej.map(p => ({ enabled: true, pattern: p, note: '' }))
      }
      const doms = JSON.parse(sessionStorage.getItem('wgetta.domains') || '[]') as string[]
      if (Array.isArray(doms)) { domains = doms }
    } catch {}
  })
  let newPattern = $state('')
  let newNote = $state('')

  function addRow() {
    const p = newPattern.trim()
    if (!p) return
    rows = [...rows, { enabled: true, pattern: p, note: newNote.trim() }]
    newPattern = ''
    newNote = ''
    try { sessionStorage.setItem('wgetta.rules.reject', JSON.stringify(rows.map(r=>r.pattern))) } catch {}
  }

  function removeRow(i: number) {
    rows = rows.filter((_, idx) => idx !== i)
    try { sessionStorage.setItem('wgetta.rules.reject', JSON.stringify(rows.map(r=>r.pattern))) } catch {}
  }

  function addDomain() {
    const d = newDomain.trim()
    if (!d) return
    domains = [...domains, d]
    newDomain = ''
    try { sessionStorage.setItem('wgetta.domains', JSON.stringify(domains)) } catch {}
  }

  async function simulate(i: number) {
    const base = (window as any).WGETTA?.apiBase || '/wp-json/wgetta/v1'
    const nonce = (window as any).WGETTA?.nonce || ''
    const pattern = rows[i]?.pattern?.trim()
    if (!pattern) return
    const job_id = sessionStorage.getItem('wgetta.job_id') || undefined
    const urls = (()=>{ try { return JSON.parse(sessionStorage.getItem('wgetta.urls')||'[]') } catch { return [] } })()
    const body = job_id ? { job_id, pattern } : { urls, pattern }
    const res = await fetch(`${base}/rules/simulate`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce }, body: JSON.stringify(body) })
    const data = await res.json().catch(()=>({}))
    if (data && data.success) {
      rows[i].preview = `Excludes ${data.excluded}/${data.total}`
      rows = [...rows]
    } else {
      rows[i].preview = data?.message || `HTTP ${res.status}`
      rows = [...rows]
    }
  }
</script>

<WgettaStep title="Rules">
  <div>
    <div class="mb-2 flex gap-2">
      <Button variant="outline" size="sm">Test patterns</Button>
    </div>
    <div class="overflow-auto rounded border border-neutral-800">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-neutral-400">
            <th class="py-1.5 px-2 w-10">On</th>
            <th class="py-1.5 px-2">Pattern</th>
            <th class="py-1.5 px-2 w-64">Note</th>
            <th class="py-1.5 px-2 w-40">Preview</th>
            <th class="py-1.5 px-2 w-24"></th>
          </tr>
        </thead>
        <tbody>
          {#each rows as row, i}
            <tr class="border-t border-neutral-800">
              <td class="py-1.5 px-2 align-top">
                <Checkbox bind:checked={row.enabled} />
              </td>
              <td class="py-1.5 px-2 align-top">
                <Input placeholder="POSIX regex" bind:value={row.pattern} class="w-full font-mono" />
              </td>
              <td class="py-1.5 px-2 align-top">
                <Input placeholder="note" bind:value={row.note} />
              </td>
              <td class="py-1.5 px-2 align-top text-neutral-400">
                {row.preview}
              </td>
              <td class="py-1.5 px-2 align-top text-right flex gap-2 justify-end">
                <Button variant="outline" size="sm" on:click={() => simulate(i)} disabled={!row.pattern.trim()}>Simulate</Button>
                <Button variant="outline" size="sm" on:click={() => removeRow(i)} aria-label="Remove">×</Button>
              </td>
            </tr>
          {/each}
          <!-- Add new pattern row -->
          <tr class="border-t border-neutral-800">
            <td class="py-1.5 px-2 align-top">
              <span class="text-neutral-500">+</span>
            </td>
            <td class="py-1.5 px-2 align-top">
              <Input placeholder="Add new pattern" bind:value={newPattern} class="w-full font-mono" />
            </td>
            <td class="py-1.5 px-2 align-top">
              <Input placeholder="note (optional)" bind:value={newNote} />
            </td>
            <td class="py-1.5 px-2 align-top text-neutral-400"></td>
            <td class="py-1.5 px-2 align-top text-right">
              <Button variant="outline" size="sm" on:click={addRow} disabled={!newPattern.trim()}>Add</Button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="mt-4">
      <h3 class="text-sm text-neutral-400 mb-2">Domains</h3>
      <div class="flex flex-wrap gap-2">
        {#each domains as d, i}
          <span class="inline-flex items-center gap-1 border border-neutral-800 rounded px-2 py-0.5">
            <code>{d}</code>
            <Button size="sm" variant="ghost" aria-label="Remove domain" onclick={() => { domains = domains.filter((_,idx)=>idx!==i); try{sessionStorage.setItem('wgetta.domains', JSON.stringify(domains))}catch{} }}>×</Button>
          </span>
        {/each}
      </div>
      <div class="mt-2 flex gap-2 items-center">
        <Input placeholder="Add domain" bind:value={newDomain} class="w-64" />
        <Button variant="outline" size="sm" onclick={addDomain} disabled={!newDomain.trim()}>Add</Button>
      </div>
    </div>
    <div class="mt-2 flex gap-2">
      <Button variant="outline" size="sm" on:click={() => onBack?.()}>Back</Button>
      <Button variant="outline" size="sm" on:click={() => onNext?.()}>Continue</Button>
    </div>
  </div>
</WgettaStep>


