<script lang="ts">
  export let onNext: (() => void) | undefined
  import WgettaStep from '../WgettaStep.svelte'
  import { Button } from '$lib/components/ui/button/index.js'

  let summary: { total: number; samples: string[] } | null = null
  let remainderEl: HTMLSpanElement | null = null
  let remainderText: string = '--recursive --level=2 https://example.com/'
  let loading = false
  let errorMsg: string | null = null
  let infoMsg: string | null = null

  async function analyze() {
    const txt = (remainderEl?.textContent || '').trim()
    if (!txt) { errorMsg = 'Enter flags or a URL to analyze'; infoMsg = null; return }
    try {
      loading = true
      errorMsg = null
      infoMsg = 'Analyzing…'
      const base = (window as any).WGETTA?.apiBase || '/wp-json/wgetta/v1'
      const nonce = (window as any).WGETTA?.nonce || ''
      if (!base) { throw new Error('REST base missing') }
      const res = await fetch(`${base}/discover/analyze`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
        body: JSON.stringify({ remainder: txt })
      })
      const data = await res.json().catch(() => ({}))
      if (data && data.success) {
        summary = { total: Number(data.total || 0), samples: Array.isArray(data.samples) ? data.samples : [] }
        infoMsg = `Found ${summary.total} URL${summary.total === 1 ? '' : 's'}.`
      } else {
        summary = { total: 0, samples: [] }
        errorMsg = (data && data.message) ? String(data.message) : `HTTP ${res.status}`
        infoMsg = null
      }
    } catch (e) {
      summary = { total: 0, samples: [] }
      errorMsg = 'Network error'
      infoMsg = null
    } finally {
      loading = false
    }
  }
</script>

<WgettaStep title="Discover">
  <div class="space-y-3">
    <div>
      <pre class="whitespace-pre-wrap font-mono text-sm bg-neutral-950 border border-neutral-800 rounded p-3 overflow-x-auto"><code><span class="px-2 py-0.5 rounded bg-neutral-800 text-neutral-200 select-none">wget</span> <span class="px-2 py-0.5 rounded bg-neutral-800 text-neutral-200 select-none">--spider</span> <span class="px-2 py-0.5 rounded bg-neutral-800 text-neutral-200 select-none">-nv</span> <span bind:this={remainderEl} contenteditable="true" role="textbox" aria-multiline="true" class="inline align-baseline break-words outline-none border border-dashed border-neutral-700 rounded px-2 py-0.5 text-neutral-200" on:input={(e:any)=>{ remainderText = (e.currentTarget?.textContent || '').trim(); }}>{remainderText}</span></code></pre>
    </div>

    <div class="mt-2 flex gap-2 items-center">
      <Button variant="outline" disabled={loading || !remainderText} on:click={analyze}>{loading ? 'Analyzing…' : 'Analyze'}</Button>
      <Button variant="outline" on:click={() => onNext?.()}>Next</Button>
      {#if infoMsg}<span class="text-xs text-neutral-400">{infoMsg}</span>{/if}
      {#if errorMsg}<span class="text-xs text-red-400">{errorMsg}</span>{/if}
    </div>

    {#if summary}
      <div class="mt-3 text-sm text-neutral-300">
        <p class="m-0">Found <strong>{summary.total}</strong> potential URL{summary.total === 1 ? '' : 's'}.</p>
        {#if summary.samples.length}
          <ul class="list-disc pl-5 mt-1">
            {#each summary.samples as u}
              <li><code>{u}</code></li>
            {/each}
          </ul>
        {/if}
      </div>
    {/if}
  </div>
</WgettaStep>


