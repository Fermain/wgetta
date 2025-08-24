<script lang="ts">
  export let onNext: (() => void) | undefined
  import WgettaStep from '../WgettaStep.svelte'
</script>

<WgettaStep title="Discover">
  <div class="space-y-3">
    <div>
      <pre class="whitespace-pre-wrap font-mono text-sm bg-neutral-950 border border-neutral-800 rounded p-3 overflow-x-auto"><code><span class="px-2 py-0.5 rounded bg-neutral-800 text-neutral-200 select-none">wget</span> <span class="px-2 py-0.5 rounded bg-neutral-800 text-neutral-200 select-none">--spider</span> <span class="px-2 py-0.5 rounded bg-neutral-800 text-neutral-200 select-none">-nv</span> <span contenteditable="true" role="textbox" aria-multiline="true" class="inline align-baseline break-words outline-none border border-dashed border-neutral-700 rounded px-2 py-0.5 text-neutral-200">--recursive --level=2 https://example.com/</span></code></pre>
    </div>

    <div class="mt-2 flex gap-2">
      <button type="button" class="px-3 py-2 rounded border border-neutral-700" on:click={() => onNext?.()}>Next</button>
    </div>
  </div>
</WgettaStep>


