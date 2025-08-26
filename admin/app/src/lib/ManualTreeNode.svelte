<script lang="ts">
  import { Checkbox } from '$lib/components/ui/checkbox/index.js'
  let { node, level = 0, moveNode, toggleNode, toggleExpand }: {
    node: { id: string; text: string; checked?: boolean; expanded?: boolean; children?: any[] }
    level?: number
    moveNode: (dragId: string, targetId: string, pos: 'before'|'after'|'inside') => void
    toggleNode: (id: string) => void
    toggleExpand: (id: string) => void
  } = $props()

  function onDragStart(e: DragEvent) {
    e.dataTransfer?.setData('text/plain', node.id)
    e.dataTransfer?.setDragImage(new Image(), 0, 0)
  }
  let dropPos = $state<'none'|'before'|'after'|'inside'>('none')
  function onRowDragOver(e: DragEvent) {
    e.preventDefault()
    const el = e.currentTarget as HTMLElement
    const rect = el.getBoundingClientRect()
    const y = e.clientY - rect.top
    const threshold = Math.max(6, rect.height * 0.2)
    if (y < threshold) dropPos = 'before'
    else if (y > rect.height - threshold) dropPos = 'after'
    else dropPos = 'inside'
  }
  function onRowDragLeave() { dropPos = 'none' }
  function onRowDrop(e: DragEvent) {
    e.preventDefault()
    const id = e.dataTransfer?.getData('text/plain') || ''
    if (id && id !== node.id) moveNode(id, node.id, dropPos === 'none' ? 'after' : dropPos)
    dropPos = 'none'
  }
</script>

<div role="treeitem" aria-expanded={node.children?.length ? !!node.expanded : undefined} draggable="true" ondragstart={onDragStart} class="select-none">
  <div role="button" aria-label="Drop before" class="h-2" ondragover={onRowDragOver} ondragleave={onRowDragLeave} ondrop={onRowDrop} style={dropPos==='before' ? 'outline:1px solid #888' : ''}></div>
  <div class="py-1 px-2 rounded"
       style={`padding-left:${level*16}px; ${dropPos==='inside' ? 'outline:1px dashed #888' : ''}`}
       ondragover={onRowDragOver}
       ondragleave={onRowDragLeave}
       ondrop={onRowDrop}>
    <div class="flex items-center gap-2">
      {#if node.children?.length}
        <button type="button" onclick={() => toggleExpand(node.id)} aria-label="Toggle children">
          {node.expanded ? '▾' : '▸'}
        </button>
      {:else}
        <span style="width:0.75rem;display:inline-block"></span>
      {/if}
      <Checkbox checked={!!node.checked} onclick={() => toggleNode(node.id)} />
      <code style={node.checked ? '' : 'opacity:0.7'}>{node.text}</code>
    </div>
  </div>
  <div role="button" aria-label="Drop after" class="h-2" ondragover={onRowDragOver} ondragleave={onRowDragLeave} ondrop={onRowDrop} style={dropPos==='after' ? 'outline:1px solid #888' : ''}></div>

  {#if node.expanded && node.children?.length}
    {#each node.children as child}
      <ManualTreeNode {moveNode} {toggleNode} {toggleExpand} node={child} level={level+1} />
    {/each}
  {/if}
</div>


