<script lang="ts">
  export let onBack: (() => void) | undefined
  import WgettaStep from '../WgettaStep.svelte'
  import { Button } from '$lib/components/ui/button/index.js'
</script>

<WgettaStep title="Deploy">
  <p>Select a completed run and push to GitLab.</p>
  <div class="mt-2 flex gap-2">
    <Button variant="outline" on:click={() => onBack?.()}>Back</Button>
    <Button variant="outline">Open Git settings</Button>
  </div>
</WgettaStep>


