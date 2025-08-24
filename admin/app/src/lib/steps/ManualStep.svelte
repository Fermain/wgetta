<script lang="ts">
  export let onNext: (() => void) | undefined
  export let onBack: (() => void) | undefined
  import WgettaStep from '../WgettaStep.svelte'
</script>

<WgettaStep title="Manual">
  <p>Review and toggle items to include in the plan.</p>
  <ul class="list-disc pl-6 space-y-1">
    <li><code>https://example.com/</code></li>
    <li><code>https://example.com/about/</code></li>
    <li><code>https://example.com/blog/</code></li>
  </ul>
  <div class="mt-2 flex gap-2">
    <button type="button" class="px-3 py-2 rounded border border-neutral-700" on:click={() => onBack?.()}>Back</button>
    <button type="button" class="px-3 py-2 rounded border border-neutral-700" on:click={() => onNext?.()}>Continue to Run</button>
  </div>
</WgettaStep>


