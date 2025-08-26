<script lang="ts">
  let { onNext = undefined, onBack = undefined }: { onNext?: () => void; onBack?: () => void } = $props()
  import { onMount } from 'svelte'
  import WgettaStep from '../WgettaStep.svelte'
  import { Button } from '$lib/components/ui/button/index.js'
  import TreeNode from '../TreeNode.svelte'

  interface TreeNode { 
    id: string
    title: string
    children?: TreeNode[]
    leaf?: boolean
    expanded?: boolean
    checked?: 0|1|2  // 0=unchecked, 1=checked, 2=indeterminate
  }
  
  let tree: TreeNode[] = $state([])
  let loading = $state(false)
  let errorMsg = $state<string | null>(null)
  let selectedCount = $state(0)
  let totalCount = $state(0)

  function getSessionUrls(): string[] {
    try {
      const urls = JSON.parse(sessionStorage.getItem('wgetta.urls') || '[]')
      return Array.isArray(urls) && urls.length ? urls : []
    } catch { return [] }
  }

  function buildTree(urls: string[]): any[] {
    const map = new Map<string, any>()
    const root: any[] = []
    for (const url of urls) {
      let u: URL
      try { u = new URL(url) } catch { continue }
      const parts = u.pathname.split('/').filter(Boolean)
      let parentArr = root
      let pathAccum = `${u.protocol}//${u.host}`
      // Host node
      if (!map.has(pathAccum)) {
        const node = { title: pathAccum, key: pathAccum, folder: true, expanded: true, children: [] as any[] }
        map.set(pathAccum, node); root.push(node)
      }
      parentArr = map.get(pathAccum).children
      for (let i=0;i<parts.length;i++) {
        const part = parts[i]
        pathAccum += '/' + part
        if (!map.has(pathAccum)) {
          const isLeaf = i === parts.length - 1
          const node = { title: part + (isLeaf && !pathAccum.endsWith('/') ? '' : '/'), key: pathAccum + (isLeaf ? '' : '/'), folder: !isLeaf, children: [] as any[] }
          map.set(pathAccum, node); parentArr.push(node)
        }
        parentArr = map.get(pathAccum).children
      }
    }
    return root
  }

  onMount(async () => {
    try {
      loading = true; errorMsg = null
      let urls = getSessionUrls()
      if (!urls.length) {
        const base = (window as any).WGETTA?.apiBase || '/wp-json/wgetta/v1'
        const nonce = (window as any).WGETTA?.nonce || ''
        const job = sessionStorage.getItem('wgetta.job_id')
        if (job) {
          const res = await fetch(`${base}/jobs/urls?job_id=${encodeURIComponent(job)}`, { headers: { 'X-WP-Nonce': nonce } })
          const data = await res.json().catch(()=>({}))
          if (data && data.success && Array.isArray(data.urls)) urls = data.urls
        }
      }
      const base = (window as any).WGETTA?.apiBase || '/wp-json/wgetta/v1'
      const nonce = (window as any).WGETTA?.nonce || ''
      const job = sessionStorage.getItem('wgetta.job_id')
      if (job) {
        const res = await fetch(`${base}/jobs/tree?job_id=${encodeURIComponent(job)}`, { headers: { 'X-WP-Nonce': nonce } })
        const data = await res.json().catch(()=>({}))
        if (data && data.success && Array.isArray(data.tree)) {
          // Initialize all nodes with unchecked state and expand top level
          function initTree(nodes: TreeNode[], isTopLevel = false) {
            for (const node of nodes) {
              node.checked = node.checked ?? 1  // Default to checked
              node.expanded = isTopLevel || node.expanded  // Expand top level by default
              if (node.children) initTree(node.children)
            }
          }
          tree = data.tree
          initTree(tree, true)
        } else {
          tree = []
        }
      } else {
        tree = buildTree(urls) as any
      }
      updateCounts()
    } catch (e) {
      errorMsg = 'Failed to load tree'
    } finally {
      loading = false
    }
  })

  function updateCheckState(id: string, state: 0|1|2) {
    // Helper to set all descendants to the same state
    function setDescendants(node: TreeNode, newState: 0|1|2) {
      node.checked = newState
      if (node.children) {
        for (const child of node.children) {
          setDescendants(child, newState)
        }
      }
    }
    
    // Helper to update parent states based on children
    function updateParents(nodes: TreeNode[], targetId: string): boolean {
      for (const node of nodes) {
        if (node.id === targetId) {
          // Found the target, set it and all descendants
          setDescendants(node, state)
          return true
        }
        
        if (node.children?.length) {
          const found = updateParents(node.children, targetId)
          if (found) {
            // Update this parent based on children states
            const childStates = node.children.map(c => c.checked || 0)
            const allChecked = childStates.every(s => s === 1)
            const allUnchecked = childStates.every(s => s === 0)
            node.checked = allChecked ? 1 : (allUnchecked ? 0 : 2)
            return true
          }
        }
      }
      return false
    }
    
    updateParents(tree, id)
    tree = [...tree]  // Trigger reactivity
    updateCounts()
  }
  
  function updateCounts() {
    let selected = 0
    let total = 0
    
    function count(nodes: TreeNode[]) {
      for (const node of nodes) {
        if (node.leaf) {
          total++
          if (node.checked === 1) selected++
        }
        if (node.children) count(node.children)
      }
    }
    
    count(tree)
    selectedCount = selected
    totalCount = total
  }
</script>

<WgettaStep title="Manual Selection">
  <div class="mb-4">
    <p class="text-neutral-300">Review and select URLs to include in the mirror.</p>
    {#if totalCount > 0}
      <p class="text-sm text-neutral-500 mt-1">
        {selectedCount} of {totalCount} URLs selected
      </p>
    {/if}
    <div class="flex gap-4 mt-2 text-xs text-neutral-500">
      <span class="flex items-center gap-1">
        <span class="inline-block w-3 h-3 bg-blue-600 rounded"></span> Selected
      </span>
      <span class="flex items-center gap-1">
        <span class="inline-block w-3 h-3 bg-neutral-700 rounded"></span> Not selected
      </span>
      <span class="flex items-center gap-1">
        <span class="inline-block w-3 h-3 bg-blue-600 rounded relative">
          <span class="absolute inset-0 flex items-center justify-center text-white font-bold text-[8px]">-</span>
        </span> Partially selected
      </span>
    </div>
  </div>
  {#if loading}
    <p>Loading…</p>
  {:else if errorMsg}
    <p class="text-red-400">{errorMsg}</p>
  {:else if !tree.length}
    <p class="text-neutral-400">No URLs available. Run Analyze first.</p>
  {:else}
    <div class="border border-neutral-800 rounded-lg p-2 bg-neutral-950 max-h-[500px] overflow-y-auto">
      <div class="mb-2 flex gap-2">
        <Button 
          variant="outline" 
          size="sm"
          onclick={() => {
            function selectAll(nodes: any[]) {
              for (const n of nodes) {
                n.checked = 1
                if (n.children) selectAll(n.children)
              }
            }
            selectAll(tree)
            tree = [...tree]
            updateCounts()
          }}>
          Select All
        </Button>
        <Button 
          variant="outline" 
          size="sm"
          onclick={() => {
            function deselectAll(nodes: any[]) {
              for (const n of nodes) {
                n.checked = 0
                if (n.children) deselectAll(n.children)
              }
            }
            deselectAll(tree)
            tree = [...tree]
            updateCounts()
          }}>
          Deselect All
        </Button>
        <Button 
          variant="outline" 
          size="sm"
          onclick={() => {
            function expandAll(nodes: any[]) {
              for (const n of nodes) {
                if (!n.leaf && n.children?.length) n.expanded = true
                if (n.children) expandAll(n.children)
              }
            }
            expandAll(tree)
            tree = [...tree]
          }}>
          Expand All
        </Button>
        <Button 
          variant="outline" 
          size="sm"
          onclick={() => {
            function collapseAll(nodes: any[], isTop = false) {
              for (const n of nodes) {
                if (!n.leaf && n.children?.length) n.expanded = isTop
                if (n.children) collapseAll(n.children)
              }
            }
            collapseAll(tree, true)
            tree = [...tree]
          }}>
          Collapse All
        </Button>
      </div>
      {#each tree as n}
        <TreeNode node={n} onToggle={updateCheckState} />
      {/each}
    </div>
  {/if}
  <div class="mt-4 flex gap-2 items-center">
    <Button variant="outline" onclick={() => onBack?.()}>Back</Button>
    <Button 
      variant="default" 
      onclick={() => {
        // Save selected URLs to session
        const selected: string[] = []
        function collectSelected(nodes: any[]) {
          for (const n of nodes) {
            if (n.leaf && n.checked === 1) selected.push(n.id)
            if (n.children) collectSelected(n.children)
          }
        }
        collectSelected(tree)
        sessionStorage.setItem('wgetta.selected_urls', JSON.stringify(selected))
        onNext?.()
      }}
      disabled={selectedCount === 0}>
      Continue to Run ({selectedCount} URLs)
    </Button>
  </div>
</WgettaStep>


