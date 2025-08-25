<script lang="ts">
  export let onNext: (() => void) | undefined
  export let onBack: (() => void) | undefined
  import WgettaStep from '../WgettaStep.svelte'
  import { Button } from '$lib/components/ui/button/index.js'
  import { Checkbox } from '$lib/components/ui/checkbox/index.js'
  import { Input } from '$lib/components/ui/input/index.js'
  import { Label } from '$lib/components/ui/label/index.js'

  type Row = { enabled: boolean; pattern: string; note: string }
  let rows: Row[] = [
    { enabled: true, pattern: '', note: '' },
    { enabled: true, pattern: '^https?://example.com/wp-admin/', note: '' }
  ]
  let newPattern = ''
  let newNote = ''

  function addRow() {
    const p = newPattern.trim()
    if (!p) return
    rows = [...rows, { enabled: true, pattern: p, note: newNote.trim() }]
    newPattern = ''
    newNote = ''
  }

  function removeRow(i: number) {
    rows = rows.filter((_, idx) => idx !== i)
  }
</script>

<WgettaStep title="Rules">
  <div>
    <div class="mb-2 flex gap-2">
      <Button variant="outline" size="sm">Test patterns</Button>
    </div>
    <div class="overflow-auto rounded border border-neutral-800">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-neutral-400">
            <th class="py-1.5 px-2 w-10">On</th>
            <th class="py-1.5 px-2">Pattern</th>
            <th class="py-1.5 px-2 w-64">Note</th>
            <th class="py-1.5 px-2 w-12"></th>
          </tr>
        </thead>
        <tbody>
          {#each rows as row, i}
            <tr class="border-t border-neutral-800">
              <td class="py-1.5 px-2 align-top">
                <Checkbox bind:checked={row.enabled} />
              </td>
              <td class="py-1.5 px-2 align-top">
                <Input placeholder="POSIX regex" bind:value={row.pattern} class="w-full font-mono" />
              </td>
              <td class="py-1.5 px-2 align-top">
                <Input placeholder="note" bind:value={row.note} />
              </td>
              <td class="py-1.5 px-2 align-top text-right">
                <Button variant="outline" size="sm" on:click={() => removeRow(i)} aria-label="Remove">×</Button>
              </td>
            </tr>
          {/each}
          <!-- Add new pattern row -->
          <tr class="border-t border-neutral-800">
            <td class="py-1.5 px-2 align-top">
              <span class="text-neutral-500">+</span>
            </td>
            <td class="py-1.5 px-2 align-top">
              <Input placeholder="Add new pattern" bind:value={newPattern} class="w-full font-mono" />
            </td>
            <td class="py-1.5 px-2 align-top">
              <Input placeholder="note (optional)" bind:value={newNote} />
            </td>
            <td class="py-1.5 px-2 align-top text-right">
              <Button variant="outline" size="sm" on:click={addRow} disabled={!newPattern.trim()}>Add</Button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="mt-2 flex gap-2">
      <Button variant="outline" size="sm" on:click={() => onBack?.()}>Back</Button>
      <Button variant="outline" size="sm" on:click={() => onNext?.()}>Continue</Button>
    </div>
  </div>
</WgettaStep>


