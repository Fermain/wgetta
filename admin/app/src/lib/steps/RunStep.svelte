<script lang="ts">
  export let onNext: (() => void) | undefined
  export let onBack: (() => void) | undefined
  import WgettaStep from '../WgettaStep.svelte'
  import { Button } from '$lib/components/ui/button/index.js'
  import { Progress } from '$lib/components/ui/progress/index.js'
</script>

<WgettaStep title="Run">
  <p>Execution log:</p>
  <Progress value={33} />
  <pre class="whitespace-pre-wrap overflow-auto h-40 border border-neutral-800 rounded p-2">Starting...
Queued job...
Downloading ...
Done.</pre>
  <div class="mt-2 flex gap-2">
    <Button variant="outline" on:click={() => onBack?.()}>Back</Button>
    <Button variant="outline" on:click={() => onNext?.()}>Continue to Deploy</Button>
  </div>
</WgettaStep>


