<script lang="ts">
  export let onNext: (() => void) | undefined
  export let onBack: (() => void) | undefined
  import WgettaStep from '../WgettaStep.svelte'
</script>

<WgettaStep title="Run">
  <p>Execution log:</p>
  <pre class="whitespace-pre-wrap overflow-auto h-40 border border-neutral-800 rounded p-2">Starting...
Queued job...
Downloading ...
Done.</pre>
  <div class="mt-2 flex gap-2">
    <button type="button" class="px-3 py-2 rounded border border-neutral-700" on:click={() => onBack?.()}>Back</button>
    <button type="button" class="px-3 py-2 rounded border border-neutral-700" on:click={() => onNext?.()}>Continue to Deploy</button>
  </div>
</WgettaStep>


