<script lang="ts">
  import Checkbox from '$lib/components/ui/checkbox/checkbox.svelte'
  
  let { node, level = 0, onToggle }: { 
    node: any
    level?: number
    onToggle: (id: string, state: 0|1|2) => void 
  } = $props()
  
  function toggleExpand() { 
    node.expanded = !node.expanded 
  }
  
  function handleCheck() {
    // When clicking checkbox: unchecked -> checked, checked -> unchecked, indeterminate -> checked
    const nextState = (node.checked === 1) ? 0 : 1
    onToggle(node.id, nextState as 0|1|2)
  }
  
  // Derive visual state for checkbox
  const isChecked = $derived(node.checked === 1)
  const isIndeterminate = $derived(node.checked === 2)
</script>

<div class="group select-none" style={`padding-left:${level*20}px`}>
  <div class="flex items-center gap-2 py-1 px-2 hover:bg-neutral-900 rounded transition-colors">
    {#if !node.leaf && node.children?.length}
      <button 
        type="button" 
        onclick={toggleExpand}
        class="p-0.5 hover:bg-neutral-800 rounded transition-colors"
        aria-label="Toggle">
        <span class="text-neutral-400">
          {node.expanded ? '▼' : '▶'}
        </span>
      </button>
    {:else if node.leaf}
      <span class="w-4 h-4 text-neutral-500 ml-1 inline-block">📄</span>
    {:else}
      <span class="w-5"></span>
    {/if}
    
    <Checkbox 
      checked={isChecked}
      indeterminate={isIndeterminate}
      onCheckedChange={() => handleCheck()}
      class="data-[state=checked]:bg-blue-600 data-[state=checked]:border-blue-600" />
    
    {#if !node.leaf && node.children?.length}
      <span class="text-base">
        {node.expanded ? '📂' : '📁'}
      </span>
    {/if}
    
    <span class="text-sm font-mono" class:text-neutral-200={node.checked === 1} class:text-neutral-500={node.checked === 0} class:text-blue-400={node.checked === 2}>
      {node.title}
    </span>
    
    {#if node.children?.length}
      {@const checkedCount = node.children.filter(c => c.checked === 1).length}
      {@const totalCount = node.children.length}
      <span class="text-xs ml-auto mr-2" class:text-neutral-500={node.checked !== 2} class:text-blue-400={node.checked === 2}>
        {#if node.checked === 2}
          ({checkedCount}/{totalCount})
        {:else}
          ({totalCount})
        {/if}
      </span>
    {/if}
  </div>
  
  {#if node.children && node.expanded}
    <div class="border-l border-neutral-800 ml-[18px]">
      {#each node.children as child}
        <svelte:self node={child} level={level+1} {onToggle} />
      {/each}
    </div>
  {/if}
</div>


