<script lang="ts">
  import { Tabs } from 'bits-ui'
  import type { Plan, CommandOption, PlanRule } from './lib/api/mock'
  import { MockApi } from './lib/api/mock'
  import { assembleEffective } from './lib/cmd'
  import WgettaStep from './lib/WgettaStep.svelte'

  const steps = ['discover','rules','manual','run','deploy'] as const
  let step: (typeof steps)[number] = 'discover'
  const stepIndex = () => steps.indexOf(step)
  function next(){ if (stepIndex() < steps.length - 1) step = steps[stepIndex()+1] }
  function prev(){ if (stepIndex() > 0) step = steps[stepIndex()-1] }

  let baseUrls: string[] = [window?.WGETTA?.siteUrl || 'https://example.com/']
  let options: CommandOption[] = [
    { name: '-nv', value: null, editable: true },
    { name: '--recursive', value: null, editable: true },
    { name: '--level', value: '2', editable: true }
  ]

  let plan: Plan | null = null
  let sample: string[] = []
  let rules: PlanRule[] = []
  let testing = false
  let testSummary: { includedCount: number; excludedCount: number } | null = null
  $: eff = assembleEffective({ baseUrls, options })

  async function doDiscover() {
    const res = await MockApi.discover({ baseUrls, options })
    plan = res.plan
    sample = res.sample
    step = 'rules'
  }

  function addRule() {
    const id = 'r' + (rules.length + 1)
    rules = [...rules, { id, pattern: '', type: 'posix', enabled: true }]
  }

  async function testRules() {
    testing = true
    try {
      const res = await MockApi.testRules(rules, sample)
      testSummary = { includedCount: res.includedCount, excludedCount: res.excludedCount }
    } finally {
      testing = false
    }
  }

  async function applyRulesAndContinue() {
    // For mock, just move to manual step
    step = 'manual'
  }

  function gotoRun() {
    step = 'run'
  }

  function gotoDeploy() {
    step = 'deploy'
  }
</script>

<main class="max-w-screen-lg mx-auto">
  <h1>Wgetta</h1>
  <p>Discover → Rules → Manual → Run → Deploy</p>

  <Tabs.Root value={step} on:change={(e:any)=> step = e.detail.value}>
    <Tabs.List>
      {#each steps as s, i}
        <Tabs.Trigger value={s} disabled={i > stepIndex()+1}>{i+1}) {s}</Tabs.Trigger>
      {/each}
    </Tabs.List>
    <div class="h-1.5 bg-neutral-800 rounded-md my-2">
      <div class="h-1.5 bg-neutral-500 rounded-md" style={`width:${((stepIndex()+1)/steps.length)*100}%`}></div>
    </div>

    <Tabs.Content value="discover">
      <WgettaStep title="Discover">
        <div class="w-full grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">
          <div>
            <label for="baseurl">Base URL</label>
            <input id="baseurl" type="text" value={baseUrls[0]} on:input={(e:any)=> baseUrls=[e.currentTarget.value]} style="min-width:360px;" />
            <div class="mt-3">
              <strong class="font-semibold">Effective Commands (preview)</strong>
              {#if baseUrls.length}
                <pre class="whitespace-pre-wrap overflow-auto">discover: {eff.discover.join(' ')}</pre>
                <pre class="whitespace-pre-wrap overflow-auto">run: {eff.run.join(' ')}</pre>
              {/if}
            </div>
          </div>
          <div>
            <label for="opts">Options</label>
            <div id="opts">
              {#each options as opt, i}
                <div class="mb-1.5">
                  <code>{opt.name}</code>
                  {#if opt.value !== null}
                    = <input type="text" value={opt.value} on:input={(e:any)=> options[i] = { ...opt, value: e.currentTarget.value }} />
                  {/if}
                  {#if !opt.editable}
                    <span title="locked">🔒</span>
                  {/if}
                </div>
              {/each}
            </div>
          </div>
        </div>
        <div class="mt-2 flex gap-2">
          <button on:click={doDiscover} class="px-3 py-2 rounded border border-neutral-700">Run Discover (mock)</button>
          <button on:click={next} class="px-3 py-2 rounded border border-neutral-700">Next</button>
        </div>
      </WgettaStep>
    </Tabs.Content>

    <Tabs.Content value="rules">
      <WgettaStep title="Rules">
        <div>
          <div class="mb-2">
            <button on:click={addRule} class="px-3 py-2 rounded border border-neutral-700">Add Rule</button>
            <button on:click={testRules} disabled={rules.length===0 || testing} class="ml-2 px-3 py-2 rounded border border-neutral-700 opacity-100 disabled:opacity-50">{testing ? 'Testing…' : 'Test Rules (mock)'}</button>
          </div>
          {#each rules as r, i}
            <div class="flex gap-2 items-center mb-1.5">
              <input type="checkbox" checked={r.enabled} on:change={(e:any)=> rules[i] = { ...r, enabled: e.currentTarget.checked }} />
              <input type="text" placeholder="POSIX regex" value={r.pattern} on:input={(e:any)=> rules[i] = { ...r, pattern: e.currentTarget.value }} class="min-w-[360px]" />
              <input type="text" placeholder="note" value={r.note || ''} on:input={(e:any)=> rules[i] = { ...r, note: e.currentTarget.value }} />
            </div>
          {/each}
          {#if testSummary}
            <p>Included: {testSummary.includedCount} • Excluded: {testSummary.excludedCount}</p>
          {/if}
          <div class="mt-2 flex gap-2">
            <button on:click={prev} class="px-3 py-2 rounded border border-neutral-700">Back</button>
            <button on:click={applyRulesAndContinue} class="px-3 py-2 rounded border border-neutral-700">Continue to Manual</button>
          </div>
        </div>
      </WgettaStep>
    </Tabs.Content>

    <Tabs.Content value="manual">
      <WgettaStep title="Manual">
        <p>Manual selection UI placeholder (virtualized tree to come). Sample:</p>
        <ul>
          {#each sample.slice(0,10) as u}
            <li><code>{u}</code></li>
          {/each}
        </ul>
        <div class="mt-2 flex gap-2">
          <button on:click={prev} class="px-3 py-2 rounded border border-neutral-700">Back</button>
          <button on:click={gotoRun} class="px-3 py-2 rounded border border-neutral-700">Continue to Run</button>
        </div>
      </WgettaStep>
    </Tabs.Content>

    <Tabs.Content value="run">
      <WgettaStep title="Run">
        <p>Run/Log placeholder.</p>
        <div class="mt-2 flex gap-2">
          <button on:click={prev} class="px-3 py-2 rounded border border-neutral-700">Back</button>
          <button on:click={gotoDeploy} class="px-3 py-2 rounded border border-neutral-700">Continue to Deploy</button>
        </div>
      </WgettaStep>
    </Tabs.Content>

    <Tabs.Content value="deploy">
      <WgettaStep title="Deploy">
        <p>Deploy placeholder.</p>
        <div class="mt-2 flex gap-2">
          <button on:click={prev} class="px-3 py-2 rounded border border-neutral-700">Back</button>
        </div>
      </WgettaStep>
    </Tabs.Content>
  </Tabs.Root>
</main>
