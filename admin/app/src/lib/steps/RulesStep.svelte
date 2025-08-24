<script lang="ts">
  export let onNext: (() => void) | undefined
  export let onBack: (() => void) | undefined
  import WgettaStep from '../WgettaStep.svelte'
  import { Button } from '$lib/components/ui/button/index.js'
  import { Checkbox } from '$lib/components/ui/checkbox/index.js'
  import { Input } from '$lib/components/ui/input/index.js'
  import { Label } from '$lib/components/ui/label/index.js'
</script>

<WgettaStep title="Rules">
  <div>
    <div class="mb-2 flex gap-2">
      <Button variant="outline">Add pattern</Button>
      <Button variant="outline">Test patterns</Button>
    </div>
    <div class="space-y-2">
      <div class="flex gap-2 items-center">
        <Checkbox checked />
        <Input placeholder="POSIX regex" class="min-w-[360px]" />
        <Input placeholder="note" />
      </div>
      <div class="flex gap-2 items-center">
        <Checkbox />
        <Input placeholder="^https?://example.com/wp-admin/" class="min-w-[360px]" />
        <Input placeholder="note" />
      </div>
    </div>
    <div class="mt-2 flex gap-2">
      <Button variant="outline" on:click={() => onBack?.()}>Back</Button>
      <Button variant="outline" on:click={() => onNext?.()}>Continue to Manual</Button>
    </div>
  </div>
</WgettaStep>


