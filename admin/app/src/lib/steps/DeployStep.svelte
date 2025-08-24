<script lang="ts">
  export let onBack: (() => void) | undefined
  import WgettaStep from '../WgettaStep.svelte'
</script>

<WgettaStep title="Deploy">
  <p>Select a completed run and push to GitLab.</p>
  <div class="mt-2 flex gap-2">
    <button type="button" class="px-3 py-2 rounded border border-neutral-700" on:click={() => onBack?.()}>Back</button>
    <button type="button" class="px-3 py-2 rounded border border-neutral-700">Open Git settings</button>
  </div>
</WgettaStep>


