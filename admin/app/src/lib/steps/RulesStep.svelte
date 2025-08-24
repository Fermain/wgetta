<script lang="ts">
  export let onNext: (() => void) | undefined
  export let onBack: (() => void) | undefined
  import WgettaStep from '../WgettaStep.svelte'
</script>

<WgettaStep title="Rules">
  <div>
    <div class="mb-2 flex gap-2">
      <button type="button" class="px-3 py-2 rounded border border-neutral-700">Add pattern</button>
      <button type="button" class="px-3 py-2 rounded border border-neutral-700">Test patterns</button>
    </div>
    <div class="space-y-2">
      <div class="flex gap-2 items-center">
        <input type="checkbox" checked />
        <input type="text" placeholder="POSIX regex" class="min-w-[360px]" />
        <input type="text" placeholder="note" />
      </div>
      <div class="flex gap-2 items-center">
        <input type="checkbox" />
        <input type="text" placeholder="^https?://example.com/wp-admin/" class="min-w-[360px]" />
        <input type="text" placeholder="note" />
      </div>
    </div>
    <div class="mt-2 flex gap-2">
      <button type="button" class="px-3 py-2 rounded border border-neutral-700" on:click={() => onBack?.()}>Back</button>
      <button type="button" class="px-3 py-2 rounded border border-neutral-700" on:click={() => onNext?.()}>Continue to Manual</button>
    </div>
  </div>
</WgettaStep>


