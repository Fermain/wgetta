<script lang="ts">
  export let onNext: (() => void) | undefined
  import WgettaStep from '../WgettaStep.svelte'
  import { Button } from '$lib/components/ui/button/index.js'

  let summary: { total: number; samples: string[] } | null = null
  let remainderEl: HTMLSpanElement | null = null

  function analyze() {
    const txt = (remainderEl?.textContent || '').trim()
    const urls = (txt.match(/https?:\/\/[^\s]+/g) || []).slice(0, 5)
    const total = (txt.match(/https?:\/\/[^\s]+/g) || []).length
    summary = { total, samples: urls }
  }
</script>

<WgettaStep title="Discover">
  <div class="space-y-3">
    <div>
      <pre class="whitespace-pre-wrap font-mono text-sm bg-neutral-950 border border-neutral-800 rounded p-3 overflow-x-auto"><code><span class="px-2 py-0.5 rounded bg-neutral-800 text-neutral-200 select-none">wget</span> <span class="px-2 py-0.5 rounded bg-neutral-800 text-neutral-200 select-none">--spider</span> <span class="px-2 py-0.5 rounded bg-neutral-800 text-neutral-200 select-none">-nv</span> <span bind:this={remainderEl} contenteditable="true" role="textbox" aria-multiline="true" class="inline align-baseline break-words outline-none border border-dashed border-neutral-700 rounded px-2 py-0.5 text-neutral-200">--recursive --level=2 https://example.com/</span></code></pre>
    </div>

    <div class="mt-2 flex gap-2">
      <Button variant="outline" on:click={analyze}>Analyze (mock)</Button>
      <Button variant="outline" on:click={() => onNext?.()}>Next</Button>
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


