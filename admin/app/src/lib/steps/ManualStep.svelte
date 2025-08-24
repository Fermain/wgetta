<script lang="ts">
  export let onNext: (() => void) | undefined
  export let onBack: (() => void) | undefined
  import WgettaStep from '../WgettaStep.svelte'
  import { Button } from '$lib/components/ui/button/index.js'
  import { Checkbox } from '$lib/components/ui/checkbox/index.js'
  import { Input } from '$lib/components/ui/input/index.js'
</script>

<WgettaStep title="Manual">
  <p>Review and toggle items to include in the plan.</p>
  <ul class="list-disc pl-6 space-y-1">
    <li class="flex items-center gap-2"><Checkbox /> <code>https://example.com/</code></li>
    <li class="flex items-center gap-2"><Checkbox /> <code>https://example.com/about/</code></li>
    <li class="flex items-center gap-2"><Checkbox /> <code>https://example.com/blog/</code></li>
  </ul>
  <div class="mt-2 flex gap-2">
    <Button variant="outline" on:click={() => onBack?.()}>Back</Button>
    <Button variant="outline" on:click={() => onNext?.()}>Continue to Run</Button>
  </div>
</WgettaStep>


